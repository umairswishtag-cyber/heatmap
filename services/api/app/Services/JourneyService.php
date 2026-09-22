<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class JourneyService extends AnalyticsReportService
{
    public function report(Project $project, int $days = 30, ?string $device = null): array
    {
        $sessions = $this->sessions($project, $days, $device)
            ->get(['id', 'visitor_id', 'converted']);
        $events = DB::table('analytics_events')
            ->whereIn('session_id', $sessions->pluck('id'))
            ->where('event_name', 'page_view')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['session_id', 'url', 'occurred_at'])
            ->groupBy('session_id');

        $paths = [];
        $entries = [];
        $exits = [];
        $journeySessions = 0;
        $totalSteps = 0;
        $bounces = 0;

        foreach ($sessions as $session) {
            $steps = ($events[$session->id] ?? collect())
                ->pluck('url')
                ->map(fn ($url) => $this->pageLabel($url))
                ->reduce(function (array $carry, string $step) {
                    if (end($carry) !== $step) {
                        $carry[] = $step;
                    }

                    return $carry;
                }, []);

            if (! count($steps)) {
                continue;
            }

            $journeySessions++;
            $totalSteps += count($steps);
            $bounces += count($steps) === 1 ? 1 : 0;
            $entries[$steps[0]] = ($entries[$steps[0]] ?? 0) + 1;
            $exits[end($steps)] = ($exits[end($steps)] ?? 0) + 1;
            $signature = implode(' → ', $steps);
            $paths[$signature] ??= [
                'signature' => $signature,
                'steps' => $steps,
                'sessions' => 0,
                'conversions' => 0,
                'exampleSessionId' => (string) $session->id,
            ];
            $paths[$signature]['sessions']++;
            $paths[$signature]['conversions'] += $session->converted ? 1 : 0;
        }

        $pathRows = collect($paths)->sortByDesc('sessions')->take(12)->values()
            ->map(function ($path) use ($journeySessions) {
                $path['share'] = $journeySessions ? round($path['sessions'] * 100 / $journeySessions, 1) : 0;
                $path['conversionRate'] = $path['sessions'] ? round($path['conversions'] * 100 / $path['sessions'], 1) : 0;

                return $path;
            })->all();

        return [
            'totalSessions' => $sessions->count(),
            'uniqueVisitors' => $sessions->pluck('visitor_id')->unique()->count(),
            'averageSteps' => $journeySessions ? round($totalSteps / $journeySessions, 1) : 0,
            'bounceRate' => $journeySessions ? round($bounces * 100 / $journeySessions, 1) : 0,
            'paths' => $pathRows,
            'entryPages' => $this->rankPages($entries, $journeySessions),
            'exitPages' => $this->rankPages($exits, $journeySessions),
        ];
    }

    private function rankPages(array $pages, int $total): array
    {
        arsort($pages);

        return collect($pages)->take(8)->map(fn ($sessions, $page) => [
            'page' => $page,
            'sessions' => $sessions,
            'share' => $total ? round($sessions * 100 / $total, 1) : 0,
        ])->values()->all();
    }
}
