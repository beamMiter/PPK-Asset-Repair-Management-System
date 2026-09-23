<?php

namespace Tests\Feature;

use App\Http\Controllers\Settings\NotificationSettingController;
use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A text field the server limits (`max:255`) has to say so on the page: the browser then stops typing at the limit, where before a
 * longer text was sent, refused, and — on a page that prints no errors — the form came back with nothing said.
 *
 * The limit is not written down here: it is read from the server's own refusal ("… ไม่เกิน 255 ตัวอักษร") and compared with the
 * `maxlength` the page carries, so a rule changed on one side and forgotten on the other is what fails.
 */
class FormFieldLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MaintenanceRequest $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->job = MaintenanceRequest::factory()->create([
            'status' => MaintenanceRequest::STATUS_IN_PROGRESS,
            'reporter_id' => $this->admin->id,
            'asset_id' => Asset::factory()->create()->id,
        ]);
    }

    /** The longest value the SERVER takes for $field, from its refusal of a huge one. */
    private function serverLimit(string $method, string $route, array $params, string $field): int
    {
        $this->flushSession();
        $this->actingAs($this->admin)->from('/x')->{$method}(route($route, $params), [$field => str_repeat('ก', 20000)]);

        $message = session('errors')?->first($field) ?? '';
        $this->assertMatchesRegularExpression('/(\d+) ตัวอักษร/u', $message, "the server did not name a length limit for $field: “{$message}”");
        preg_match('/(\d+) ตัวอักษร/u', $message, $m);

        return (int) $m[1];
    }

    /** `maxlength` of every visible control called $field (or with id $field) on the page; the hidden inputs of a form do not count. */
    private function pageLimits(string $html, string $field): array
    {
        $limits = [];
        foreach ($this->controlTags($html) as $tag) {
            if (! preg_match('/\b(?:name|id)="' . preg_quote($field, '/') . '"/', $tag) || str_contains($tag, 'type="hidden"')) {
                continue;
            }
            $limits[] = preg_match('/\bmaxlength="(\d+)"/', $tag, $m) ? (int) $m[1] : null;
        }

        return $limits;
    }

    /**
     * The opening tag of every <input> and <textarea>. It ends at the first `>` outside quotes: an Alpine attribute such as
     * x-init="$watch('a', v => { … })" has a `>` inside it, which a plain [^>]* would take for the end of the tag.
     *
     * @return string[]
     */
    private function controlTags(string $html): array
    {
        $tags = [];
        preg_match_all('/<(?:input|textarea)\b/', $html, $starts, PREG_OFFSET_CAPTURE);
        foreach ($starts[0] as [, $at]) {
            $quote = null;
            for ($i = $at + 1, $n = strlen($html); $i < $n; $i++) {
                $c = $html[$i];
                if ($quote) {
                    $quote = $c === $quote ? null : $quote;
                } elseif ($c === '"' || $c === "'") {
                    $quote = $c;
                } elseif ($c === '>') {
                    $tags[] = substr($html, $at, $i - $at + 1);
                    break;
                }
            }
        }

        return $tags;
    }

    private function assertPageMatchesServer(string $html, string $field, int $serverLimit, string $where): void
    {
        $limits = $this->pageLimits($html, $field);

        $this->assertNotSame([], $limits, "$where: no control called $field on the page");
        foreach ($limits as $limit) {
            $this->assertSame($serverLimit, $limit, "$where: $field has maxlength " . ($limit ?? 'none') . " on the page, the server takes $serverLimit");
        }
    }

    public function test_the_request_form_says_how_much_each_field_takes(): void
    {
        $html = $this->actingAs($this->admin)->get(route('maintenance.requests.create'))->assertOk()->getContent();

        // (the reporter's name is a typed field only when a job is edited: on a new one it is the signed-in person)
        foreach (['title', 'description', 'location_text', 'reporter_phone'] as $field) {
            $this->assertPageMatchesServer($html, $field, $this->serverLimit('post', 'maintenance.requests.store', [], $field), 'request form');
        }
        $this->assertSame([255], $this->pageLimits($html, 'reporter_email'), 'an e-mail address: 255 (the rule checks it as an address first, so its length is not in the refusal)');
    }

    public function test_the_request_edit_page_and_the_job_page_say_it_too(): void
    {
        $edit = $this->actingAs($this->admin)->get(route('maintenance.requests.edit', $this->job))->assertOk()->getContent();
        foreach (['title', 'description', 'location_text', 'reporter_name', 'reporter_phone', 'property_code', 'remark'] as $field) {
            $this->assertPageMatchesServer($edit, $field, $this->serverLimit('put', 'maintenance.requests.update', ['req' => $this->job->id], $field), 'request edit');
        }

        // a job that is under way offers pause / resolve / cancel; one nobody has taken yet offers "not accepted" (reject) instead
        $show = $this->actingAs($this->admin)->get(route('maintenance.requests.show', $this->job))->assertOk()->getContent();
        $unaccepted = MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_PENDING, 'reporter_id' => $this->admin->id]);
        $showUnaccepted = $this->actingAs($this->admin)->get(route('maintenance.requests.show', $unaccepted))->assertOk()->getContent();

        $modals = [
            'note' => ['maintenance.requests.hold', 'the pause reason', $show, $this->job],
            'resolution_note' => ['maintenance.requests.resolve', 'the repair note', $show, $this->job],
            'cancel_reason' => ['maintenance.requests.cancel', 'the cancel reason', $show, $this->job],
            'reject_reason' => ['maintenance.requests.reject', 'the reject reason', $showUnaccepted, $unaccepted],
        ];
        foreach ($modals as $field => [$route, $what, $page, $job]) {
            $this->assertPageMatchesServer($page, $field, $this->serverLimit('post', $route, ['req' => $job->id], $field), $what);
        }
        $this->assertPageMatchesServer($show, 'remark', $this->serverLimit('post', 'maintenance.requests.operation-log', ['maintenanceRequest' => $this->job->id], 'remark'), 'operation log');
        $this->assertPageMatchesServer($show, 'property_code', $this->serverLimit('post', 'maintenance.requests.operation-log', ['maintenanceRequest' => $this->job->id], 'property_code'), 'operation log');
    }

    public function test_the_asset_form_says_how_much_each_field_takes(): void
    {
        $html = $this->actingAs($this->admin)->get(route('assets.create'))->assertOk()->getContent();

        foreach (['asset_code', 'name', 'type', 'brand', 'model', 'serial_number', 'location', 'his_asset_id', 'vendor_name', 'vendor_phone'] as $field) {
            $this->assertPageMatchesServer($html, $field, $this->serverLimit('post', 'assets.store', [], $field), 'asset form');
        }
    }

    public function test_the_people_and_settings_forms_say_it_too(): void
    {
        $users = $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk()->getContent();
        $this->assertPageMatchesServer($users, 'name', $this->serverLimit('post', 'admin.users.store', [], 'name'), 'user form');
        $this->assertContains(255, $this->pageLimits($users, 'email'), 'user form: e-mail');

        $profile = $this->actingAs($this->admin)->get(route('profile.edit'))->assertOk()->getContent();
        $this->assertPageMatchesServer($profile, 'name', $this->serverLimit('patch', 'profile.update', [], 'name'), 'profile');
        $this->assertContains(255, $this->pageLimits($profile, 'email'), 'profile: e-mail');

        $type = $this->actingAs($this->admin)->get(route('settings.maintenance-types.create'))->assertOk()->getContent();
        $this->assertPageMatchesServer($type, 'name', $this->serverLimit('post', 'settings.maintenance-types.store', [], 'name'), 'job type');

        $chat = $this->actingAs($this->admin)->get(route('chat.index'))->assertOk()->getContent();
        $this->assertPageMatchesServer($chat, 'modal-thread-title', $this->serverLimit('post', 'chat.store', [], 'title'), 'new chat thread');
    }

    public function test_the_sign_up_and_password_forms_say_it_too(): void
    {
        auth()->logout();
        $this->flushSession();

        $register = $this->get(route('register'))->assertOk()->getContent();
        $this->assertContains(255, $this->pageLimits($register, 'name'), 'sign-up: name');
        $this->assertContains(255, $this->pageLimits($register, 'email'), 'sign-up: e-mail');
        $this->assertContains(255, $this->pageLimits($this->get(route('password.request'))->getContent(), 'email'), 'forgot password: e-mail');
    }

    /** The long text areas count their letters ("123 / 1000") and the page's script (layout/char-counter.js) finds them by this mark. */
    public function test_the_long_text_areas_ask_for_a_counter(): void
    {
        $unaccepted = MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_PENDING, 'reporter_id' => $this->admin->id]);
        $pages = [
            $this->actingAs($this->admin)->get(route('maintenance.requests.show', $this->job))->getContent() => ['note', 'resolution_note', 'cancel_reason', 'remark'],
            $this->actingAs($this->admin)->get(route('maintenance.requests.show', $unaccepted))->getContent() => ['reject_reason'],
        ];
        foreach ($pages as $html => $fields) {
            foreach ($fields as $field) {
                $tag = collect($this->controlTags($html))->first(fn ($t) => str_starts_with($t, '<textarea') && str_contains($t, 'name="' . $field . '"'));
                $this->assertNotNull($tag, "no text area called $field");
                $this->assertStringContainsString('data-counter', $tag, "$field");
            }
        }

        $create = $this->actingAs($this->admin)->get(route('maintenance.requests.create'))->getContent();
        $tag = collect($this->controlTags($create))->first(fn ($t) => str_starts_with($t, '<textarea') && str_contains($t, 'name="description"'));
        $this->assertStringContainsString('data-counter', $tag ?? '');
    }

    /** The notification-sound page hands the file guard the SAME limit the upload rule uses. */
    public function test_the_sound_upload_input_carries_the_servers_limit_and_kinds(): void
    {
        $html = $this->actingAs($this->admin)->get(route('settings.notifications.index'))->assertOk()->getContent();
        $input = collect($this->controlTags($html))->first(fn ($t) => str_contains($t, 'name="sound_file"')) ?? '';

        $this->assertStringContainsString('data-max-kb="' . NotificationSettingController::SOUND_MAX_KB . '"', $input);
        $this->assertStringContainsString('data-ext="mp3,wav"', $input);

        // and the server really refuses a file over it
        $big = \Illuminate\Http\UploadedFile::fake()->create('big.mp3', NotificationSettingController::SOUND_MAX_KB + 1);
        $this->actingAs($this->admin)->from('/x')->post(route('settings.notifications.upload_sound'), ['sound_file' => $big])
            ->assertSessionHasErrors('sound_file');
        $this->assertStringContainsString('ไม่เกิน ' . intdiv(NotificationSettingController::SOUND_MAX_KB, 1024) . 'MB', session('errors')->first('sound_file'));
    }
}
