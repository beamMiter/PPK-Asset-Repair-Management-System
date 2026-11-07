<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The citizen ID is a person's national ID number and also the name they sign in with. `GET /api/repair-requests/pending/evaluations`
 * handed every reporter the whole user record of the technician on each job, citizen ID included, because a User turned into
 * JSON carried it. It is now hidden from every JSON answer; the owner still reads their own through /api/auth/me.
 */
class CitizenIdIsNotHandedOutTest extends TestCase
{
    use RefreshDatabase;

    private function resolvedJobFor(User $reporter, User $technician): MaintenanceRequest
    {
        $job = MaintenanceRequest::factory()->create([
            'status' => 'resolved', 'reporter_id' => $reporter->id, 'technician_id' => $technician->id, 'asset_id' => null,
            'resolved_at' => now()->subDay(), 'closed_at' => null, 'completed_date' => null,
        ]);
        MaintenanceAssignment::create([
            'maintenance_request_id' => $job->id, 'user_id' => $technician->id, 'role' => 'technician', 'is_lead' => true, 'status' => 'in_progress',
        ]);

        return $job;
    }

    public function test_the_reporters_waiting_list_names_the_technician_and_nothing_more(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $technician = User::factory()->create(['role' => 'technician']);
        $this->resolvedJobFor($reporter, $technician);
        Sanctum::actingAs($reporter);

        $res = $this->getJson('/api/repair-requests/pending/evaluations')->assertOk();

        $this->assertSame($technician->name, $res->json('data.0.technician.name'));
        $this->assertStringNotContainsString($technician->citizen_id, $res->getContent());
        foreach (['citizen_id', 'email', 'suspended_at', 'must_change_password'] as $field) {
            $this->assertArrayNotHasKey($field, $res->json('data.0.technician'), "the reporter has no use for the technician's {$field}");
        }
    }

    public function test_a_user_turned_into_json_never_carries_the_citizen_id(): void
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('citizen_id', $user->toArray());
        $this->assertStringNotContainsString($user->citizen_id, $user->toJson());
    }

    public function test_the_owner_still_reads_their_own_citizen_id(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->postJson('/api/auth/login', ['citizen_id' => $user->citizen_id, 'password' => 'password'])
            ->assertSuccessful()
            ->assertJsonPath('user.citizen_id', $user->citizen_id);

        Sanctum::actingAs($user);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('citizen_id', $user->citizen_id);
    }

    public function test_no_page_or_api_answer_gives_a_member_or_a_technician_someone_elses_citizen_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $technician = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'it_support']);
        $member = User::factory()->create(['role' => 'member']);
        $everyone = [$admin, $supervisor, $technician, $other, $member];

        $done = $this->resolvedJobFor($member, $technician);
        $open = $this->resolvedJobFor($member, $other);
        $open->update(['status' => 'in_progress', 'resolved_at' => null, 'started_at' => now()]);
        $asset = Asset::factory()->create(['his_asset_id' => null]);
        $thread = ChatThread::create(['title' => 'Hello', 'author_id' => $supervisor->id]);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $other->id, 'body' => 'one']);

        $bind = ['req' => $open->id, 'maintenanceRequest' => $done->id, 'asset' => $asset->id, 'thread' => $thread->id, 'user' => $other->id];
        $seen = [];

        foreach ([$member, $technician] as $viewer) {
            foreach (Route::getRoutes() as $route) {
                $uri = $route->uri();
                if (! in_array('GET', $route->methods(), true) || ! str_contains((string) $route->getActionName(), 'App\\Http\\Controllers')
                    || preg_match('#^(_|sanctum|storage|broadcasting|attachments)#', $uri)) {
                    continue;
                }
                $path = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $bind[$m[1]] ?? 1, $uri);

                $this->app['auth']->forgetGuards();
                Sanctum::actingAs($viewer);
                $this->actingAs($viewer);
                $body = (string) (str_starts_with($uri, 'api/') ? $this->getJson($path) : $this->get($path))->getContent();

                foreach ($everyone as $person) {
                    if ($person->isNot($viewer) && str_contains($body, $person->citizen_id)) {
                        $seen[] = "{$viewer->role} sees {$person->role}'s citizen ID on GET {$path}";
                    }
                }
            }
        }

        $this->assertSame([], $seen);
    }
}
