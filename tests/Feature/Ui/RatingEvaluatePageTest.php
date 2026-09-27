<?php

namespace Tests\Feature\Ui;

use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "ประเมินความพึงพอใจ" page: header glyph matches the other pages (Material Symbols, not the old feedback.webp
 * image) and its buttons are the shared <x-ui.button>. Also pins which jobs are offered for rating.
 */
class RatingEvaluatePageTest extends TestCase
{
    use RefreshDatabase;

    private function closedRequestFor(User $member, array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'reporter_id' => $member->id,
            'status' => MaintenanceRequest::STATUS_CLOSED,
            'closed_at' => now()->subHour(),
        ]);
    }

    public function test_header_uses_a_material_symbol_and_no_bare_image_icon(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $html = $this->actingAs($member)->get(route('maintenance.requests.rating.evaluate'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-hidden="true">rate_review</span>', $html);
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*ประเมินความพึงพอใจ/u', $html);
        $this->assertStringNotContainsString('class="w-8 h-8 object-contain mt-0.5" alt=""', $html, 'the old <img> header icon is back');
    }

    public function test_pending_job_gets_the_shared_buttons_with_the_rate_link(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $req = $this->closedRequestFor($member);

        $html = $this->actingAs($member)->get(route('maintenance.requests.rating.evaluate'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<x-ui', $html);
        $this->assertStringContainsString(route('maintenance.requests.show', $req).'?rate=1', $html);
        $this->assertStringContainsString('ประเมินงาน', $html);
        $this->assertStringContainsString('>star</span>', $html);
        $this->assertStringContainsString('bg-amber-600', $html, 'rate action is the amber/star button used in the post-close dialog');
        $this->assertStringNotContainsString('animate-bounce-x', $html);
        $this->assertStringNotContainsString('open_in_new', $html);
    }

    public function test_rated_job_moves_to_the_history_tab_with_a_view_button(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $tech = User::factory()->create(['role' => 'it_support']);
        $req = $this->closedRequestFor($member, ['technician_id' => $tech->id]);
        MaintenanceRating::create([
            'maintenance_request_id' => $req->id, 'rater_id' => $member->id, 'technician_id' => $tech->id, 'score' => 5, 'comment' => 'ดีมาก',
        ]);

        // the waiting list no longer has it …
        $waiting = $this->actingAs($member)->get(route('maintenance.requests.rating.evaluate'))->assertOk()->getContent();
        $this->assertStringNotContainsString('?rate=1', $waiting, 'a rated job must not be offered for rating again');
        $this->assertStringContainsString('ไม่มีงานค้างประเมิน', $waiting);

        // … the history tab does, with a way to look at it
        $history = $this->actingAs($member)->get(route('maintenance.requests.rating.evaluate', ['tab' => 'rated']))->assertOk()->getContent();
        $this->assertStringContainsString('ดีมาก', $history);
        $this->assertStringContainsString('ดูรายการ', $history);
        $this->assertStringNotContainsString('?rate=1', $history);
    }

    public function test_only_own_closed_jobs_are_listed(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $someoneElse = User::factory()->create(['role' => 'member']);
        $mine = $this->closedRequestFor($member);
        $theirs = $this->closedRequestFor($someoneElse);

        $html = $this->actingAs($member)->get(route('maintenance.requests.rating.evaluate'))->assertOk()->getContent();

        $this->assertStringContainsString(route('maintenance.requests.show', $mine).'?rate=1', $html);
        $this->assertStringNotContainsString(route('maintenance.requests.show', $theirs).'?rate=1', $html);
    }
}
