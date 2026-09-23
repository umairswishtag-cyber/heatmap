<?php

namespace App\GraphQL\Mutations;

use App\Services\ProjectAccessService;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Validator;

class ProjectMutation
{
    public function __construct(private ProjectService $projects, private ProjectAccessService $access) {}

    public function create($_, array $args)
    {
        $input = Validator::make($args['input'], [
            'name' => ['required', 'string', 'max:120'],
            'domains' => ['required', 'array', 'min:1', 'max:20'],
            'domains.*' => ['required', 'string', 'max:255'],
        ])->validate();

        return $this->projects->create(request()->user(), $input);
    }

    public function update($_, array $args)
    {
        $input = Validator::make($args['input'], [
            'name' => ['sometimes', 'string', 'max:120'],
            'domains' => ['sometimes', 'array', 'min:1', 'max:20'],
            'domains.*' => ['string', 'max:255'],
            'recordingEnabled' => ['sometimes', 'boolean'],
            'samplingRate' => ['sometimes', 'integer', 'between:0,100'],
            'privacySettings' => ['sometimes', 'array'],
        ])->validate();

        return $this->projects->update($this->access->project(request()->user(), $args['id']), $input);
    }

    public function delete($_, array $args): bool
    {
        return $this->projects->delete($this->access->project(request()->user(), $args['id']));
    }
}
