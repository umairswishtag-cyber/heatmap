<?php

namespace Tests\Feature;

use App\Jobs\ProcessRecordingChunk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GraphQLPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_can_register_and_create_a_project(): void
    {
        $register = $this->postJson('/graphql', [
            'query' => 'mutation Register($input:RegisterInput!){register(input:$input){token user{id email}}}',
            'variables' => ['input' => [
                'name' => 'Ada Owner', 'email' => 'ada@example.com',
                'password' => 'a-secure-password', 'password_confirmation' => 'a-secure-password',
            ]],
        ])->assertOk()->assertJsonPath('data.register.user.email', 'ada@example.com');

        $token = $register->json('data.register.token');
        $this->withToken($token)->postJson('/graphql', [
            'query' => 'mutation Create($input:CreateProjectInput!){createProject(input:$input){id name public_key domains{domain}}}',
            'variables' => ['input' => ['name' => 'Store', 'domains' => ['https://WWW.Example.com/products']]],
        ])->assertOk()
            ->assertJsonPath('data.createProject.name', 'Store')
            ->assertJsonPath('data.createProject.domains.0.domain', 'www.example.com');

        $this->assertDatabaseHas('projects', ['name' => 'Store']);
    }

    public function test_project_queries_are_tenant_scoped(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $owner->projects()->create(['name' => 'Private', 'public_key' => 'pk_private']);

        $response = $this->withToken($intruder->createToken('test')->plainTextToken)->postJson('/graphql', [
            'query' => 'query Project($id:ID!){project(id:$id){id name}}',
            'variables' => ['id' => $project->id],
        ])->assertOk();

        $this->assertNotEmpty($response->json('errors'));
        $this->assertNull($response->json('data.project'));
    }

    public function test_account_can_update_profile_and_password(): void
    {
        $user = User::factory()->create(['password' => 'old-secure-password']);
        $token = $user->createToken('test')->plainTextToken;
        $query = 'mutation Update($input:UpdateProfileInput!){updateProfile(input:$input){name email contact avatar_url}}';

        $this->withToken($token)->postJson('/graphql', [
            'query' => $query,
            'variables' => ['input' => [
                'name' => 'Updated Owner',
                'email' => 'updated@example.com',
                'contact' => '+92 300 1234567',
                'avatarUrl' => 'https://example.com/avatar.jpg',
                'currentPassword' => 'old-secure-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.updateProfile.name', 'Updated Owner')
            ->assertJsonPath('data.updateProfile.contact', '+92 300 1234567');

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_ingestion_accepts_only_an_allowed_origin_and_queues_work(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Store', 'public_key' => 'pk_allowed']);
        $project->domains()->create(['domain' => 'shop.example.com']);
        $input = [
            'projectKey' => 'pk_allowed', 'visitorId' => 'visitor_1', 'sessionId' => 'session_1',
            'url' => 'https://shop.example.com/products', 'encoding' => 'JSON',
            'payload' => json_encode([['kind' => 'page_view', 'timestamp' => 1_700_000_000_000, 'data' => ['url' => 'https://shop.example.com/products']]]),
        ];
        $query = 'mutation Ingest($input:RecordingBatchInput!){ingestRecording(input:$input){accepted eventCount batchId}}';

        $this->withHeader('Origin', 'https://shop.example.com')->postJson('/graphql', ['query' => $query, 'variables' => ['input' => $input]])
            ->assertOk()->assertJsonPath('data.ingestRecording.accepted', true)->assertJsonPath('data.ingestRecording.eventCount', 1);
        Queue::assertPushed(ProcessRecordingChunk::class, fn ($job) => $job->projectId === $project->id);

        $rejected = $this->withHeader('Origin', 'https://attacker.example')->postJson('/graphql', ['query' => $query, 'variables' => ['input' => $input]])->assertOk();
        $this->assertNotEmpty($rejected->json('errors'));
    }
}
