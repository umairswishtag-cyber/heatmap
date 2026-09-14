<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RecordingSession;
use App\Models\User;

class ProjectAccessService
{
    public function project(User $user, int|string $id): Project
    {
        return $user->projects()->with('domains')->findOrFail($id);
    }

    public function session(User $user, int|string $id): RecordingSession
    {
        return RecordingSession::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
            ->with(['visitor', 'chunks'])
            ->findOrFail($id);
    }
}
