<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RecordingProcessorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordingProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_processor_persists_gzip_rrweb_chunk_and_searchable_metadata(): void
    {
        Storage::fake('recordings');
        $project = User::factory()->create()->projects()->create(['name' => 'Store', 'public_key' => 'pk_test']);
        $timestamp = 1_700_000_000_000;
        $batch = [
            'visitorId' => 'visitor_test', 'sessionId' => 'session_test',
            'url' => 'https://example.com/', 'referrer' => null,
            'viewportWidth' => 1440, 'viewportHeight' => 900, 'userAgent' => 'Chrome/120',
            'events' => [
                ['kind' => 'rrweb', 'timestamp' => $timestamp, 'data' => ['type' => 4, 'timestamp' => $timestamp, 'data' => ['href' => 'https://example.com/']]],
                ['kind' => 'page_view', 'timestamp' => $timestamp, 'data' => ['url' => 'https://example.com/']],
                ['kind' => 'click', 'timestamp' => $timestamp + 1000, 'data' => ['url' => 'https://example.com/', 'x' => 20, 'y' => 30, 'viewportWidth' => 1440, 'viewportHeight' => 900, 'selector' => '#buy']],
            ],
        ];

        app(RecordingProcessorService::class)->process($project->id, 'ed8f1b18-6164-4a1b-83d1-b55c0cded45f', $batch);

        $this->assertDatabaseHas('sessions', ['session_uuid' => 'session_test', 'page_count' => 1, 'duration' => 1, 'device_type' => 'desktop']);
        $this->assertDatabaseHas('click_events', ['x' => 20, 'y' => 30]);
        $chunk = $project->sessions()->first()->chunks()->first();
        Storage::disk('recordings')->assertExists($chunk->storage_path);
        $decoded = json_decode(gzdecode(Storage::disk('recordings')->get($chunk->storage_path)), true);
        $this->assertSame(4, $decoded[0]['type']);
    }
}
