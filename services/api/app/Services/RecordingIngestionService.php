<?php

namespace App\Services;

use App\Jobs\ProcessRecordingChunk;
use App\Models\Project;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordingIngestionService
{
    private const MAX_DECOMPRESSED_BYTES = 5_242_880;

    private const MAX_EVENTS = 5_000;

    public function ingest(array $input, ?string $origin): array
    {
        $project = Project::query()->with('domains')->where('public_key', $input['projectKey'])->firstOrFail();
        $this->assertAllowedOrigin($project, $origin);

        if (! $project->recording_enabled) {
            return ['accepted' => false, 'batchId' => null, 'eventCount' => 0];
        }

        $json = $this->decode($input['payload'], $input['encoding'] ?? 'GZIP_BASE64');
        if (strlen($json) > self::MAX_DECOMPRESSED_BYTES) {
            throw ValidationException::withMessages(['payload' => 'The decompressed batch exceeds 5 MB.']);
        }

        $events = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($events) || ! array_is_list($events) || count($events) > self::MAX_EVENTS) {
            throw ValidationException::withMessages(['payload' => 'The event batch must be a list of at most 5,000 records.']);
        }

        $batchId = (string) Str::uuid();
        ProcessRecordingChunk::dispatch($project->id, $batchId, [
            'visitorId' => $input['visitorId'],
            'sessionId' => $input['sessionId'],
            'url' => $input['url'],
            'referrer' => $input['referrer'] ?? null,
            'viewportWidth' => $input['viewportWidth'] ?? null,
            'viewportHeight' => $input['viewportHeight'] ?? null,
            'userAgent' => $input['userAgent'] ?? null,
            'events' => $events,
        ])->onQueue('recordings');

        return ['accepted' => true, 'batchId' => $batchId, 'eventCount' => count($events)];
    }

    private function decode(string $payload, string $encoding): string
    {
        if ($encoding === 'JSON') {
            return $payload;
        }
        $binary = base64_decode($payload, true);
        $json = $binary === false ? false : gzdecode($binary, self::MAX_DECOMPRESSED_BYTES + 1);
        if ($json === false) {
            throw ValidationException::withMessages(['payload' => 'Payload is not valid base64 gzip data.']);
        }

        return $json;
    }

    private function assertAllowedOrigin(Project $project, ?string $origin): void
    {
        $host = $origin ? strtolower((string) parse_url($origin, PHP_URL_HOST)) : '';
        if (! $host || ! $project->domains->contains('domain', $host)) {
            throw ValidationException::withMessages(['origin' => 'This origin is not allowed for the project.']);
        }
    }
}
