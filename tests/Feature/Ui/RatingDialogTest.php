<?php

namespace Tests\Feature\Ui;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rating dialog on the job page is built on the same frame as the other dialogs there (reject / cancel / hold / close,
 * assign team, the history log): the same dimming, the same bordered card, a header with a close button, a body of
 * px-4 py-4 space-y-4, a footer of buttons. It used to be its own thing — a darker backdrop, a 2px square card without a border,
 * px-8 / p-8 padding, an 18px bold title — and its comment box had no length limit, so a long comment was sent, refused, and only
 * then said so.
 */
class RatingDialogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reporter;

    private MaintenanceRequest $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $tech = User::factory()->create(['role' => 'it_support']);
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->job = MaintenanceRequest::factory()->create([
            'status' => MaintenanceRequest::STATUS_CLOSED, 'closed_at' => now()->subDay(),
            'reporter_id' => $this->reporter->id, 'technician_id' => $tech->id,
        ]);
        MaintenanceAssignment::create(['maintenance_request_id' => $this->job->id, 'user_id' => $tech->id, 'status' => 'done', 'is_lead' => true]);
    }

    private function page(): string
    {
        return $this->actingAs($this->admin)->get(route('maintenance.requests.show', $this->job))->assertOk()->getContent();
    }

    /**
     * The opening tag of the comment box. It ends at the first `>` outside quotes: `:required="score > 0 && score <= 2"` has a `>`
     * inside it, which a plain [^>]* would take for the end of the tag (FormFieldLimitsTest reads tags the same way).
     */
    private function commentBox(string $html): string
    {
        preg_match_all('/<textarea\b/', $html, $starts, PREG_OFFSET_CAPTURE);
        foreach ($starts[0] as [, $at]) {
            $quote = null;
            for ($i = $at + 1, $n = strlen($html); $i < $n; $i++) {
                $c = $html[$i];
                if ($quote) {
                    $quote = $c === $quote ? null : $quote;
                } elseif ($c === '"' || $c === "'") {
                    $quote = $c;
                } elseif ($c === '>') {
                    $tag = substr($html, $at, $i - $at + 1);
                    if (str_contains($tag, 'name="comment"')) {
                        return $tag;
                    }
                    break;
                }
            }
        }

        $this->fail('no text area called comment on the page');
    }

    public function test_the_dialog_dims_the_page_and_frames_itself_like_the_other_dialogs(): void
    {
        $html = $this->page();

        preg_match('/<div x-show="ratingOpen"[^>]*>/', $html, $overlay);
        $this->assertNotEmpty($overlay, 'the dialog is on the page');
        $this->assertStringContainsString('fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4', $overlay[0], 'the same backdrop as every other dialog');
        $this->assertStringNotContainsString('bg-slate-900/60', $overlay[0]);
        $this->assertStringContainsString('@click.self="ratingOpen = false"', $overlay[0], 'a click outside closes it');
        $this->assertStringContainsString('@keydown.escape.window="ratingOpen = false"', $overlay[0], 'so does Escape');

        $this->assertStringContainsString('relative z-[10000] w-full max-w-lg max-h-[92vh] overflow-y-auto rounded-md border border-slate-200 bg-white', $html, 'the card');
        $this->assertStringContainsString('flex items-center justify-between border-b border-slate-200 px-4 py-3', $html, 'the header');
        $this->assertStringContainsString('class="px-4 py-4 space-y-4"', $html, 'the body');
        $this->assertStringContainsString('flex justify-end gap-2 pt-2', $html, 'the footer');
    }

    public function test_nothing_is_left_of_the_old_heavy_styling(): void
    {
        $source = file_get_contents(resource_path('views/maintenance/requests/partials/_modal_rating.blade.php'));

        foreach (['rounded-sm', 'px-8 py-6', 'class="p-8"', 'tracking-widest', 'text-[18px] font-bold', 'bg-slate-900/60', 'p-1 bg-slate-50'] as $old) {
            $this->assertStringNotContainsString($old, $source, $old);
        }
    }

    public function test_the_comment_box_carries_the_limit_the_server_enforces_and_a_counter(): void
    {
        // the limit is not written down here: it is read from the server's own refusal, as FormFieldLimitsTest does
        $this->actingAs($this->reporter)->from('/x')->post(route('maintenance.requests.rating.store', $this->job), ['score' => 5, 'comment' => str_repeat('ก', 20000)]);
        $message = session('errors')?->first('comment') ?? '';
        $this->assertMatchesRegularExpression('/(\d+) ตัวอักษร/u', $message, "the server did not name a limit: “{$message}”");
        preg_match('/(\d+) ตัวอักษร/u', $message, $limit);

        $tag = $this->commentBox($this->page());
        $this->assertStringContainsString('maxlength="' . $limit[1] . '"', $tag);
        $this->assertStringContainsString('data-counter', $tag);
    }

    public function test_the_comment_box_is_the_same_as_the_other_dialogs_text_boxes(): void
    {
        $this->assertStringContainsString('mt-2 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm resize-none overflow-hidden', $this->commentBox($this->page()));
    }

    public function test_the_stars_say_what_they_mean_and_a_low_score_asks_for_the_reason(): void
    {
        $html = $this->page();

        foreach ([1, 2, 3, 4, 5] as $score) {
            $this->assertStringContainsString(\App\Support\RatingLevel::scoreLabel($score), $html, "the word for $score stars comes from RatingLevel");
        }
        $this->assertStringContainsString('x-text="score ? names[score] : ', $html);
        // the server refuses 1–2 stars without a comment; the box now says so, and stops an empty submit, before the trip
        $this->assertStringContainsString(':required="score > 0 && score <= 2"', $html);
        $this->assertStringContainsString('ถ้าให้ 1–2 ดาว กรุณาระบุความคิดเห็นเพิ่มเติม', $html);
        $this->assertSame(5, substr_count($html, 'x-model.number="score"'), 'five stars, one value');
    }

    public function test_the_dialog_opens_again_after_a_refused_rating_with_what_was_typed(): void
    {
        $this->actingAs($this->reporter)->from(route('maintenance.requests.show', $this->job))
            ->post(route('maintenance.requests.rating.store', $this->job), ['score' => 1, 'comment' => '']);   // 1 star needs a reason

        $html = $this->actingAs($this->reporter)->get(route('maintenance.requests.show', $this->job))->getContent();

        $this->assertStringContainsString('ratingOpen: true', $html, 'the dialog is open, not silently closed');
        $this->assertStringContainsString('score: 1,', $html, 'the star that was picked is still picked');
    }

    public function test_the_texts_the_page_already_tested_for_are_still_there(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('บันทึกการประเมิน', $html);
        $this->assertStringContainsString('ประเมินความพึงพอใจ', $html);
        $this->assertStringContainsString('ใบงานเลขที่ #' . $this->job->request_no, $html);
        $this->assertStringContainsString(route('maintenance.requests.rating.store', $this->job), $html);
    }
}
