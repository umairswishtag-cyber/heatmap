<?php

namespace App\Services;

use App\Models\RecordingSession;
use Illuminate\Support\Facades\Storage;

class ReplayService
{
    public function events(RecordingSession $session): array
    {
        return $session->chunks->flatMap(function ($chunk) {
            $compressed = Storage::disk($chunk->storage_disk)->get($chunk->storage_path);

            return json_decode(gzdecode($compressed), true, 64, JSON_THROW_ON_ERROR);
        })->sortBy('timestamp')->values()->all();
    }
}
