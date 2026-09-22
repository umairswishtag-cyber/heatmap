<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RecordingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OverviewService
{
    public function report(Project $project, int $days = 7): array
    {
        $days = max(1, min($days, 90));
        $periodEnd = CarbonImmutable::now();
        $periodStart = $periodEnd->startOfDay()->subDays($days - 1);
        $previousStart = $periodStart->subDays($days);

        $rows = RecordingSession::query()
            ->where('project_id', $project->id)
            ->whereBetween('started_at', [$previousStart, $periodEnd])
            ->get(['visitor_id', 'duration', 'page_count', 'converted', 'started_at']);

        $current = $rows->filter(fn (RecordingSession $session) => $session->started_at->gte($periodStart));
        $previous = $rows->filter(fn (RecordingSession $session) => $session->started_at->lt($periodStart));
        $currentMetrics = $this->metrics($current);
        $previousMetrics = $this->metrics($previous);

        return [
            ...$currentMetrics,
            'visitorsChange' => $this->change($currentMetrics['visitors'], $previousMetrics['visitors']),
            'sessionsChange' => $this->change($currentMetrics['sessions'], $previousMetrics['sessions']),
            'durationChange' => $this->change($currentMetrics['averageDuration'], $previousMetrics['averageDuration']),
            'conversionChange' => $this->change($currentMetrics['conversionRate'], $previousMetrics['conversionRate']),
            'traffic' => $this->traffic($current, $periodStart, $days),
        ];
    }

    private function metrics(Collection $sessions): array
    {
        $count = $sessions->count();
        $conversions = $sessions->where('converted', true)->count();

        return [
            'visitors' => $sessions->pluck('visitor_id')->unique()->count(),
            'sessions' => $count,
            'averageDuration' => (int) round($sessions->avg('duration') ?? 0),
            'pagesPerSession' => $count ? round((float) $sessions->avg('page_count'), 2) : 0,
            'conversionRate' => $count ? round($conversions * 100 / $count, 2) : 0,
            'conversions' => $conversions,
        ];
    }

    private function traffic(Collection $sessions, CarbonImmutable $start, int $days): array
    {
        $byDate = $sessions->groupBy(fn (RecordingSession $session) => $session->started_at->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($byDate, $start) {
            $date = $start->addDays($offset);
            $daily = $byDate->get($date->toDateString(), collect());

            return [
                'date' => $date->toDateString(),
                'sessions' => $daily->count(),
                'visitors' => $daily->pluck('visitor_id')->unique()->count(),
                'conversions' => $daily->where('converted', true)->count(),
            ];
        })->all();
    }

    private function change(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}
