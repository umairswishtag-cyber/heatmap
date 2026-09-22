<?php

namespace App\Services;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FrustrationService extends AnalyticsReportService
{
    public function report(Project $project, int $days = 30, ?string $device = null): array
    {
        $sessions = $this->sessions($project, $days, $device)->get(['id']);
        $sessionIds = $sessions->pluck('id');
        $events = DB::table('analytics_events')
            ->whereIn('session_id', $sessionIds)
            ->whereIn('event_name', ['rage_click', 'dead_click'])
            ->orderBy('occurred_at')
            ->get();
        $signals = [];

        foreach ($events as $event) {
            $properties = $this->properties($event->properties);
            $signals[] = [
                'type' => $event->event_name,
                'url' => $event->url ?: '',
                'selector' => (string) ($properties['selector'] ?? ''),
                'sessionId' => (string) $event->session_id,
                'occurredAt' => CarbonImmutable::parse($event->occurred_at),
            ];
        }

        $quickBacks = $this->quickBacks($sessionIds->all());
        $signals = array_merge($signals, $quickBacks);
        $groups = [];

        foreach ($signals as $signal) {
            $key = implode('|', [$signal['type'], $signal['url'], $signal['selector']]);
            $groups[$key] ??= [
                'type' => $signal['type'],
                'url' => $signal['url'],
                'selector' => $signal['selector'],
                'count' => 0,
                'sessions' => [],
                'latestAt' => $signal['occurredAt'],
                'exampleSessionId' => $signal['sessionId'],
            ];
            $groups[$key]['count']++;
            $groups[$key]['sessions'][$signal['sessionId']] = true;
            if ($signal['occurredAt']->gt($groups[$key]['latestAt'])) {
                $groups[$key]['latestAt'] = $signal['occurredAt'];
                $groups[$key]['exampleSessionId'] = $signal['sessionId'];
            }
        }

        $items = collect($groups)->map(function ($group) {
            $group['sessions'] = count($group['sessions']);
            $group['latestAt'] = $group['latestAt']->toISOString();

            return $group;
        })->sortByDesc('count')->take(30)->values()->all();
        $affected = collect($signals)->pluck('sessionId')->unique()->count();

        return [
            'totalSessions' => $sessions->count(),
            'affectedSessions' => $affected,
            'affectedRate' => $sessions->count() ? round($affected * 100 / $sessions->count(), 1) : 0,
            'totalSignals' => count($signals),
            'rageClicks' => collect($signals)->where('type', 'rage_click')->count(),
            'deadClicks' => collect($signals)->where('type', 'dead_click')->count(),
            'quickBacks' => collect($signals)->where('type', 'quick_back')->count(),
            'items' => $items,
        ];
    }

    private function quickBacks(array $sessionIds): array
    {
        $pages = DB::table('analytics_events')
            ->whereIn('session_id', $sessionIds)
            ->where('event_name', 'page_view')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['session_id', 'url', 'occurred_at'])
            ->groupBy('session_id');
        $signals = [];

        foreach ($pages as $sessionId => $events) {
            $events = $events->values();
            for ($index = 2; $index < $events->count(); $index++) {
                $first = $events[$index - 2];
                $middle = $events[$index - 1];
                $last = $events[$index];
                $elapsed = CarbonImmutable::parse($middle->occurred_at)->diffInSeconds(CarbonImmutable::parse($last->occurred_at));
                if ($first->url === $last->url && $middle->url !== $last->url && $elapsed <= 7) {
                    $signals[] = [
                        'type' => 'quick_back',
                        'url' => $middle->url ?: '',
                        'selector' => '',
                        'sessionId' => (string) $sessionId,
                        'occurredAt' => CarbonImmutable::parse($last->occurred_at),
                    ];
                }
            }
        }

        return $signals;
    }
}
