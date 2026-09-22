<?php

namespace App\GraphQL\Queries;

use App\Services\ConversionAnalyticsService;
use App\Services\ErrorAnalyticsService;
use App\Services\FormAnalyticsService;
use App\Services\FrustrationService;
use App\Services\FunnelService;
use App\Services\HeatmapService;
use App\Services\JourneyService;
use App\Services\OverviewService;
use App\Services\ProjectAccessService;
use App\Services\ReplayService;

class DashboardQuery
{
    public function __construct(
        private ProjectAccessService $access,
        private ReplayService $replay,
        private HeatmapService $heatmaps,
        private FunnelService $funnels,
        private JourneyService $journeys,
        private FormAnalyticsService $forms,
        private FrustrationService $frustration,
        private ErrorAnalyticsService $errors,
        private ConversionAnalyticsService $conversions,
        private OverviewService $overview,
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

        return $this->overview->report($project, $args['days'] ?? 7);
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

    public function journeyReport($_, array $args): array
    {
        return $this->journeys->report(
            $this->access->project(request()->user(), $args['projectId']),
            $args['days'] ?? 30,
            $args['device'] ?? null,
        );
    }

    public function formsReport($_, array $args): array
    {
        return $this->forms->report(
            $this->access->project(request()->user(), $args['projectId']),
            $args['days'] ?? 30,
            $args['device'] ?? null,
        );
    }

    public function frustrationReport($_, array $args): array
    {
        return $this->frustration->report(
            $this->access->project(request()->user(), $args['projectId']),
            $args['days'] ?? 30,
            $args['device'] ?? null,
        );
    }

    public function errorsReport($_, array $args): array
    {
        return $this->errors->report(
            $this->access->project(request()->user(), $args['projectId']),
            $args['days'] ?? 30,
            $args['device'] ?? null,
        );
    }

    public function conversionsReport($_, array $args): array
    {
        return $this->conversions->report(
            $this->access->project(request()->user(), $args['projectId']),
            $args['days'] ?? 30,
            $args['device'] ?? null,
        );
    }
}
