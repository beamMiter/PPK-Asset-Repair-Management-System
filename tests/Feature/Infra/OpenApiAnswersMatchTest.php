<?php

namespace Tests\Feature\Infra;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * openapi.yaml says what each API call answers. OpenApiMatchesRoutesTest keeps the list of calls honest; this keeps what they ANSWER
 * honest: each call below is made with real records, and the answer is held against the schema the document gives for that status.
 *
 * What it holds the answer to: the status is one the document lists for that call; every key of the answer is a key the schema names
 * (a field added to a model and forgotten here fails); and a value has the type the schema says, null only where the schema allows it.
 * What it does not do: ask that every documented key is present (a create answer carries fewer of the empty fields than a list row).
 * Found by reading the old document against the answers: `priority` was documented as a required field of a request that has no
 * priority at all, and the answers of most calls were not described.
 */
class OpenApiAnswersMatchTest extends TestCase
{
    use RefreshDatabase;

    private array $spec;

    private User $admin;

    private User $member;

    private User $tech;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('public');
        Storage::fake('local');
        $this->spec = Yaml::parseFile(base_path('openapi.yaml'));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->member = User::factory()->create(['role' => 'member']);
        $this->tech = User::factory()->create(['role' => 'technician']);
    }

    // ── the check ───────────────────────────────────────────────────────────────────────────────────────────────────

    private function ref(array $schema): array
    {
        while (isset($schema['$ref'])) {
            $node = $this->spec;
            foreach (explode('/', ltrim($schema['$ref'], '#/')) as $part) {
                $node = $node[$part] ?? $this->fail("{$schema['$ref']} does not resolve");
            }
            $schema = $node;
        }

        return $schema;
    }

    /** allOf merged into one schema; oneOf narrowed to the branch that fits the value. */
    private function flatten(array $schema, mixed $value): array
    {
        $schema = $this->ref($schema);
        foreach ($schema['allOf'] ?? [] as $part) {
            $part = $this->flatten($part, $value);
            $schema['properties'] = ($schema['properties'] ?? []) + ($part['properties'] ?? []);
            $schema['type'] ??= $part['type'] ?? null;
            if (isset($part['additionalProperties'])) {
                $schema['additionalProperties'] = $part['additionalProperties'];
            }
        }
        if (isset($schema['oneOf'])) {
            $branches = array_map(fn ($b) => $this->ref($b), $schema['oneOf']);
            $isNull = fn ($b) => ($b['type'] ?? null) === 'null';
            $schema = $value === null
                ? (array_filter($branches, $isNull) ? ['type' => 'null'] : $branches[0])
                : $this->flatten(array_values(array_filter($branches, fn ($b) => ! $isNull($b)))[0], $value);
        }

        return $schema;
    }

    private function problems(mixed $value, array $schema, string $at): array
    {
        $schema = $this->flatten($schema, $value);
        $type = $schema['type'] ?? null;

        if ($value === null) {
            return ($type === 'null' || ($schema['nullable'] ?? false) || $type === null) ? [] : ["$at is null, the document says $type"];
        }

        $ok = match ($type) {
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'string' => is_string($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            default => true,   // no type: anything
        };
        if (! $ok) {
            return ["$at is ".get_debug_type($value).", the document says $type"];
        }

        $out = [];
        if ($type === 'array') {
            foreach (array_slice($value, 0, 3) as $i => $item) {
                $out = array_merge($out, $this->problems($item, $schema['items'] ?? [], "{$at}[$i]"));
            }
        }
        if ($type === 'object' && $value !== []) {
            $props = $schema['properties'] ?? [];
            $extra = $schema['additionalProperties'] ?? null;
            foreach ($value as $key => $item) {
                if (isset($props[$key])) {
                    $out = array_merge($out, $this->problems($item, $props[$key], "$at.$key"));
                } elseif (is_array($extra)) {
                    $out = array_merge($out, $this->problems($item, $extra, "$at.$key"));
                } elseif ($props !== [] || $extra === false) {
                    $out[] = "$at.$key is in the answer and not in the document";
                }
            }
        }

        return $out;
    }

    /** every mismatch found so far in this test: it is asserted once at the end, so one run lists them all */
    private array $drift = [];

    /** @param string $path as the document writes it, e.g. /repair-requests/{id} */
    private function answers($response, string $method, string $path): void
    {
        $label = strtoupper($method)." $path ".$response->status();
        $declared = $this->spec['paths'][$path][$method]['responses'][(string) $response->status()] ?? null;
        if (! isset($this->spec['paths'][$path][$method])) {
            $this->drift[] = "$label: the document has no such operation";

            return;
        }
        if ($declared === null) {
            $this->drift[] = "$label: the document does not list this status";

            return;
        }

        $schema = $declared['content']['application/json']['schema'] ?? null;
        if ($schema === null) {
            return;
        }
        $json = json_decode($response->getContent(), true);
        if ($json === null) {
            $this->drift[] = "$label: the answer is not JSON";

            return;
        }
        foreach ($this->problems($json, $schema, '$') as $problem) {
            $this->drift[] = "$label  $problem";
        }
    }

    private function assertTheDocumentMatches(): void
    {
        $this->assertSame('', implode("\n", array_unique($this->drift)), 'the answers are not what openapi.yaml says');
    }

    // ── the calls ───────────────────────────────────────────────────────────────────────────────────────────────────

    private function h(): array
    {
        return ['Accept' => 'application/json'];
    }

    public function test_the_sign_in_and_account_calls(): void
    {
        $h = $this->h();
        $this->answers($this->postJson('/api/auth/login', ['citizen_id' => $this->member->citizen_id, 'password' => 'password'], $h), 'post', '/auth/login');
        $this->answers($this->postJson('/api/auth/login', ['citizen_id' => $this->member->citizen_id, 'password' => 'wrong'], $h), 'post', '/auth/login');
        $this->answers($this->postJson('/api/auth/login', [], $h), 'post', '/auth/login');
        $suspended = User::factory()->create(['suspended_at' => now()]);
        $this->answers($this->postJson('/api/auth/login', ['citizen_id' => $suspended->citizen_id, 'password' => 'password'], $h), 'post', '/auth/login');

        Sanctum::actingAs($this->member);
        $this->answers($this->getJson('/api/auth/me', $h), 'get', '/auth/me');
        $this->answers($this->getJson('/api/user', $h), 'get', '/user');
        $this->answers($this->getJson('/api/auth/tokens', $h), 'get', '/auth/tokens');
        $token = $this->member->createToken('another');
        $this->answers($this->deleteJson('/api/auth/tokens/'.$token->accessToken->id, [], $h), 'delete', '/auth/tokens/{id}');
        $this->answers($this->deleteJson('/api/auth/tokens/999999', [], $h), 'delete', '/auth/tokens/{id}');
        $this->answers($this->putJson('/api/auth/password', ['current_password' => 'wrong', 'password' => 'x', 'password_confirmation' => 'y'], $h), 'put', '/auth/password');
        $this->answers($this->putJson('/api/auth/password', ['current_password' => 'password', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'], $h), 'put', '/auth/password');
        $this->answers($this->postJson('/api/auth/logout-all', [], $h), 'post', '/auth/logout-all');
        $this->answers($this->postJson('/api/auth/logout', [], $h), 'post', '/auth/logout');
        $this->answers($this->postJson('/api/auth/password/email', ['email' => 'nobody@example.com'], $h), 'post', '/auth/password/email');
        $this->answers($this->postJson('/api/auth/password/email', ['email' => 'not-an-address'], $h), 'post', '/auth/password/email');
        $this->answers($this->postJson('/api/auth/password/reset', ['email' => 'nobody@example.com', 'token' => 'x', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'], $h), 'post', '/auth/password/reset');
        $this->answers($this->getJson('/api/health', $h), 'get', '/health');
        $this->assertTheDocumentMatches();
    }

    public function test_the_asset_calls(): void
    {
        $h = $this->h();
        $dept = Department::factory()->create();
        $cat = AssetCategory::factory()->create();

        Sanctum::actingAs($this->admin);
        $this->answers($this->postJson('/api/assets', ['asset_code' => 'A-1', 'name' => 'เครื่องพิมพ์', 'department_id' => $dept->id, 'category_id' => $cat->id, 'price' => 1500.5], $h), 'post', '/assets');
        $this->answers($this->postJson('/api/assets', [], $h), 'post', '/assets');
        $asset = Asset::where('asset_code', 'A-1')->firstOrFail();
        $this->answers($this->getJson('/api/assets?per_page=5&status=active&sort_by=name&sort_dir=asc', $h), 'get', '/assets');
        $this->answers($this->getJson("/api/assets/{$asset->id}", $h), 'get', '/assets/{id}');
        $this->answers($this->getJson('/api/assets/999999', $h), 'get', '/assets/{id}');
        $this->answers($this->putJson("/api/assets/{$asset->id}", ['name' => 'ชื่อใหม่'], $h), 'put', '/assets/{id}');
        $this->answers($this->patchJson("/api/assets/{$asset->id}", ['status' => 'in_repair'], $h), 'patch', '/assets/{id}');
        $this->answers($this->putJson("/api/assets/{$asset->id}", ['status' => 'nonsense'], $h), 'put', '/assets/{id}');

        $busy = Asset::factory()->create(['status' => 'active']);
        MR::factory()->create(['asset_id' => $busy->id, 'status' => 'pending']);
        $this->answers($this->deleteJson("/api/assets/{$busy->id}", [], $h), 'delete', '/assets/{id}');
        $free = Asset::factory()->create();
        $this->answers($this->deleteJson("/api/assets/{$free->id}", [], $h), 'delete', '/assets/{id}');

        Sanctum::actingAs($this->member);
        $this->answers($this->postJson('/api/assets', ['asset_code' => 'A-2', 'name' => 'x'], $h), 'post', '/assets');
        $this->answers($this->putJson("/api/assets/{$asset->id}", ['name' => 'x'], $h), 'put', '/assets/{id}');
        $this->answers($this->deleteJson("/api/assets/{$asset->id}", [], $h), 'delete', '/assets/{id}');
        $this->assertTheDocumentMatches();
    }

    public function test_the_request_calls(): void
    {
        $h = $this->h();
        $asset = Asset::factory()->create(['status' => 'active']);   // the factory picks a status at random, and a repair cannot be opened on a disposed asset

        Sanctum::actingAs($this->member);
        $made = $this->post('/api/repair-requests', ['title' => 'มีรูป', 'description' => 'จอดับ', 'asset_id' => $asset->id, 'files' => [UploadedFile::fake()->image('a.jpg', 100, 100)], 'captions' => ['ภาพจอ']], $h);
        $this->answers($made, 'post', '/repair-requests');
        $this->answers($this->post('/api/repair-requests', [], $h), 'post', '/repair-requests');
        $made->assertCreated();
        $id = $made->json('data.id');

        MaintenanceAssignment::create(['maintenance_request_id' => $id, 'user_id' => $this->tech->id, 'role' => 'technician', 'is_lead' => true, 'status' => 'in_progress']);
        $this->answers($this->getJson('/api/repair-requests?status=pending&q=มี&sort_by=id&sort_dir=asc', $h), 'get', '/repair-requests');
        $this->answers($this->getJson("/api/repair-requests/{$id}", $h), 'get', '/repair-requests/{id}');
        $this->answers($this->getJson('/api/repair-requests/999999', $h), 'get', '/repair-requests/{id}');
        $this->answers($this->put("/api/repair-requests/{$id}", ['title' => 'แก้หัวข้อ'], $h), 'put', '/repair-requests/{id}');
        $this->answers($this->put("/api/repair-requests/{$id}", ['status' => 'closed'], $h), 'put', '/repair-requests/{id}');
        $this->answers($this->postJson("/api/repair-requests/{$id}/transition", ['status' => 'accepted'], $h), 'post', '/repair-requests/{id}/transition');
        $stranger = MR::factory()->create(['status' => 'pending']);
        $this->answers($this->getJson("/api/repair-requests/{$stranger->id}", $h), 'get', '/repair-requests/{id}');

        Sanctum::actingAs($this->admin);
        $this->answers($this->postJson("/api/repair-requests/{$id}/transition", ['status' => 'acknowledged', 'note' => 'รับทราบ'], $h), 'post', '/repair-requests/{id}/transition');
        $this->answers($this->postJson("/api/repair-requests/{$id}/transition", ['status' => 'closed'], $h), 'post', '/repair-requests/{id}/transition');
        $this->answers($this->postJson("/api/repair-requests/{$id}/transition", ['status' => 'nonsense'], $h), 'post', '/repair-requests/{id}/transition');
        $this->answers($this->putJson("/api/repair-requests/{$id}", ['status' => 'resolved'], $h), 'put', '/repair-requests/{id}');
        $this->answers($this->getJson("/api/repair-requests/{$id}/logs", $h), 'get', '/repair-requests/{id}/logs');

        // a finished, approved and rated one shows everything the detail can carry
        MR::whereKey($id)->update(['status' => 'closed', 'closed_at' => now(), 'technician_id' => $this->tech->id]);
        Sanctum::actingAs($this->member);
        $this->postJson("/api/repair-requests/{$id}/rating", ['score' => 4, 'comment' => 'ดี'], $h)->assertCreated();
        $this->answers($this->getJson("/api/repair-requests/{$id}", $h), 'get', '/repair-requests/{id}');
        $unrated = MR::factory()->create(['reporter_id' => $this->member->id, 'status' => 'closed', 'closed_at' => now()]);
        $this->answers($this->getJson('/api/repair-requests/pending/evaluations', $h), 'get', '/repair-requests/pending/evaluations');

        Sanctum::actingAs($this->tech);
        $this->answers($this->getJson('/api/repair-requests/my-jobs?status=all', $h), 'get', '/repair-requests/my-jobs');
        $this->assertTheDocumentMatches();
    }

    public function test_the_numbers_lists_and_search_calls(): void
    {
        $h = $this->h();
        $dept = Department::factory()->create();
        $asset = Asset::factory()->create(['status' => 'active', 'department_id' => $dept->id, 'category_id' => AssetCategory::factory()->create()->id]);
        MR::factory()->create(['reporter_id' => $this->member->id, 'asset_id' => $asset->id, 'status' => 'pending', 'title' => 'จอดับ']);

        Sanctum::actingAs($this->admin);
        foreach ([
            '/stats/summary' => '/api/stats/summary',
            '/stats/maintenance/status-counts' => '/api/stats/maintenance/status-counts',
            '/stats/maintenance/technicians' => '/api/stats/maintenance/technicians',
            '/stats/assets/by-department' => '/api/stats/assets/by-department',
            '/meta/departments' => '/api/meta/departments?q=a',
            '/meta/categories' => '/api/meta/categories',
            '/meta/users' => '/api/meta/users?role=technician',
            '/search/assets' => '/api/search/assets?q='.substr($asset->asset_code, 0, 3).'&limit=3',
            '/search/maintenance-requests' => '/api/search/maintenance-requests?q=จอ',
        ] as $path => $url) {
            $this->answers($this->getJson($url, $h), 'get', $path);
        }

        Sanctum::actingAs($this->member);
        $this->answers($this->getJson('/api/stats/maintenance/technicians', $h), 'get', '/stats/maintenance/technicians');
        $this->answers($this->getJson('/api/stats/summary', $h), 'get', '/stats/summary');
        $this->answers($this->getJson('/api/meta/users', $h), 'get', '/meta/users');
        $this->assertTheDocumentMatches();
    }

    public function test_the_chat_calls(): void
    {
        $h = $this->h();
        Sanctum::actingAs($this->member);

        $thread = $this->postJson('/api/threads', ['title' => 'ทดสอบ'], $h);
        $this->answers($thread, 'post', '/threads');
        $this->answers($this->postJson('/api/threads', [], $h), 'post', '/threads');
        $tid = $thread->json('id');

        $this->answers($this->getJson('/api/threads', $h), 'get', '/threads');
        $this->answers($this->getJson('/api/threads?scope=hidden', $h), 'get', '/threads');
        $this->answers($this->getJson("/api/threads/{$tid}", $h), 'get', '/threads/{id}');
        $this->answers($this->getJson('/api/threads/999999', $h), 'get', '/threads/{id}');

        $message = $this->postJson("/api/threads/{$tid}/messages", ['body' => 'สวัสดี'], $h);
        $this->answers($message, 'post', '/threads/{id}/messages');
        $this->answers($this->postJson("/api/threads/{$tid}/messages", [], $h), 'post', '/threads/{id}/messages');
        $mid = $message->json('id');
        $this->answers($this->getJson("/api/threads/{$tid}/messages", $h), 'get', '/threads/{id}/messages');
        $this->answers($this->patchJson("/api/threads/{$tid}/messages/{$mid}", ['body' => 'แก้ไข'], $h), 'patch', '/threads/{id}/messages/{message_id}');
        $this->answers($this->getJson('/api/chat/my-updates', $h), 'get', '/chat/my-updates');
        $this->answers($this->postJson("/api/threads/{$tid}/hide", [], $h), 'post', '/threads/{id}/hide');
        $this->answers($this->deleteJson("/api/threads/{$tid}/hide", [], $h), 'delete', '/threads/{id}/hide');
        $this->answers($this->deleteJson("/api/threads/{$tid}/messages/{$mid}", [], $h), 'delete', '/threads/{id}/messages/{message_id}');

        Sanctum::actingAs(User::factory()->create(['role' => 'it_support']));
        $this->answers($this->postJson("/api/threads/{$tid}/lock", [], $h), 'post', '/threads/{id}/lock');
        $this->answers($this->postJson("/api/threads/{$tid}/unlock", [], $h), 'post', '/threads/{id}/unlock');
        $this->assertTheDocumentMatches();
    }
}
