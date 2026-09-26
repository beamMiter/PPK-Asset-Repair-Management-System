<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A form that fails validation is sent back with its errors, and only a handful of pages print them — the modals of a job (hold /
 * resolve / cancel / reject), the notification-sound and SLA settings and the chat bounced back and said nothing. bootstrap/app.php now
 * adds the first message as a toast to every such refusal on the web.
 */
class ValidationToastTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function jobInProgress(User $reporter): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_IN_PROGRESS, 'reporter_id' => $reporter->id]);
    }

    public function test_a_modal_of_a_job_that_is_refused_says_why_and_still_carries_its_errors(): void
    {
        $admin = $this->admin();
        $job = $this->jobInProgress($admin);

        $this->actingAs($admin)->from('/back')->post(route('maintenance.requests.hold', $job), [])
            ->assertRedirect('/back')
            ->assertSessionHasErrors('note')                                   // a page that prints them still can
            ->assertSessionHas('toast', fn ($t) => $t['type'] === 'warning' && $t['message'] === 'กรุณากรอกข้อมูล เหตุผลในการพักชั่วคราว');

        $this->actingAs($admin)->from('/back')->post(route('maintenance.requests.resolve', $job), ['resolution_note' => str_repeat('ก', 2001)])
            ->assertSessionHas('toast', fn ($t) => $t['message'] === 'รายละเอียดการซ่อม จะต้องมีความยาวไม่เกิน 2000 ตัวอักษร');
    }

    public function test_other_pages_that_printed_nothing_now_toast_too(): void
    {
        $admin = $this->admin();
        $type = MaintenanceRequestType::create(['name' => 'T1', 'is_active' => true]);

        $refusals = [
            'a chat thread with no title'  => [fn () => $this->actingAs($admin)->post(route('chat.store'), []), 'กรุณากรอกข้อมูล หัวข้อ'],
            'a sound that is not chosen'   => [fn () => $this->actingAs($admin)->post(route('settings.notifications.upload_sound'), []), 'กรุณาเลือกไฟล์เสียง'],
            'an SLA target that is text'   => [fn () => $this->actingAs($admin)->patch(route('maintenance.sla.update-type-default', $type->id), ['default_response_minutes' => 'abc']), 'เป้าหมายเวลาตอบกลับ (นาที) จะต้องเป็นจำนวนเต็ม'],
        ];

        foreach ($refusals as $what => [$send, $message]) {
            $send()->assertSessionHas('toast', fn ($t) => $t['message'] === $message && $t['type'] === 'warning');
            $this->assertSame($message, session('toast.message'), $what);
            $this->flushSession();
        }
    }

    public function test_several_errors_say_the_first_and_how_many_more(): void
    {
        $admin = $this->admin();
        $type = MaintenanceRequestType::create(['name' => 'T1', 'is_active' => true]);

        $this->actingAs($admin)->patch(route('maintenance.sla.bulk-update-type-default'), [
            'types' => [$type->id => ['default_response_minutes' => 'x', 'default_resolution_minutes' => 'y']],
        ])->assertSessionHas('toast', fn ($t) => str_ends_with($t['message'], '(และมีอีก 1 ข้อ)'));
    }

    /** The profile form flashes a toast of its own (type "error"): the handler must not overwrite it with a second one. */
    public function test_a_request_that_already_has_its_own_toast_keeps_it(): void
    {
        $this->actingAs($this->admin())->from('/back')->patch(route('profile.update'), [])
            ->assertSessionHas('toast', fn ($t) => $t['type'] === 'error' && $t['message'] === 'กรุณากรอกชื่อ');
    }

    public function test_a_json_request_still_gets_its_422_and_no_toast_is_flashed(): void
    {
        $admin = $this->admin();
        $job = $this->jobInProgress($admin);

        $this->actingAs($admin)->postJson(route('maintenance.requests.hold', $job), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');
        $this->assertNull(session('toast'));
    }

    public function test_the_api_is_left_as_it_was(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/threads', [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertNull(session('toast'));
    }
}
