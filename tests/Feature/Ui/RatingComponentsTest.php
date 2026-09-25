<?php

namespace Tests\Feature\Ui;

use App\Support\RatingLevel;
use App\Support\ThaiDate;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * The pieces the three rating pages share: what a score is called (App\Support\RatingLevel), the stars, and the level label.
 * The team board and a person's own page each kept a copy of the wording and the copies disagreed.
 */
class RatingComponentsTest extends TestCase
{
    public function test_the_levels_start_at_4_5_4_and_3(): void
    {
        $this->assertSame('great', RatingLevel::of(5.0, 10)['key']);
        $this->assertSame('great', RatingLevel::of(4.5, 10)['key']);
        $this->assertSame('good', RatingLevel::of(4.49, 10)['key']);
        $this->assertSame('good', RatingLevel::of(4.0, 10)['key']);
        $this->assertSame('fair', RatingLevel::of(3.99, 10)['key']);
        $this->assertSame('fair', RatingLevel::of(3.0, 10)['key']);
        $this->assertSame('poor', RatingLevel::of(2.99, 10)['key']);
        $this->assertSame('poor', RatingLevel::of(1.0, 10)['key']);
    }

    public function test_the_wording_is_one_set(): void
    {
        $this->assertSame(['ดีมาก', 'ดี', 'ปานกลาง', 'ควรปรับปรุง'], [
            RatingLevel::of(4.6, 3)['label'], RatingLevel::of(4.2, 3)['label'], RatingLevel::of(3.5, 3)['label'], RatingLevel::of(2.0, 3)['label'],
        ]);
    }

    public function test_nobody_rated_is_not_a_bad_rating(): void
    {
        // an average of nothing used to be 0.00, which read as "ควรปรับปรุง"
        $this->assertSame('none', RatingLevel::of(0.0, 0)['key']);
        $this->assertSame('none', RatingLevel::of(null, 0)['key']);
        $this->assertSame('ยังไม่มีการประเมิน', RatingLevel::of(0.0, 0)['label']);
    }

    public function test_one_raters_stars_have_a_name(): void
    {
        $this->assertSame(
            ['ไม่พอใจ', 'ไม่ค่อยพอใจ', 'ปานกลาง', 'พอใจ', 'พอใจมาก'],
            array_map([RatingLevel::class, 'scoreLabel'], [1, 2, 3, 4, 5]),
        );
    }

    public function test_a_few_ratings_are_too_few_to_judge_by(): void
    {
        $this->assertTrue(RatingLevel::isThin(0));
        $this->assertTrue(RatingLevel::isThin(RatingLevel::ENOUGH_REVIEWS - 1));
        $this->assertFalse(RatingLevel::isThin(RatingLevel::ENOUGH_REVIEWS));
    }

    public function test_thai_short_dates_use_the_buddhist_year(): void
    {
        $this->assertSame('24 ก.ย. 2569', ThaiDate::short(Carbon::create(2026, 9, 24)));
        $this->assertSame('5 ม.ค. 2570', ThaiDate::short(Carbon::create(2027, 1, 5)));
        $this->assertSame('ก.ย. 2569', ThaiDate::monthYear(Carbon::create(2026, 9, 24)));
    }

    public function test_the_stars_fill_up_to_the_rounded_score(): void
    {
        foreach ([[0, 0], [1, 1], [3.4, 3], [3.5, 4], [5, 5]] as [$score, $filled]) {   // (not an array of floats as keys: PHP truncates them)
            $html = $this->blade('<x-rating.stars :score="$score" />', ['score' => $score])->__toString();

            $this->assertSame(5, substr_count($html, '<svg'), "score $score: five stars");
            $this->assertSame($filled, substr_count($html, 'text-amber-400'), "score $score: filled");
            $this->assertSame(5 - $filled, substr_count($html, 'text-slate-200'), "score $score: empty");
        }
    }

    public function test_the_stars_say_the_score_to_a_screen_reader_and_take_a_size(): void
    {
        $html = $this->blade('<x-rating.stars :score="4.25" size="xs" class="mt-1" />')->__toString();

        $this->assertStringContainsString('aria-label="4.3 จาก 5 ดาว"', $html);
        $this->assertStringContainsString('h-3 w-3', $html);
        $this->assertStringContainsString('mt-1', $html);
    }

    public function test_the_level_label_carries_the_wording_and_a_colour_per_level(): void
    {
        $cases = [
            [4.8, 5, 'ดีมาก', 'text-emerald-700'],
            [4.1, 5, 'ดี', 'text-emerald-600'],
            [3.4, 5, 'ปานกลาง', 'text-amber-600'],
            [2.1, 5, 'ควรปรับปรุง', 'text-rose-600'],
            [0.0, 0, 'ยังไม่มีการประเมิน', 'text-slate-400'],
        ];
        foreach ($cases as [$avg, $count, $label, $class]) {
            $html = $this->blade('<x-rating.level :average="$avg" :count="$count" />', ['avg' => $avg, 'count' => $count])->__toString();
            $this->assertStringContainsString(">{$label}<", $html);
            $this->assertStringContainsString($class, $html, $label);

            $pill = $this->blade('<x-rating.level :average="$avg" :count="$count" pill />', ['avg' => $avg, 'count' => $count])->__toString();
            $this->assertStringContainsString('rounded-full', $pill);
        }
    }
}
