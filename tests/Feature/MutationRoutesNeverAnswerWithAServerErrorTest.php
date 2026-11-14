<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What GarbageQueryStringTest does for the pages that only read, this does for every route that changes something or takes an id:
 * a person may send anything, and the answer may be a refusal, a 404 or a validation error, but never a server error.
 *
 * Each route is sent an empty, a junk and a list-valued payload, as an admin, a member and a staff member, with an id that exists,
 * is text, belongs to a deleted row, or belongs to somebody else's row. A request never leaves anything behind: it runs in its own
 * transaction that is rolled back, and the person is loaded afresh. This is how a list sent as `current_password` (TypeError in the
 * hasher) and a text id on the maintenance-type pages (TypeError on an `int` argument) were found.
 */
class MutationRoutesNeverAnswerWithAServerErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_route_that_changes_something_answers_with_a_server_error(): void
    {
        $this->seed();
        Storage::fake('local');
        Storage::fake('public');
        $this->withoutMiddleware(ThrottleRequests::class);

        $by = fn (string $email) => User::where('email', $email)->firstOrFail()->id;
        $actors = ['admin' => $by('dev@example.com'), 'member' => $by('member1@example.com'), 'staff' => $by('it1@example.com')];
        $other = $by('member2@example.com');

        $ids = $this->aSetOfIds($actors['member'], $actors['staff'], $other);

        $keys = ['title', 'description', 'status', 'note', 'body', 'score', 'comment', 'user_ids', 'technician_id', 'asset_id', 'department_id',
            'type_id', 'name', 'email', 'role', 'password', 'password_confirmation', 'current_password', 'file_name', 'sound', 'citizen_id',
            'phone', 'request_date', 'location_text', 'reporter_name', 'reporter_phone', 'reporter_email', 'asset_code', 'serial_number',
            'brand', 'model', 'purchase_date', 'cost', 'captions', 'files', 'signature', 'from', 'to', 'sla_hours', 'items', 'ids', 'default_sla',
            'work_done', 'cause', 'parts', 'reason', 'department', 'avatar', 'is_locked', 'color', 'is_active'];
        $payloads = [
            'empty' => [],
            'junk' => array_combine($keys, array_map(fn ($k) => $k === 'role' ? 'superuser' : ($k === 'score' ? '99' : ($k === 'status' ? 'bogus' : "x'\"<script>")), $keys)),
            'lists' => array_combine($keys, array_map(fn () => ['a' => ['b']], $keys)),
        ];

        $failures = [];
        $sent = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (preg_match('#^(up$|storage|_|broadcasting|sanctum)#', $uri)) {
                continue;
            }
            $params = $route->parameterNames();
            $api = str_starts_with($uri, 'api/');

            foreach (array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS'])) as $method) {
                if ($method === 'GET' && ! $params) {
                    continue; // the pages that only read are GarbageQueryStringTest's
                }
                foreach ($params ? array_keys($ids) : ['-'] as $which) {
                    $url = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $ids[$which][$m[1]] ?? 999999, $uri);
                    foreach ($method === 'GET' ? ['empty'] : array_keys($payloads) as $kind) {
                        foreach ($actors as $who => $userId) {
                            $sent++;
                            if ($error = $this->serverError($userId, $api, $method, $url, $payloads[$kind])) {
                                $failures[] = "$method $uri  as $who  id: $which  payload: $kind  =>  $error";
                            }
                        }
                    }
                }
            }
        }

        $this->assertGreaterThan(1000, $sent, 'the sweep should have sent a good many requests');
        $this->assertSame([], $failures, count($failures)." request(s) answered with a server error:\n".implode("\n", array_slice($failures, 0, 20)));
    }

    /**
     * Rows to point routes at, by the kind of id: a real set (a job of the member that the staff member is on, and an asset, a
     * file, a chat thread and message), the same names as text, a deleted job / asset / thread / message, and somebody else's job
     * and file (and a message that is in another thread).
     *
     * @return array<string, array<string, int|string>>
     */
    private function aSetOfIds(int $member, int $staff, int $other): array
    {
        $asset = fn () => Asset::factory()->create(['asset_code' => 'MS-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null]);
        $assetA = $asset();
        $assetB = $asset();
        $assetGone = $asset();
        $assetGone->delete();

        // A starts pending so its reporter may still attach a file (they may not once a staff member is on it); it is moved on below
        $jobA = MaintenanceRequest::factory()->create(['reporter_id' => $member, 'asset_id' => $assetA->id, 'status' => 'pending', 'technician_id' => null]);
        $jobB = MaintenanceRequest::factory()->create(['reporter_id' => $other, 'asset_id' => $assetB->id, 'status' => 'pending', 'technician_id' => null]);
        $jobGone = MaintenanceRequest::factory()->create(['reporter_id' => $member, 'asset_id' => $assetA->id, 'status' => 'pending', 'technician_id' => null]);
        $jobGone->delete();

        $this->actingAs(User::find($member))->post(route('maintenance.requests.attachments', $jobA), ['files' => [UploadedFile::fake()->image('a.jpg', 11, 11)]]);
        $this->actingAs(User::find($other))->post(route('maintenance.requests.attachments', $jobB), ['files' => [UploadedFile::fake()->image('b.jpg', 12, 12)]]);
        $fileOf = fn (MaintenanceRequest $job) => Attachment::where('attachable_id', $job->id)->where('attachable_type', $job->getMorphClass())->firstOrFail();
        $fileA = $fileOf($jobA);
        $fileB = $fileOf($jobB);

        MaintenanceRequest::whereKey($jobA->id)->update(['status' => 'in_progress']);
        MaintenanceAssignment::create(['maintenance_request_id' => $jobA->id, 'user_id' => $staff, 'status' => 'in_progress', 'is_lead' => true]);

        $thread = ChatThread::orderBy('id')->firstOrFail();
        $message = ChatMessage::where('chat_thread_id', $thread->id)->orderBy('id')->firstOrFail();
        $messageElsewhere = ChatMessage::where('chat_thread_id', '!=', $thread->id)->orderBy('id')->firstOrFail();
        $threadGone = ChatThread::create(['title' => 'deleted thread', 'author_id' => $member]);
        $threadGone->delete();
        $messageGone = ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $member, 'body' => 'deleted message']);
        $messageGone->delete();

        $real = ['user' => $staff, 'asset' => $assetA->id, 'attachment' => $fileA->id, 'id' => \App\Models\MaintenanceRequestType::firstOrFail()->id,
            'maintenanceRequest' => $jobA->id, 'req' => $jobA->id, 'thread' => $thread->id, 'message' => $message->id, 'token' => 'x'];

        return [
            'a real one' => $real,
            'text' => array_map(fn () => 'abc', $real),
            'a deleted one' => array_merge($real, ['maintenanceRequest' => $jobGone->id, 'req' => $jobGone->id, 'asset' => $assetGone->id,
                'thread' => $threadGone->id, 'message' => $messageGone->id]),
            'somebody else\'s' => array_merge($real, ['maintenanceRequest' => $jobB->id, 'req' => $jobB->id, 'asset' => $assetB->id,
                'attachment' => $fileB->id, 'message' => $messageElsewhere->id]),
        ];
    }

    /** The error behind a 5xx answer, or null. The request is rolled back, so the next one starts from the same rows. */
    private function serverError(int $userId, bool $api, string $method, string $url, array $data): ?string
    {
        $send = function () use ($userId, $api, $method, $url, $data) {
            $this->app['auth']->forgetGuards();
            $this->app['auth']->shouldUse('web');
            $this->flushSession();
            $this->actingAs(User::findOrFail($userId), $api ? 'sanctum' : 'web');

            return $api
                ? $this->json($method, $url, $data)
                : $this->call($method, $url, $data, [], [], ['HTTP_ACCEPT' => 'text/html']);
        };

        DB::beginTransaction();
        try {
            $status = $send()->getStatusCode();
        } finally {
            DB::rollBack();
        }

        if ($status < 500) {
            return null;
        }

        // say WHY: the same request again with the handler out of the way
        DB::beginTransaction();
        $this->withoutExceptionHandling();
        try {
            $send();

            return "HTTP $status";
        } catch (\Throwable $e) {
            return class_basename($e).': '.mb_substr($e->getMessage(), 0, 160).' @ '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine();
        } finally {
            $this->withExceptionHandling();
            DB::rollBack();
        }
    }
}
