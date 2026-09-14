<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function create(User $user, array $input): Project
    {
        return DB::transaction(function () use ($user, $input) {
            $project = $user->projects()->create([
                'name' => trim($input['name']),
                'public_key' => 'pk_'.Str::random(40),
                'recording_enabled' => true,
                'sampling_rate' => 100,
                'privacy_settings' => ['maskInputs' => true, 'respectDnt' => true],
            ]);
            $this->syncDomains($project, $input['domains']);

            return $project->load('domains');
        });
    }

    public function update(Project $project, array $input): Project
    {
        return DB::transaction(function () use ($project, $input) {
            $project->fill(array_filter([
                'name' => $input['name'] ?? null,
                'recording_enabled' => $input['recordingEnabled'] ?? null,
                'sampling_rate' => $input['samplingRate'] ?? null,
                'privacy_settings' => $input['privacySettings'] ?? null,
            ], fn ($value) => $value !== null))->save();
            if (isset($input['domains'])) {
                $this->syncDomains($project, $input['domains']);
            }

            return $project->fresh('domains');
        });
    }

    private function syncDomains(Project $project, array $domains): void
    {
        $normalized = collect($domains)->map(function ($domain) {
            $domain = strtolower(trim($domain));
            $host = parse_url(str_contains($domain, '://') ? $domain : "https://{$domain}", PHP_URL_HOST);
            if (! $host) {
                throw ValidationException::withMessages(['domains' => 'Every domain must be a valid host.']);
            }

            return $host;
        })->unique()->values();

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages(['domains' => 'At least one allowed domain is required.']);
        }

        $project->domains()->whereNotIn('domain', $normalized)->delete();
        foreach ($normalized as $domain) {
            $project->domains()->firstOrCreate(['domain' => $domain]);
        }
    }
}
