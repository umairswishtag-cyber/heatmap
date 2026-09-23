<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function create(User $user, array $input): Project
    {
        return DB::transaction(function () use ($user, $input) {
            $name = trim($input['name']);
            $domains = $this->normalizeDomains($input['domains']);
            $this->assertUniqueProject($user, $name, $domains);

            $project = $user->projects()->create([
                'name' => $name,
                'public_key' => 'pk_'.Str::random(40),
                'recording_enabled' => true,
                'sampling_rate' => 100,
                'privacy_settings' => ['maskInputs' => true, 'respectDnt' => true],
            ]);
            $this->syncDomains($project, $domains);

            return $project->load('domains');
        });
    }

    public function update(Project $project, array $input): Project
    {
        return DB::transaction(function () use ($project, $input) {
            $project->loadMissing(['domains', 'user']);
            $name = isset($input['name']) ? trim($input['name']) : $project->name;
            $domains = isset($input['domains'])
                ? $this->normalizeDomains($input['domains'])
                : $project->domains->pluck('domain');
            $this->assertUniqueProject($project->user, $name, $domains, $project->id);

            $project->fill(array_filter([
                'name' => isset($input['name']) ? $name : null,
                'recording_enabled' => $input['recordingEnabled'] ?? null,
                'sampling_rate' => $input['samplingRate'] ?? null,
                'privacy_settings' => $input['privacySettings'] ?? null,
            ], fn ($value) => $value !== null))->save();
            if (isset($input['domains'])) {
                $this->syncDomains($project, $domains);
            }

            return $project->fresh('domains');
        });
    }

    public function delete(Project $project): bool
    {
        $projectId = $project->id;
        DB::transaction(fn () => $project->delete());

        try {
            Storage::disk('recordings')->deleteDirectory("recordings/{$projectId}");
        } catch (\Throwable $exception) {
            report($exception);
        }

        return true;
    }

    private function normalizeDomains(array $domains): Collection
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

        return $normalized;
    }

    private function syncDomains(Project $project, Collection $normalized): void
    {

        $project->domains()->whereNotIn('domain', $normalized)->delete();
        foreach ($normalized as $domain) {
            $project->domains()->firstOrCreate(['domain' => $domain]);
        }
    }

    private function assertUniqueProject(User $user, string $name, Collection $domains, ?int $exceptId = null): void
    {
        $expectedDomains = $domains->sort()->values()->all();
        $duplicate = $user->projects()
            ->with('domains')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->get()
            ->contains(function (Project $project) use ($name, $expectedDomains): bool {
                return strcasecmp(trim($project->name), $name) === 0
                    && $project->domains->pluck('domain')->sort()->values()->all() === $expectedDomains;
            });

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'A project with this name and allowed domains already exists.',
            ]);
        }
    }
}
