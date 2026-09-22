<?php

namespace App\Services;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ConversionAnalyticsService extends AnalyticsReportService
{
    public function report(Project $project, int $days = 30, ?string $device = null): array
    {
        $days = min(365, max(1, $days));
        $sessions = $this->sessions($project, $days, $device)->get(['id']);
        $events = DB::table('analytics_events')
            ->join('sessions', 'sessions.id', '=', 'analytics_events.session_id')
            ->join('visitors', 'visitors.id', '=', 'sessions.visitor_id')
            ->whereIn('analytics_events.session_id', $sessions->pluck('id'))
            ->where('analytics_events.event_name', 'conversion')
            ->orderByDesc('analytics_events.occurred_at')
            ->get([
                'analytics_events.session_id', 'analytics_events.url', 'analytics_events.properties',
                'analytics_events.occurred_at', 'visitors.visitor_uuid',
            ]);
        $goals = [];
        $trend = [];
        $recent = [];
        $revenue = 0.0;
        $valuedConversions = 0;
        $reportCurrency = 'USD';

        foreach ($events as $event) {
            $properties = $this->properties($event->properties);
            $goal = trim((string) ($properties['name'] ?? 'Conversion')) ?: 'Conversion';
            $value = is_numeric($properties['value'] ?? null) ? (float) $properties['value'] : 0.0;
            $currency = strtoupper(substr((string) ($properties['currency'] ?? 'USD'), 0, 3));
            $reportCurrency = $currency ?: $reportCurrency;
            $date = CarbonImmutable::parse($event->occurred_at)->toDateString();
            $revenue += $value;
            $valuedConversions += $value > 0 ? 1 : 0;
            $trend[$date] = ($trend[$date] ?? 0) + 1;
            $goals[$goal] ??= ['name' => $goal, 'conversions' => 0, 'revenue' => 0.0, 'currency' => $currency];
            $goals[$goal]['conversions']++;
            $goals[$goal]['revenue'] += $value;
            if (count($recent) < 20) {
                $recent[] = [
                    'sessionId' => (string) $event->session_id,
                    'visitorId' => $event->visitor_uuid,
                    'goal' => $goal,
                    'value' => $value,
                    'currency' => $currency,
                    'url' => $event->url ?: '',
                    'occurredAt' => CarbonImmutable::parse($event->occurred_at)->toISOString(),
                ];
            }
        }

        $period = CarbonPeriod::create(now()->subDays($days - 1)->startOfDay(), now()->startOfDay());
        $trendRows = collect($period)->map(fn ($date) => [
            'date' => $date->toDateString(),
            'conversions' => $trend[$date->toDateString()] ?? 0,
        ])->values()->all();
        $convertedSessions = $events->pluck('session_id')->unique()->count();

        return [
            'totalSessions' => $sessions->count(),
            'totalConversions' => $events->count(),
            'convertedSessions' => $convertedSessions,
            'conversionRate' => $sessions->count() ? round($convertedSessions * 100 / $sessions->count(), 2) : 0,
            'revenue' => round($revenue, 2),
            'averageValue' => $valuedConversions ? round($revenue / $valuedConversions, 2) : 0,
            'currency' => $reportCurrency,
            'goals' => collect($goals)->sortByDesc('conversions')->values()->all(),
            'trend' => $trendRows,
            'recent' => $recent,
        ];
    }
}
