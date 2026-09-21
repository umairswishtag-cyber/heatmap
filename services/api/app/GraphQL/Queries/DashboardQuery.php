<?php

namespace App\GraphQL\Queries;

use App\Models\RecordingSession;
use App\Services\FunnelService;
use App\Services\HeatmapService;
use App\Services\ProjectAccessService;
use App\Services\ReplayService;

class DashboardQuery
{
    public function __construct(
        private ProjectAccessService $access,
        private ReplayService $replay,
        private HeatmapService $heatmaps,
        private FunnelService $funnels,
    ) {}

    public function projects(): iterable
    {
        return request()->user()->projects()->with('domains')->latest()->get();
    }

    public function project($_, array $args)
    {
        return $this->access->project(request()->user(), $args['id']);
    }

    public function sessions($_, array $args): iterable
    {
        $project = $this->access->project(request()->user(), $args['projectId']);

        return $project->sessions()->with('visitor')->latest('started_at')->limit(min($args['limit'] ?? 50, 100))->get();
    }

    public function session($_, array $args)
    {
        return $this->access->session(request()->user(), $args['id']);
    }

    public function overview($_, array $args): array
    {
        $project = $this->access->project(request()->user(), $args['projectId']);
        $base = RecordingSession::where('project_id', $project->id);
        $sessions = (clone $base)->count();
        $visitors = (clone $base)->distinct('visitor_id')->count('visitor_id');
        $conversions = (clone $base)->where('converted', true)->count();

        return [
            'visitors' => $visitors, 'sessions' => $sessions,
            'averageDuration' => (int) round((clone $base)->avg('duration') ?? 0),
            'pagesPerSession' => $sessions ? round((float) (clone $base)->avg('page_count'), 2) : 0,
            'conversionRate' => $sessions ? round($conversions * 100 / $sessions, 2) : 0,
            'conversions' => $conversions,
        ];
    }

    public function recordingEvents($_, array $args): array
    {
        return $this->replay->events($this->access->session(request()->user(), $args['sessionId']));
    }

    public function heatmapPages($_, array $args): array
    {
        $project = $this->access->project(request()->user(), $args['projectId']);

        return $this->heatmaps->pages($project, $args['days'] ?? 30, $args['device'] ?? null);
    }

    public function heatmap($_, array $args): array
    {
        $project = $this->access->project(request()->user(), $args['projectId']);

        return $this->heatmaps->report($project, $args['url'], $args['days'] ?? 30, $args['device'] ?? null);
    }

    public function funnels($_, array $args): iterable
    {
        return $this->access->project(request()->user(), $args['projectId'])->funnels()->get();
    }

    public function funnelOptions($_, array $args): array
    {
        $project = $this->access->project(request()->user(), $args['projectId']);

        return $this->funnels->options($project, $args['days'] ?? 30);
    }

    public function funnelAnalysis($_, array $args): array
    {
        $project = $this->access->project(request()->user(), $args['projectId']);

        return $this->funnels->analyze(
            $project,
            $args['steps'],
            $args['days'] ?? 30,
            $args['device'] ?? null,
            $args['windowMinutes'] ?? 30,
        );
    }
}
