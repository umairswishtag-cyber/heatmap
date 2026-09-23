<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class HeatmapService
{
    public function pages(Project $project, int $days = 30, ?string $device = null): array
    {
        $views = $this->eventsQuery($project, $days, $device)
            ->where('analytics_events.event_name', 'page_view')
            ->whereNotNull('analytics_events.url')
            ->select('analytics_events.url', DB::raw('COUNT(*) as views'))
            ->groupBy('analytics_events.url')
            ->get()->keyBy('url');

        $clicks = $this->clicksQuery($project, $days, $device)
            ->select('click_events.url', DB::raw('COUNT(*) as clicks'))
            ->groupBy('click_events.url')
            ->get()->keyBy('url');

        return $views->keys()->merge($clicks->keys())->unique()->map(function ($url) use ($views, $clicks) {
            return [
                'url' => $url,
                'views' => (int) ($views->get($url)->views ?? 0),
                'clicks' => (int) ($clicks->get($url)->clicks ?? 0),
            ];
        })->sortByDesc(fn ($page) => $page['views'] + $page['clicks'])->values()->all();
    }

    public function report(Project $project, string $url, int $days = 30, ?string $device = null): array
    {
        $clickRows = $this->clicksQuery($project, $days, $device)
            ->where('click_events.url', $url)
            ->select('click_events.*')
            ->limit(10000)
            ->get();

        $pointGroups = [];
        foreach ($clickRows as $click) {
            $documentHeight = max(1, (int) ($click->document_height ?: $click->viewport_height));
            $x = min(100, max(0, (($click->page_x ?? $click->x) / max(1, $click->viewport_width)) * 100));
            $y = min(100, max(0, (($click->page_y ?? $click->y) / $documentHeight) * 100));
            $key = round($x / 3).':'.round($y / 3);
            if (! isset($pointGroups[$key])) {
                $pointGroups[$key] = ['x' => round($x, 2), 'y' => round($y, 2), 'clicks' => 0, 'selector' => $click->selector ?: null];
            }
            $pointGroups[$key]['clicks']++;
        }

        $pageSessionIds = $this->eventsQuery($project, $days, $device)
            ->where('analytics_events.url', $url)
            ->distinct()
            ->pluck('analytics_events.session_id');
        $sessionMaxDepthRows = $this->scrollsQuery($project, $days, $device)
            ->where('scroll_events.url', $url)
            ->select('scroll_events.session_id', DB::raw('MAX(scroll_events.depth) as depth'))
            ->groupBy('scroll_events.session_id')
            ->get();
        $sessionMaxDepths = $pageSessionIds->mapWithKeys(fn ($sessionId) => [(string) $sessionId => 0]);
        foreach ($sessionMaxDepthRows as $row) {
            $sessionMaxDepths->put((string) $row->session_id, (int) $row->depth);
        }
        $pageSessions = $sessionMaxDepths->count();
        $scrollDepths = collect([25, 50, 75, 100])->map(function ($depth) use ($sessionMaxDepths, $pageSessions) {
            $visitors = $sessionMaxDepths->filter(fn ($value) => $value >= $depth)->count();

            return ['depth' => $depth, 'visitors' => $visitors, 'percentage' => $pageSessions ? round($visitors * 100 / $pageSessions, 1) : 0];
        })->all();

        $pageViews = $this->eventsQuery($project, $days, $device)
            ->where('analytics_events.event_name', 'page_view')->where('analytics_events.url', $url)->count();
        $sessions = $pageSessionIds->count();
        $topElements = $clickRows->groupBy(fn ($click) => $click->selector ?: 'Unidentified element')
            ->map(fn ($rows, $selector) => [
                'selector' => $selector,
                'clicks' => $rows->count(),
                'share' => $clickRows->count() ? round($rows->count() * 100 / $clickRows->count(), 1) : 0,
            ])->sortByDesc('clicks')->take(8)->values()->all();

        return [
            'url' => $url,
            'pageViews' => $pageViews,
            'sessions' => $sessions,
            'totalClicks' => $clickRows->count(),
            'averageScrollDepth' => $pageSessions ? round((float) $sessionMaxDepths->average(), 1) : 0,
            'points' => collect($pointGroups)->sortByDesc('clicks')->take(500)->values()->all(),
            'scrollDepths' => $scrollDepths,
            'topElements' => $topElements,
        ];
    }

    private function eventsQuery(Project $project, int $days, ?string $device): Builder
    {
        return DB::table('analytics_events')->join('sessions', 'sessions.id', '=', 'analytics_events.session_id')
            ->where('analytics_events.project_id', $project->id)
            ->where('analytics_events.occurred_at', '>=', now()->subDays($this->safeDays($days)))
            ->when($device && $device !== 'all', fn ($query) => $query->where('sessions.device_type', $device));
    }

    private function clicksQuery(Project $project, int $days, ?string $device): Builder
    {
        return DB::table('click_events')->join('sessions', 'sessions.id', '=', 'click_events.session_id')
            ->where('click_events.project_id', $project->id)
            ->where('click_events.occurred_at', '>=', now()->subDays($this->safeDays($days)))
            ->when($device && $device !== 'all', fn ($query) => $query->where('sessions.device_type', $device));
    }

    private function scrollsQuery(Project $project, int $days, ?string $device): Builder
    {
        return DB::table('scroll_events')->join('sessions', 'sessions.id', '=', 'scroll_events.session_id')
            ->where('scroll_events.project_id', $project->id)
            ->where('scroll_events.occurred_at', '>=', now()->subDays($this->safeDays($days)))
            ->when($device && $device !== 'all', fn ($query) => $query->where('sessions.device_type', $device));
    }

    private function safeDays(int $days): int
    {
        return min(365, max(1, $days));
    }
}
