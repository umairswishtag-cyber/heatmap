<?php

namespace App\GraphQL\Mutations;

use App\Models\Funnel;
use App\Services\FunnelService;
use App\Services\ProjectAccessService;
use Illuminate\Validation\ValidationException;

class FunnelMutation
{
    public function __construct(private ProjectAccessService $access, private FunnelService $funnels) {}

    public function save($_, array $args): Funnel
    {
        $input = $args['input'];
        $project = $this->access->project(request()->user(), $input['projectId']);
        $steps = $this->funnels->normalizeSteps($input['steps']);
        if (count($steps) < 2) {
            throw ValidationException::withMessages(['steps' => 'A funnel needs at least two valid steps.']);
        }

        $funnel = isset($input['id'])
            ? $project->funnels()->findOrFail($input['id'])
            : new Funnel(['project_id' => $project->id]);
        $funnel->fill([
            'name' => trim($input['name']),
            'steps' => $steps,
            'window_minutes' => min(43200, max(1, (int) ($input['windowMinutes'] ?? 30))),
        ])->save();

        return $funnel->fresh();
    }

    public function delete($_, array $args): bool
    {
        $funnel = Funnel::findOrFail($args['id']);
        $this->access->project(request()->user(), $funnel->project_id);

        return (bool) $funnel->delete();
    }
}
