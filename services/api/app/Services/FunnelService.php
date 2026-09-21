<?php

namespace App\Services;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FunnelService
{
    public function options(Project $project, int $days = 30): array
    {
        $since = now()->subDays(min(365, max(1, $days)));
        $pages = DB::table('analytics_events')->where('project_id', $project->id)->where('event_name', 'page_view')
            ->where('occurred_at', '>=', $since)->whereNotNull('url')->select('url as value', DB::raw('COUNT(*) as uses'))
            ->groupBy('url')->orderByDesc('uses')->limit(50)->get()
            ->map(fn ($item) => ['type' => 'page', 'value' => $item->value, 'label' => $this->pageLabel($item->value), 'uses' => (int) $item->uses]);
        $events = DB::table('analytics_events')->where('project_id', $project->id)->where('event_name', '!=', 'page_view')
            ->where('occurred_at', '>=', $since)->select('event_name as value', DB::raw('COUNT(*) as uses'))
            ->groupBy('event_name')->orderByDesc('uses')->limit(50)->get()
            ->map(fn ($item) => ['type' => 'event', 'value' => $item->value, 'label' => str($item->value)->replace('_', ' ')->title()->toString(), 'uses' => (int) $item->uses]);

        return $pages->concat($events)->values()->all();
    }

    public function analyze(Project $project, array $steps, int $days = 30, ?string $device = null, int $windowMinutes = 30): array
    {
        $steps = $this->normalizeSteps($steps);
        $sessionQuery = DB::table('sessions')->where('project_id', $project->id)
            ->where('started_at', '>=', now()->subDays(min(365, max(1, $days))))
            ->when($device && $device !== 'all', fn ($query) => $query->where('device_type', $device));
        $sessionIds = $sessionQuery->pluck('id');
        $eventsBySession = DB::table('analytics_events')->whereIn('session_id', $sessionIds)
            ->orderBy('occurred_at')->get()->groupBy('session_id');

        $counts = array_fill(0, count($steps), 0);
        $durations = array_fill(0, count($steps), []);
        $completedSessions = [];
        foreach ($eventsBySession as $sessionId => $events) {
            $matchedAt = $this->matchSequence($events, $steps, $windowMinutes);
            foreach ($matchedAt as $index => $time) {
                if ($time === null) {
                    break;
                }
                $counts[$index]++;
                if ($index > 0) {
                    $durations[$index][] = CarbonImmutable::parse($matchedAt[$index - 1])->diffInSeconds(CarbonImmutable::parse($time));
                }
            }
            if (count($matchedAt) === count($steps) && end($matchedAt) !== null) {
                $completedSessions[] = (string) $sessionId;
            }
        }

        $firstCount = $counts[0] ?? 0;
        $resultSteps = collect($steps)->map(function ($step, $index) use ($counts, $durations) {
            $previous = $index === 0 ? $counts[0] : $counts[$index - 1];
            $dropoff = $index === 0 ? 0 : max(0, $previous - $counts[$index]);
            return [
                'label' => $step['label'], 'type' => $step['type'], 'value' => $step['value'],
                'count' => $counts[$index],
                'conversionFromPrevious' => $previous ? round($counts[$index] * 100 / $previous, 1) : 0,
                'dropoff' => $dropoff,
                'dropoffRate' => $previous && $index > 0 ? round($dropoff * 100 / $previous, 1) : 0,
                'medianTimeFromPrevious' => $index > 0 ? $this->median($durations[$index]) : 0,
            ];
        })->all();

        $fullDurations = [];
        foreach ($eventsBySession as $events) {
            $matchedAt = $this->matchSequence($events, $steps, $windowMinutes);
            if (count($matchedAt) === count($steps) && end($matchedAt) !== null) {
                $fullDurations[] = CarbonImmutable::parse($matchedAt[0])->diffInSeconds(CarbonImmutable::parse(end($matchedAt)));
            }
        }

        return [
            'totalSessions' => $sessionIds->count(),
            'startedSessions' => $firstCount,
            'completedSessions' => count($completedSessions),
            'conversionRate' => $firstCount ? round(count($completedSessions) * 100 / $firstCount, 1) : 0,
            'medianTimeToConvert' => $this->median($fullDurations),
            'steps' => $resultSteps,
        ];
    }

    public function normalizeSteps(array $steps): array
    {
        return collect($steps)->take(8)->map(function ($step, $index) {
            $type = ($step['type'] ?? '') === 'event' ? 'event' : 'page';
            $value = trim((string) ($step['value'] ?? ''));
            return [
                'type' => $type,
                'value' => $value,
                'operator' => ($step['operator'] ?? 'exact') === 'contains' ? 'contains' : 'exact',
                'label' => trim((string) ($step['label'] ?? '')) ?: 'Step '.($index + 1),
            ];
        })->filter(fn ($step) => $step['value'] !== '')->values()->all();
    }

    private function matchSequence(Collection $events, array $steps, int $windowMinutes): array
    {
        $matches = [];
        $after = null;
        $afterId = null;
        $started = null;
        foreach ($steps as $step) {
            $match = $events->first(function ($event) use ($step, $after, $afterId, $started, $windowMinutes) {
                $occurredAt = CarbonImmutable::parse($event->occurred_at);
                if ($after && ($occurredAt->lt($after) || ($occurredAt->equalTo($after) && $event->id <= $afterId))) {
                    return false;
                }
                if ($started && $occurredAt->gt($started->addMinutes(min(43200, max(1, $windowMinutes))))) {
                    return false;
                }
                if ($step['type'] === 'event') {
                    return $event->event_name === $step['value'];
                }
                if ($event->event_name !== 'page_view') {
                    return false;
                }

                return $step['operator'] === 'contains'
                    ? str_contains((string) $event->url, $step['value'])
                    : $event->url === $step['value'];
            });
            if (! $match) {
                $matches[] = null;
                break;
            }
            $after = CarbonImmutable::parse($match->occurred_at);
            $afterId = $match->id;
            $started ??= $after;
            $matches[] = $after->toISOString();
        }

        return $matches;
    }

    private function median(array $values): int
    {
        if (! count($values)) {
            return 0;
        }
        sort($values);
        $middle = intdiv(count($values), 2);
        return count($values) % 2 ? (int) $values[$middle] : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function pageLabel(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        return $path === '/' ? 'Homepage' : $path;
    }
}
