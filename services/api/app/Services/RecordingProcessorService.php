<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RecordingSession;
use App\Models\SessionChunk;
use App\Models\Visitor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RecordingProcessorService
{
    public function process(int $projectId, string $batchId, array $batch): void
    {
        if (SessionChunk::where('batch_id', $batchId)->exists()) {
            return;
        }

        $project = Project::findOrFail($projectId);
        $timestamps = collect($batch['events'])->pluck('timestamp')->filter()->map(fn ($value) => CarbonImmutable::createFromTimestampMs((int) $value));
        $startedAt = $timestamps->min() ?? now();
        $endedAt = $timestamps->max() ?? $startedAt;
        $rrwebEvents = collect($batch['events'])->where('kind', 'rrweb')->pluck('data')->values()->all();
        $compressed = gzencode(json_encode($rrwebEvents, JSON_THROW_ON_ERROR), 6);

        DB::transaction(function () use ($project, $batchId, $batch, $startedAt, $endedAt, $compressed, $rrwebEvents) {
            $visitor = Visitor::firstOrCreate(
                ['project_id' => $project->id, 'visitor_uuid' => $batch['visitorId']],
                ['first_seen_at' => $startedAt, 'last_seen_at' => $endedAt]
            );
            $visitor->forceFill(['last_seen_at' => $endedAt])->save();

            $session = RecordingSession::firstOrCreate(
                ['project_id' => $project->id, 'session_uuid' => $batch['sessionId']],
                [
                    'visitor_id' => $visitor->id,
                    'started_at' => $startedAt,
                    'landing_page' => $batch['url'],
                    'referrer' => $batch['referrer'],
                    'screen_width' => $batch['viewportWidth'],
                    'screen_height' => $batch['viewportHeight'],
                    'device_type' => $this->deviceType((int) ($batch['viewportWidth'] ?? 0)),
                    'browser' => $this->browser($batch['userAgent'] ?? ''),
                ]
            );

            $sequence = ((int) $session->chunks()->max('sequence')) + 1;
            $path = "recordings/{$project->id}/{$session->session_uuid}/chunk_".str_pad((string) $sequence, 6, '0', STR_PAD_LEFT).'.json.gz';
            Storage::disk('recordings')->put($path, $compressed);

            $session->chunks()->create([
                'project_id' => $project->id,
                'batch_id' => $batchId,
                'sequence' => $sequence,
                'storage_disk' => 'recordings',
                'storage_path' => $path,
                'event_count' => count($rrwebEvents),
                'compressed_bytes' => strlen($compressed),
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
            ]);

            $this->storeSemanticEvents($project->id, $session, $batch['events'], $batch['url']);
            $pageCount = $session->hasAttribute('page_count') ? $session->page_count : 0;
            $newPages = collect($batch['events'])->where('kind', 'page_view')->count();
            $session->forceFill([
                'ended_at' => $endedAt,
                'duration' => max(0, (int) round($session->started_at->diffInSeconds($endedAt))),
                'exit_page' => $batch['url'],
                'page_count' => $pageCount + $newPages,
            ])->save();
            $project->forceFill(['last_event_at' => now()])->save();
        });
    }

    private function storeSemanticEvents(int $projectId, RecordingSession $session, array $events, string $fallbackUrl): void
    {
        foreach ($events as $event) {
            $kind = $event['kind'] ?? null;
            if (! $kind || $kind === 'rrweb') {
                continue;
            }
            $occurredAt = isset($event['timestamp']) ? CarbonImmutable::createFromTimestampMs((int) $event['timestamp']) : now();
            $data = is_array($event['data'] ?? null) ? $event['data'] : [];
            DB::table('analytics_events')->insert([
                'project_id' => $projectId, 'session_id' => $session->id, 'event_name' => $kind,
                'url' => $data['url'] ?? $fallbackUrl, 'properties' => json_encode($data),
                'occurred_at' => $occurredAt, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($kind === 'click') {
                DB::table('click_events')->insert([
                    'project_id' => $projectId, 'session_id' => $session->id, 'url' => $data['url'] ?? $fallbackUrl,
                    'x' => (int) ($data['x'] ?? 0), 'y' => (int) ($data['y'] ?? 0),
                    'page_x' => isset($data['pageX']) ? (int) $data['pageX'] : null,
                    'page_y' => isset($data['pageY']) ? (int) $data['pageY'] : null,
                    'viewport_width' => max(1, (int) ($data['viewportWidth'] ?? 1)),
                    'viewport_height' => max(1, (int) ($data['viewportHeight'] ?? 1)),
                    'document_height' => isset($data['documentHeight']) ? max(1, (int) $data['documentHeight']) : null,
                    'selector' => substr($data['selector'] ?? '', 0, 500), 'occurred_at' => $occurredAt,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($kind === 'scroll') {
                DB::table('scroll_events')->insert([
                    'project_id' => $projectId, 'session_id' => $session->id, 'url' => $data['url'] ?? $fallbackUrl,
                    'depth' => min(100, max(0, (int) ($data['depth'] ?? 0))), 'occurred_at' => $occurredAt,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function deviceType(int $width): string
    {
        return $width <= 767 ? 'mobile' : ($width <= 1024 ? 'tablet' : 'desktop');
    }

    private function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => 'Other',
        };
    }
}
