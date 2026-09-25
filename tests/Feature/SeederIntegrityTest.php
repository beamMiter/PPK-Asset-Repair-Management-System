<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ChatThread;
use App\Models\Department;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The demo data is only useful if it is believable, so every relationship in it is checked: the request timeline against
 * its status, the team against the request, the log against the allowed transitions, ratings against who may rate, assets
 * against their open requests, SLA dates against the request type — and that the situations the screens have to handle
 * (breached SLA, on hold, unrated, disposed asset, suspended technician …) really are in there.
 */
class SeederIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_LEVEL = ['pending' => 0, 'acknowledged' => 1, 'accepted' => 2, 'in_progress' => 3, 'on_hold' => 3, 'resolved' => 4, 'closed' => 5];

    public function test_people_and_reference_data(): void
    {
        $this->seed();
        $problems = [];

        $roles = Role::pluck('code')->all();
        $departments = Department::pluck('code')->all();

        foreach (UserSeeder::ROSTER as $key => $person) {
            $user = User::where('citizen_id', $person['cid'])->first();
            if (! $user) {
                $problems[] = "$key is missing";
                continue;
            }
            in_array($user->role, $roles, true) || $problems[] = "$key has an unknown role {$user->role}";
            in_array($user->department, $departments, true) || $problems[] = "$key has an unknown department {$user->department}";
            Hash::check($person['password'] ?? UserSeeder::DEFAULT_PASSWORD, $user->password) || $problems[] = "$key: the documented password does not work";
            strlen($person['cid']) === 13 && ctype_digit($person['cid']) || $problems[] = "$key: citizen id is not 13 digits";
        }

        $you = User::where('citizen_id', '1234567890123')->firstOrFail();
        $this->assertSame('admin', $you->role, 'the developer account is an admin');
        $this->assertTrue(Hash::check('Dev12345!', $you->password));
        $this->assertSame(1, User::whereNotNull('suspended_at')->count(), 'exactly one suspended account');
        $this->assertGreaterThanOrEqual(1, User::whereNull('email')->count(), 'a member without an e-mail');

        foreach (MaintenanceRequestType::all() as $type) {
            in_array($type->default_role_code, $roles, true) || $problems[] = "type {$type->name}: default role {$type->default_role_code} is not a role";
            in_array($type->default_department_code, $departments, true) || $problems[] = "type {$type->name}: unknown default department";
        }
        $networkDefault = MaintenanceRequestType::where('name', 'Network')->firstOrFail()->defaultUser;
        $this->assertSame('network', $networkDefault?->role);
        $this->assertNull($networkDefault->suspended_at, 'the type\'s default person is active');
        $this->assertSame(1, MaintenanceRequestType::where('is_active', false)->count(), 'one type that has been switched off');

        $this->assertSame([], $problems);
    }

    public function test_every_request_is_consistent_with_its_status_team_log_and_rating(): void
    {
        $this->seed();
        $now = now();
        $problems = [];
        $bad = function (MaintenanceRequest $r, string $what) use (&$problems) {
            $problems[] = "#{$r->request_no} ({$r->status}) {$r->title}: $what";
        };

        $labels = MaintenanceRequest::statusLabels();
        $teamRoles = User::teamRoles();
        $suspended = User::whereNotNull('suspended_at')->pluck('id')->all();
        $chain = ['request_date', 'acknowledged_at', 'accepted_at', 'started_at', 'resolved_at', 'closed_at'];

        foreach (MaintenanceRequest::with(['assignments.user', 'logs', 'operationLog', 'rating', 'type', 'asset', 'reporter'])->orderBy('id')->get() as $r) {
            // ---- who and what it is about
            $r->reporter?->name === $r->reporter_name || $bad($r, 'reporter_name differs from the reporter');
            $r->department_id && Department::whereKey($r->department_id)->exists() || $bad($r, 'no valid department');
            if ($r->asset && (int) $r->department_id !== (int) $r->asset->department_id) {
                $bad($r, 'department differs from the asset\'s');
            }
            array_key_exists($r->status, $labels) || $bad($r, 'unknown status');
            preg_match('/^\d{2}10\d{5}$/', $r->request_no) || $bad($r, 'request number format');
            substr($r->request_no, 0, 2) === sprintf('%02d', ($r->request_date->year + 543) % 100) || $bad($r, 'request number year differs from the request date');

            // ---- timeline: stages present exactly as far as the status got, in order, none in the future
            $stage = in_array($r->status, ['cancelled', 'rejected'], true) ? null : self::STATUS_LEVEL[$r->status];
            $previous = null;
            foreach ($chain as $i => $column) {
                $value = $r->$column;
                if ($value && $value->gt($now)) {
                    $bad($r, "$column is in the future");
                }
                if ($value && $previous && $value->lt($previous)) {
                    $bad($r, "$column is earlier than the stage before it");
                }
                $previous = $value ?: $previous;
                if ($stage !== null) {
                    $expected = $i <= $stage;
                    if ($column === 'request_date' || $expected === (bool) $value) {
                        continue;
                    }
                    $bad($r, $expected ? "$column is missing" : "$column is set but the request has not got that far");
                }
            }
            if ($stage === null && ($r->resolved_at || $r->closed_at)) {
                $bad($r, 'a cancelled / rejected request was never resolved');
            }
            if ($r->status === 'rejected' && $r->accepted_at) {
                $bad($r, 'a rejected request was never accepted');
            }
            (bool) $r->on_hold_at === ($r->status === 'on_hold') || $bad($r, 'on_hold_at must be set exactly while on hold');
            (bool) $r->completed_date === ($r->status === 'closed') || $bad($r, 'completed_date is set exactly when closed');
            $r->status_updated_at && $previous && $r->status_updated_at->lt($previous) && $bad($r, 'status_updated_at is older than the last stage');

            // ---- SLA due dates come from the type (+ time spent on hold)
            $minutes = $r->type?->default_response_minutes;
            $resolution = $r->type?->default_resolution_minutes;
            $expectedResponse = $minutes ? $r->request_date->copy()->addMinutes($minutes) : null;
            $expectedSla = $resolution ? $r->request_date->copy()->addMinutes($resolution + $r->paused_duration_minutes) : null;
            ($r->response_due_date?->timestamp === $expectedResponse?->timestamp) || $bad($r, 'response_due_date does not follow the type');
            ($r->sla_due_date?->timestamp === $expectedSla?->timestamp) || $bad($r, 'sla_due_date does not follow the type');

            // ---- team
            $active = $r->assignments->where('status', '!=', 'cancelled');
            $lead = $r->assignments->where('is_lead', true)->first();
            foreach ($r->assignments as $a) {
                in_array($a->user->role, $teamRoles, true) || $bad($r, "assignee {$a->user->name} is not a team role");
                $a->role === $a->user->role || $bad($r, 'assignment role differs from the user\'s role');
                if ($a->response_status === 'rejected') {
                    ($a->status === 'cancelled' && filled($a->remark) && ! $a->is_lead) || $bad($r, 'a declined assignment must be cancelled, with a reason, and not lead');
                }
            }
            if ($active->isNotEmpty() || in_array($r->status, ['cancelled'], true) && $r->assignments->isNotEmpty()) {
                $r->assignments->where('is_lead', true)->count() === 1 || $bad($r, 'exactly one lead expected');
            }
            if (in_array($r->status, ['pending', 'rejected'], true) && $r->assignments->isNotEmpty()) {
                $bad($r, 'no team expected');
            }
            $expectedAssignmentStatus = match (true) {
                in_array($r->status, ['resolved', 'closed'], true) => 'done',
                in_array($r->status, ['cancelled', 'rejected'], true) => 'cancelled',
                default => 'in_progress',
            };
            foreach ($r->assignments->where('response_status', '!=', 'rejected') as $a) {
                $a->status === $expectedAssignmentStatus || $bad($r, "assignment of {$a->user->name} is {$a->status}, expected $expectedAssignmentStatus");
                $a->response_status === ($r->accepted_at ? 'accepted' : 'pending') || $bad($r, 'assignment response does not match whether the request was accepted');
            }
            if (in_array($r->status, MaintenanceRequest::OPEN_STATUSES, true)) {
                foreach ($active as $a) {
                    in_array($a->user_id, $suspended, true) && $bad($r, 'a suspended person is on an open request');
                }
            }
            if ($r->accepted_at) {
                ($lead && (int) $r->technician_id === (int) $lead->user_id) || $bad($r, 'technician_id is not the lead');
            } else {
                $r->technician_id === null || $bad($r, 'technician_id before the request was accepted');
            }

            // ---- log: creation entry, then exactly the allowed transitions, the last one landing on the status
            $logs = $r->logs->sortBy('id')->values();
            $first = $logs->first();
            ($first && $first->action === 'create_request' && (int) $first->user_id === (int) $r->reporter_id && $first->created_at->equalTo($r->request_date)) || $bad($r, 'the log must start with the reporter creating it at request_date');
            $transitions = $logs->where('action', 'transition')->values();
            $cursor = 'pending';
            foreach ($transitions as $t) {
                $t->from_status === $cursor || $bad($r, "log jumps: {$t->from_status} after $cursor");
                in_array($t->to_status, MaintenanceRequest::ALLOWED_TRANSITIONS[$cursor] ?? [], true) || $bad($r, "transition $cursor -> {$t->to_status} is not allowed");
                str_starts_with((string) $t->note, "[{$labels[$t->from_status]} -> {$labels[$t->to_status]}]") || $bad($r, 'log note does not start with the status labels');
                $cursor = $t->to_status;
            }
            $cursor === $r->status || $bad($r, "the log ends on $cursor");
            $logs->pluck('created_at')->every(fn ($at, $i) => $i === 0 || ! $at->lt($logs[$i - 1]->created_at)) || $bad($r, 'log times go backwards');
            if ($transitions->isNotEmpty()) {
                (int) $transitions->last()->user_id === (int) $r->status_updated_by || $bad($r, 'status_updated_by is not the last actor');
                $transitions->last()->created_at->equalTo($r->status_updated_at) || $bad($r, 'status_updated_at is not the last transition');
            }
            foreach ($transitions as $t) {
                $actor = $t->user;
                $ok = match ($t->to_status) {
                    'accepted', 'in_progress', 'resolved' => $lead && (int) $t->user_id === (int) $lead->user_id,
                    'on_hold' => $lead && (int) $t->user_id === (int) $lead->user_id,
                    'closed' => in_array($actor->role, ['admin', 'supervisor'], true),
                    'cancelled' => (int) $t->user_id === (int) $r->reporter_id || in_array($actor->role, ['admin', 'supervisor'], true),
                    default => in_array($actor->role, ['admin', 'supervisor'], true),
                };
                $ok || $bad($r, "{$t->to_status} was done by {$actor->name} ({$actor->role})");
            }

            // ---- the lead's operation report exists from the moment work starts
            $worked = in_array($r->status, ['in_progress', 'on_hold', 'resolved', 'closed'], true);
            $notWorked = in_array($r->status, ['pending', 'acknowledged', 'accepted', 'rejected'], true);
            if (($worked && ! $r->operationLog) || ($notWorked && $r->operationLog)) {
                $bad($r, $worked ? 'no operation report' : 'operation report before work started');
            }
            if ($r->operationLog) {
                (int) $r->operationLog->user_id === (int) $lead?->user_id || $bad($r, 'operation report is not by the lead');
                (bool) $r->operationLog->issue_software === ($r->type?->name === 'Software') || $bad($r, 'issue_software does not follow the type');
                $r->operationLog->property_code === $r->asset?->asset_code || $bad($r, 'operation report property_code differs from the asset');
            }

            // ---- outcome fields and the rating
            $done = in_array($r->status, ['resolved', 'closed'], true);
            ($done === filled($r->resolution_note)) || $bad($r, 'resolution_note is present exactly when resolved / closed');
            ($done || $r->cost === null) || $bad($r, 'cost on an unfinished request');
            if ($r->rating) {
                $r->status === 'closed' || $bad($r, 'rated but not closed');
                (int) $r->rating->rater_id === (int) $r->reporter_id || $bad($r, 'rated by someone other than the reporter');
                (int) $r->rating->technician_id === (int) $lead?->user_id || $bad($r, 'rating is not for the lead');
                ($r->rating->score >= 1 && $r->rating->score <= 5) || $bad($r, 'score out of range');
                ($r->rating->score > 2 || filled($r->rating->comment)) || $bad($r, 'a 1-2 star rating needs a comment');
                ($r->rating->created_at->gte($r->closed_at) && $r->rating->created_at->lte($now)) || $bad($r, 'rated before it was closed, or in the future');
            }
        }

        // request numbers: unique, and running 1..n inside each year in date order
        $numbers = MaintenanceRequest::orderBy('id')->pluck('request_no');
        $this->assertSame($numbers->unique()->count(), $numbers->count(), 'request numbers are unique');
        foreach ($numbers->groupBy(fn ($n) => substr($n, 0, 2)) as $year => $inYear) {
            $this->assertSame(range(1, $inYear->count()), $inYear->map(fn ($n) => (int) substr($n, -5))->values()->all(), "numbers in year $year run 1..n in order");
        }

        $this->assertSame([], $problems);
    }

    public function test_assets_follow_their_open_requests_and_the_chat_hangs_together(): void
    {
        $this->seed();
        $problems = [];

        foreach (Asset::with('department', 'categoryRef')->get() as $asset) {
            $open = MaintenanceRequest::where('asset_id', $asset->id)->whereIn('status', MaintenanceRequest::OPEN_STATUSES)->exists();
            match ($asset->status) {
                Asset::STATUS_IN_REPAIR => $open || $problems[] = "{$asset->asset_code} is in repair with no open request",
                Asset::STATUS_ACTIVE => ! $open || $problems[] = "{$asset->asset_code} is active but has an open request",
                Asset::STATUS_DISPOSED => ! $open || $problems[] = "{$asset->asset_code} is disposed but has an open request",
            };
            $asset->department && $asset->categoryRef || $problems[] = "{$asset->asset_code} lacks a department or category";
            $asset->warranty_expire->gte($asset->warranty_start) || $problems[] = "{$asset->asset_code} warranty ends before it starts";
            (bool) $asset->his_synced_at === (bool) $asset->his_asset_id || $problems[] = "{$asset->asset_code}: his_synced_at without a HIS id (or the reverse)";
        }

        foreach (ChatThread::with('messages')->get() as $thread) {
            $thread->messages->isNotEmpty() || $problems[] = "thread {$thread->title} has no message";
            $thread->messages->every(fn ($m) => $m->created_at->gte($thread->created_at)) || $problems[] = "thread {$thread->title}: a message before the thread";
            $thread->updated_at->equalTo($thread->messages->max('created_at')) || $problems[] = "thread {$thread->title}: updated_at is not the last message";
        }
        foreach (DB::table('chat_thread_reads')->get() as $read) {
            DB::table('chat_messages')->where('id', $read->last_read_message_id)->where('chat_thread_id', $read->chat_thread_id)->exists() || $problems[] = 'a read marker points at a message of another thread';
        }
        $this->assertTrue(ChatThread::where('is_locked', true)->exists(), 'a locked announcement thread');
        $unread = DB::table('chat_thread_reads as r')->join('chat_messages as m', 'm.chat_thread_id', '=', 'r.chat_thread_id')->whereColumn('m.id', '>', 'r.last_read_message_id')->exists();
        $this->assertTrue($unread, 'somebody has an unread thread');

        $this->assertSame([], $problems);
    }

    /** The situations the screens have to cope with must really be in the data. */
    public function test_the_data_covers_the_situations_the_screens_handle(): void
    {
        $this->seed();
        $now = now();
        $requests = MaintenanceRequest::with('assignments', 'rating', 'type')->get();
        $open = fn ($r) => in_array($r->status, MaintenanceRequest::OPEN_STATUSES, true);
        $you = User::where('citizen_id', '1234567890123')->firstOrFail();
        $gone = User::whereNotNull('suspended_at')->firstOrFail();

        $this->assertEqualsCanonicalizing(array_keys(MaintenanceRequest::statusLabels()), array_merge($requests->pluck('status')->unique()->all(), ['completed']), 'every status');

        $covered = [
            'response SLA breached (pending, unacknowledged)' => $requests->contains(fn ($r) => $r->status === 'pending' && $r->response_due_date?->lt($now)),
            'resolution SLA breached, still open' => $requests->contains(fn ($r) => in_array($r->status, ['accepted', 'in_progress'], true) && $r->sla_due_date?->lt($now)),
            'resolution SLA about to breach (< 4 h)' => $requests->contains(fn ($r) => $r->status === 'in_progress' && $r->sla_due_date?->between($now, $now->copy()->addHours(4))),
            'finished after its SLA date' => $requests->contains(fn ($r) => $r->status === 'closed' && $r->sla_due_date && $r->resolved_at->gt($r->sla_due_date)),
            'finished within its SLA date' => $requests->contains(fn ($r) => $r->status === 'closed' && $r->sla_due_date && $r->resolved_at->lte($r->sla_due_date)),
            'on hold now (2+)' => $requests->where('status', 'on_hold')->count() >= 2,
            'resumed after a hold (paused minutes)' => $requests->contains(fn ($r) => $r->status === 'in_progress' && $r->paused_duration_minutes > 0),
            'team of three or more' => $requests->contains(fn ($r) => $r->assignments->where('status', '!=', 'cancelled')->count() >= 3),
            'handed over (someone declined)' => $requests->contains(fn ($r) => $r->assignments->contains('response_status', 'rejected')),
            'assigned but not yet accepted' => $requests->contains(fn ($r) => $r->status === 'acknowledged' && $r->assignments->isNotEmpty()),
            'cancelled at pending / accepted / in progress' => $requests->where('status', 'cancelled')->map(fn ($r) => $r->accepted_at ? ($r->started_at ? 'in_progress' : 'accepted') : 'pending')->unique()->count() >= 3,
            'the admin has finished requests waiting to be rated (3+)' => $requests->where('reporter_id', $you->id)->filter(fn ($r) => $r->status === 'closed' && ! $r->rating && $r->closed_at->gt($now->copy()->subDays(30)))->count() >= 3,
            'rating window passed without a rating' => $requests->contains(fn ($r) => $r->status === 'closed' && ! $r->rating && $r->closed_at->lt($now->copy()->subDays(30))),
            'every star rating 1..5' => $requests->pluck('rating.score')->filter()->unique()->sort()->values()->all() === [1, 2, 3, 4, 5],
            'a rating without a comment' => $requests->contains(fn ($r) => $r->rating && blank($r->rating->comment)),
            // a technician's rating page lists their latest COMMENTS with who wrote them, and draws a six-month trend
            'every technician has 3+ comments to show' => collect(['it1', 'it2', 'net', 'dev', 'tech1', 'tech2', 'gone'])->every(
                fn ($key) => MaintenanceRating::where('technician_id', UserSeeder::find($key)->id)->whereNotNull('comment')->where('comment', '!=', '')->count() >= 3
            ),
            'the comments come from 10+ different members' => MaintenanceRating::whereNotNull('comment')->where('comment', '!=', '')
                ->whereIn('rater_id', User::where('role', 'member')->pluck('id'))->distinct()->count('rater_id') >= 10,
            'ratings in each of the last six months' => collect(range(0, 5))->every(fn ($ago) => MaintenanceRating::whereBetween('created_at', [
                $now->copy()->subMonths($ago)->startOfMonth(), $now->copy()->subMonths($ago)->endOfMonth(),
            ])->exists()),
            'last year (5+)' => $requests->filter(fn ($r) => $r->request_date->year === $now->year - 1)->count() >= 5,
            'a request on a disposed asset' => $requests->contains(fn ($r) => $r->asset_id && Asset::find($r->asset_id)->status === Asset::STATUS_DISPOSED),
            'no asset' => $requests->contains(fn ($r) => $r->asset_id === null),
            'no type' => $requests->contains(fn ($r) => $r->type_id === null),
            'a type that is switched off' => $requests->contains(fn ($r) => $r->type && ! $r->type->is_active),
            'a suspended technician\'s finished work' => $requests->contains(fn ($r) => $r->status === 'closed' && $r->assignments->contains('user_id', $gone->id)),
            'open work is spread over several people' => $requests->filter($open)->flatMap->assignments->where('status', '!=', 'cancelled')->pluck('user_id')->unique()->count() >= 5,
            'a request from a member with no e-mail' => $requests->contains(fn ($r) => $r->reporter_email === null),
            'a request raised by staff' => $requests->contains(fn ($r) => in_array($r->reporter?->role, ['supervisor', 'admin'], true)),
        ];

        $missing = array_keys(array_filter($covered, fn ($ok) => ! $ok));
        $this->assertSame([], $missing, 'these situations are not in the seeded data');

        $assets = Asset::all();
        $this->assertTrue($assets->contains(fn ($a) => $a->warranty_expire->between($now, $now->copy()->addDays(30))), 'a warranty about to expire');
        $this->assertTrue($assets->contains(fn ($a) => $a->warranty_expire->lt($now)), 'an expired warranty');
        $this->assertTrue($assets->contains(fn ($a) => $a->his_asset_id) && $assets->contains(fn ($a) => ! $a->his_asset_id), 'HIS-linked and not');
        $this->assertTrue($assets->contains(fn ($a) => $a->status === Asset::STATUS_ACTIVE && ! $requests->contains('asset_id', $a->id)), 'an asset that was never repaired');
    }

    public function test_the_data_stays_small_and_quick_to_seed(): void
    {
        $start = microtime(true);
        $this->seed();
        $seconds = microtime(true) - $start;

        $this->assertLessThanOrEqual(20, User::count());
        $this->assertLessThanOrEqual(45, Asset::count());
        $this->assertGreaterThanOrEqual(50, MaintenanceRequest::count());
        $this->assertLessThanOrEqual(100, MaintenanceRequest::count());
        $this->assertLessThan(10, $seconds, 'the whole demo seeds in a few seconds');
    }

    public function test_production_gets_only_the_reference_data(): void
    {
        $this->app['env'] = 'production';

        app(DatabaseSeeder::class)->setContainer($this->app)->run();

        $this->assertGreaterThan(0, Role::count());
        $this->assertGreaterThan(0, Department::count());
        $this->assertGreaterThan(0, MaintenanceRequestType::count());
        $this->assertSame(0, User::count(), 'no demo accounts with well-known passwords in production');
        $this->assertSame(0, Asset::count());
        $this->assertSame(0, MaintenanceRequest::count());
        $this->assertSame(0, MaintenanceAssignment::count());
    }
}
