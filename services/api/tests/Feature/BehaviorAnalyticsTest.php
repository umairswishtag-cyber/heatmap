<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ConversionAnalyticsService;
use App\Services\ErrorAnalyticsService;
use App\Services\FormAnalyticsService;
use App\Services\FrustrationService;
use App\Services\FunnelService;
use App\Services\HeatmapService;
use App\Services\JourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BehaviorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_heatmap_report_aggregates_document_clicks_and_scroll_reach(): void
    {
        [$project, $session] = $this->createSessionFixture();
        $second = $this->newSession($project, 'session-without-scroll');
        $now = now();
        DB::table('analytics_events')->insert([
            ['project_id' => $project->id, 'session_id' => $session->id, 'event_name' => 'page_view', 'url' => 'https://shop.test/product', 'properties' => '{}', 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['project_id' => $project->id, 'session_id' => $second->id, 'event_name' => 'page_view', 'url' => 'https://shop.test/product', 'properties' => '{}', 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('click_events')->insert([
            ['project_id' => $project->id, 'session_id' => $session->id, 'url' => 'https://shop.test/product', 'x' => 10, 'y' => 20, 'page_x' => 720, 'page_y' => 1500, 'viewport_width' => 1440, 'viewport_height' => 900, 'document_height' => 3000, 'selector' => '#buy', 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('scroll_events')->insert([
            ['project_id' => $project->id, 'session_id' => $session->id, 'url' => 'https://shop.test/product', 'depth' => 76, 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $report = app(HeatmapService::class)->report($project, 'https://shop.test/product');

        $this->assertSame(2, $report['pageViews']);
        $this->assertSame(2, $report['sessions']);
        $this->assertSame(1, $report['totalClicks']);
        $this->assertSame(50.0, $report['points'][0]['x']);
        $this->assertSame(50.0, $report['points'][0]['y']);
        $this->assertSame(50.0, $report['scrollDepths'][2]['percentage']);
        $this->assertSame(0.0, $report['scrollDepths'][3]['percentage']);
        $this->assertSame(38.0, $report['averageScrollDepth']);
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

    public function test_journey_and_form_reports_surface_paths_and_abandonment(): void
    {
        [$project, $session] = $this->createSessionFixture();
        $second = $this->newSession($project, 'session-2');
        $now = now()->subMinutes(5);
        $this->event($project->id, $session->id, 'page_view', 'https://shop.test/', $now);
        $this->event($project->id, $session->id, 'page_view', 'https://shop.test/products/one', $now->copy()->addSecond());
        $this->event($project->id, $second->id, 'page_view', 'https://shop.test/', $now);
        $this->event($project->id, $session->id, 'form_focus', 'https://shop.test/contact', $now, ['formId' => 'contact', 'name' => 'email']);
        $this->event($project->id, $session->id, 'form_submit', 'https://shop.test/contact', $now->copy()->addSecond(), ['formId' => 'contact']);
        $this->event($project->id, $second->id, 'form_focus', 'https://shop.test/contact', $now, ['formId' => 'contact', 'name' => 'message']);

        $journeys = app(JourneyService::class)->report($project);
        $forms = app(FormAnalyticsService::class)->report($project);

        $this->assertSame(1.5, $journeys['averageSteps']);
        $this->assertSame('Homepage', $journeys['entryPages'][0]['page']);
        $this->assertSame(2, $forms['starts']);
        $this->assertSame(1, $forms['submissions']);
        $this->assertSame(1, $forms['abandonments']);
    }

    public function test_frustration_error_and_conversion_reports_prioritize_actionable_signals(): void
    {
        [$project, $session] = $this->createSessionFixture();
        $now = now()->subMinutes(5);
        $this->event($project->id, $session->id, 'rage_click', 'https://shop.test/product', $now, ['selector' => '#buy']);
        $this->event($project->id, $session->id, 'dead_click', 'https://shop.test/product', $now, ['selector' => '.fake-link']);
        $this->event($project->id, $session->id, 'page_view', 'https://shop.test/product', $now);
        $this->event($project->id, $session->id, 'page_view', 'https://shop.test/help', $now->copy()->addSeconds(2));
        $this->event($project->id, $session->id, 'page_view', 'https://shop.test/product', $now->copy()->addSeconds(5));
        $this->event($project->id, $session->id, 'javascript_error', 'https://shop.test/product', $now, ['message' => 'Cart failed', 'source' => 'cart.js', 'line' => 12]);
        $this->event($project->id, $session->id, 'conversion', 'https://shop.test/thank-you', $now, ['name' => 'Purchase', 'value' => 49.95, 'currency' => 'USD']);

        $frustration = app(FrustrationService::class)->report($project);
        $errors = app(ErrorAnalyticsService::class)->report($project);
        $conversions = app(ConversionAnalyticsService::class)->report($project);

        $this->assertSame(3, $frustration['totalSignals']);
        $this->assertSame(1, $frustration['quickBacks']);
        $this->assertSame('Cart failed', $errors['issues'][0]['message']);
        $this->assertSame(1, $conversions['totalConversions']);
        $this->assertSame(49.95, $conversions['revenue']);
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

    private function event(int $projectId, int $sessionId, string $name, string $url, $time, array $properties = []): void
    {
        DB::table('analytics_events')->insert(['project_id' => $projectId, 'session_id' => $sessionId, 'event_name' => $name, 'url' => $url, 'properties' => json_encode($properties), 'occurred_at' => $time, 'created_at' => $time, 'updated_at' => $time]);
    }
}
