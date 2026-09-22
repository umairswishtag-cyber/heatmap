<?php

namespace App\Services;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ErrorAnalyticsService extends AnalyticsReportService
{
    public function report(Project $project, int $days = 30, ?string $device = null): array
    {
        $sessions = $this->sessions($project, $days, $device)->get(['id']);
        $events = DB::table('analytics_events')
            ->whereIn('session_id', $sessions->pluck('id'))
            ->where('event_name', 'javascript_error')
            ->orderByDesc('occurred_at')
            ->get();
        $issues = [];
        $pages = [];

        foreach ($events as $event) {
            $properties = $this->properties($event->properties);
            $message = trim((string) ($properties['message'] ?? 'Unknown error')) ?: 'Unknown error';
            $source = (string) ($properties['source'] ?? '');
            $line = (int) ($properties['line'] ?? 0);
            $fingerprint = substr(hash('sha256', $message.'|'.$source.'|'.$line), 0, 16);
            $occurredAt = CarbonImmutable::parse($event->occurred_at);
            $issues[$fingerprint] ??= [
                'fingerprint' => $fingerprint,
                'message' => $message,
                'source' => $source,
                'line' => $line,
                'url' => $event->url ?: '',
                'count' => 0,
                'sessions' => [],
                'firstSeen' => $occurredAt,
                'lastSeen' => $occurredAt,
                'exampleSessionId' => (string) $event->session_id,
            ];
            $issues[$fingerprint]['count']++;
            $issues[$fingerprint]['sessions'][(string) $event->session_id] = true;
            if ($occurredAt->lt($issues[$fingerprint]['firstSeen'])) {
                $issues[$fingerprint]['firstSeen'] = $occurredAt;
            }
            if ($occurredAt->gt($issues[$fingerprint]['lastSeen'])) {
                $issues[$fingerprint]['lastSeen'] = $occurredAt;
                $issues[$fingerprint]['exampleSessionId'] = (string) $event->session_id;
            }
            $page = $this->pageLabel($event->url);
            $pages[$page] = ($pages[$page] ?? 0) + 1;
        }

        $rows = collect($issues)->map(function ($issue) {
            $issue['sessions'] = count($issue['sessions']);
            $issue['firstSeen'] = $issue['firstSeen']->toISOString();
            $issue['lastSeen'] = $issue['lastSeen']->toISOString();

            return $issue;
        })->sortByDesc('count')->take(50)->values()->all();
        arsort($pages);
        $affected = $events->pluck('session_id')->unique()->count();

        return [
            'totalErrors' => $events->count(),
            'uniqueErrors' => count($issues),
            'affectedSessions' => $affected,
            'errorRate' => $sessions->count() ? round($affected * 100 / $sessions->count(), 1) : 0,
            'issues' => $rows,
            'topPages' => collect($pages)->take(8)->map(fn ($count, $page) => [
                'page' => $page,
                'count' => $count,
            ])->values()->all(),
        ];
    }
}
