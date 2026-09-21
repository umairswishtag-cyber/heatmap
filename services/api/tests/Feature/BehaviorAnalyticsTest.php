<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FunnelService;
use App\Services\HeatmapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BehaviorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_heatmap_report_aggregates_document_clicks_and_scroll_reach(): void
    {
        [$project, $session] = $this->createSessionFixture();
        $now = now();
        DB::table('analytics_events')->insert([
            ['project_id' => $project->id, 'session_id' => $session->id, 'event_name' => 'page_view', 'url' => 'https://shop.test/product', 'properties' => '{}', 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('click_events')->insert([
            ['project_id' => $project->id, 'session_id' => $session->id, 'url' => 'https://shop.test/product', 'x' => 10, 'y' => 20, 'page_x' => 720, 'page_y' => 1500, 'viewport_width' => 1440, 'viewport_height' => 900, 'document_height' => 3000, 'selector' => '#buy', 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('scroll_events')->insert([
            ['project_id' => $project->id, 'session_id' => $session->id, 'url' => 'https://shop.test/product', 'depth' => 76, 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $report = app(HeatmapService::class)->report($project, 'https://shop.test/product');

        $this->assertSame(1, $report['pageViews']);
        $this->assertSame(1, $report['totalClicks']);
        $this->assertSame(50.0, $report['points'][0]['x']);
        $this->assertSame(50.0, $report['points'][0]['y']);
        $this->assertSame(100.0, $report['scrollDepths'][2]['percentage']);
        $this->assertSame(0.0, $report['scrollDepths'][3]['percentage']);
    }

    public function test_funnel_analysis_requires_order_and_reports_dropoff(): void
    {
        [$project, $session] = $this->createSessionFixture();
        $second = $this->newSession($project, 'session-2');
        $now = now()->subMinutes(5);
        $this->event($project->id, $session->id, 'page_view', 'https://shop.test/product', $now);
        $this->event($project->id, $session->id, 'add_to_cart', 'https://shop.test/product', $now->copy()->addSeconds(20));
        $this->event($project->id, $session->id, 'page_view', 'https://shop.test/checkout', $now->copy()->addSeconds(50));
        $this->event($project->id, $second->id, 'page_view', 'https://shop.test/product', $now);
        $this->event($project->id, $second->id, 'add_to_cart', 'https://shop.test/product', $now->copy()->addSeconds(10));

        $steps = [
            ['type' => 'page', 'value' => 'https://shop.test/product', 'label' => 'Product'],
            ['type' => 'event', 'value' => 'add_to_cart', 'label' => 'Add to cart'],
            ['type' => 'page', 'value' => 'https://shop.test/checkout', 'label' => 'Checkout'],
        ];
        $analysis = app(FunnelService::class)->analyze($project, $steps);

        $this->assertSame([2, 2, 1], array_column($analysis['steps'], 'count'));
        $this->assertSame(50.0, $analysis['conversionRate']);
        $this->assertSame(1, $analysis['steps'][2]['dropoff']);
        $this->assertSame(50, $analysis['medianTimeToConvert']);
    }

    private function createSessionFixture(): array
    {
        $project = User::factory()->create()->projects()->create(['name' => 'Store', 'public_key' => 'pk_behavior']);

        return [$project, $this->newSession($project, 'session-1')];
    }

    private function newSession($project, string $uuid)
    {
        $visitor = $project->visitors()->create(['visitor_uuid' => 'visitor-'.$uuid, 'first_seen_at' => now(), 'last_seen_at' => now()]);

        return $project->sessions()->create(['visitor_id' => $visitor->id, 'session_uuid' => $uuid, 'started_at' => now()->subMinutes(10), 'device_type' => 'desktop']);
    }

    private function event(int $projectId, int $sessionId, string $name, string $url, $time): void
    {
        DB::table('analytics_events')->insert(['project_id' => $projectId, 'session_id' => $sessionId, 'event_name' => $name, 'url' => $url, 'properties' => '{}', 'occurred_at' => $time, 'created_at' => $time, 'updated_at' => $time]);
    }
}
