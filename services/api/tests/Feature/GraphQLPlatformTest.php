<?php

namespace Tests\Feature;

use App\Jobs\ProcessRecordingChunk;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    public function test_account_can_edit_and_delete_a_project_and_cannot_create_an_exact_duplicate(): void
    {
        Storage::fake('recordings');
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $project = $user->projects()->create(['name' => 'Store', 'public_key' => 'pk_manage']);
        $project->domains()->create(['domain' => 'example.com']);
        $recordingPath = "recordings/{$project->id}/session-1/chunk_000001.json.gz";
        Storage::disk('recordings')->put($recordingPath, 'recording');

        $this->withToken($token)->postJson('/graphql', [
            'query' => 'mutation Update($id:ID!,$input:UpdateProjectInput!){updateProject(id:$id,input:$input){id name recording_enabled sampling_rate domains{domain}}}',
            'variables' => [
                'id' => $project->id,
                'input' => [
                    'name' => 'Storefront',
                    'domains' => ['https://SHOP.example.com/products', 'shop.example.com'],
                    'recordingEnabled' => false,
                    'samplingRate' => 35,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.updateProject.name', 'Storefront')
            ->assertJsonPath('data.updateProject.recording_enabled', false)
            ->assertJsonPath('data.updateProject.sampling_rate', 35)
            ->assertJsonCount(1, 'data.updateProject.domains')
            ->assertJsonPath('data.updateProject.domains.0.domain', 'shop.example.com');

        $duplicate = $this->withToken($token)->postJson('/graphql', [
            'query' => 'mutation Create($input:CreateProjectInput!){createProject(input:$input){id}}',
            'variables' => ['input' => [
                'name' => 'storefront',
                'domains' => ['https://shop.example.com/checkout'],
            ]],
        ])->assertOk();

        $this->assertNotEmpty($duplicate->json('errors'));
        $this->assertDatabaseCount('projects', 1);

        $this->withToken($token)->postJson('/graphql', [
            'query' => 'mutation Delete($id:ID!){deleteProject(id:$id)}',
            'variables' => ['id' => $project->id],
        ])->assertOk()->assertJsonPath('data.deleteProject', true);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_domains', ['project_id' => $project->id]);
        Storage::disk('recordings')->assertMissing($recordingPath);
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
                'email' => 'UPDATED@example.com',
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

    public function test_account_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => 'old-secure-password']);
        $user->createToken('existing-session');

        $this->postJson('/graphql', [
            'query' => 'mutation Forgot($email:String!){requestPasswordReset(email:$email)}',
            'variables' => ['email' => 'OWNER@example.com'],
        ])->assertOk()->assertJsonPath('data.requestPasswordReset', true);

        $token = null;
        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user, &$token): bool {
                $token = $notification->token;
                $this->assertStringContainsString('/reset-password?', $notification->toMail($user)->actionUrl);

                return true;
            }
        );

        $this->postJson('/graphql', [
            'query' => 'mutation Reset($input:ResetPasswordInput!){resetPassword(input:$input)}',
            'variables' => ['input' => [
                'token' => $token,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ]],
        ])->assertOk()->assertJsonPath('data.resetPassword', true);

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_password_reset_request_does_not_reveal_unknown_email_addresses(): void
    {
        Notification::fake();

        $this->postJson('/graphql', [
            'query' => 'mutation Forgot($email:String!){requestPasswordReset(email:$email)}',
            'variables' => ['email' => 'missing@example.com'],
        ])->assertOk()->assertJsonPath('data.requestPasswordReset', true);

        Notification::assertNothingSent();
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
