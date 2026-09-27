<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Support\ThaiDate;
use App\Support\ThaiText;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * What the printed report leans on to read as Thai: dates with Thai month names and the Buddhist year, lines that break between words
 * (dompdf can only break at a space, and Thai has none), and the "+2 วัน 3 ชม." the SLA page and the report both show.
 */
class ThaiFormattingTest extends TestCase
{
    public function test_thai_dates_use_thai_months_and_the_buddhist_year(): void
    {
        $date = Carbon::create(2026, 9, 4, 9, 5);

        $this->assertSame('4 กันยายน 2569', ThaiDate::long($date));
        $this->assertSame('04/09/2569 09:05', ThaiDate::numericWithTime($date));
        $this->assertSame('4 กันยายน 2569 เวลา 09:05 น.', ThaiDate::longWithTime($date));
    }

    public function test_a_chat_time_is_read_the_way_a_person_says_it(): void
    {
        $now = Carbon::create(2026, 9, 26, 18, 0, 0, 'Asia/Bangkok');   // a Saturday evening

        $this->assertSame('15:45', ThaiDate::chatTime(Carbon::create(2026, 9, 26, 15, 45, 0, 'Asia/Bangkok'), $now), 'today');
        $this->assertSame('00:05', ThaiDate::chatTime(Carbon::create(2026, 9, 26, 0, 5, 0, 'Asia/Bangkok'), $now), 'today, just after midnight');
        $this->assertSame('เมื่อวาน 23:59', ThaiDate::chatTime(Carbon::create(2026, 9, 25, 23, 59, 0, 'Asia/Bangkok'), $now));
        $this->assertSame('วันพุธ 09:30', ThaiDate::chatTime(Carbon::create(2026, 9, 23, 9, 30, 0, 'Asia/Bangkok'), $now), 'three days ago');
        $this->assertSame('วันจันทร์ 09:30', ThaiDate::chatTime(Carbon::create(2026, 9, 21, 9, 30, 0, 'Asia/Bangkok'), $now), 'five days ago');
        $this->assertSame('20 ก.ย. 2569 09:30', ThaiDate::chatTime(Carbon::create(2026, 9, 20, 9, 30, 0, 'Asia/Bangkok'), $now), 'six days ago: a date');
        $this->assertSame('4 ก.ค. 2569 13:05', ThaiDate::chatTime(Carbon::create(2026, 7, 4, 13, 5, 0, 'Asia/Bangkok'), $now), 'older: date, Buddhist year');
    }

    public function test_a_chat_time_is_on_the_thai_clock_whatever_the_servers_timezone(): void
    {
        // 17:30 UTC on the 26th is 00:30 on the 27th in Thailand
        $utcNow = Carbon::create(2026, 9, 26, 17, 40, 0, 'UTC');

        $this->assertSame('00:30', ThaiDate::chatTime(Carbon::create(2026, 9, 26, 17, 30, 0, 'UTC'), $utcNow), 'the same Thai day, though the UTC date is not that of "now" + 7 h');
        $this->assertSame('เมื่อวาน 23:30', ThaiDate::chatTime(Carbon::create(2026, 9, 26, 16, 30, 0, 'UTC'), $utcNow), '16:30 UTC is 23:30 the day before in Thailand');
    }

    public function test_a_thai_phrase_is_put_in_a_span_per_word_so_a_line_can_break_between_them(): void
    {
        $html = (string) ThaiText::words('เครื่องคอมพิวเตอร์ห้องฉุกเฉินทำงานช้ามาก');

        $this->assertSame(
            '<span>เครื่อง</span><span>คอมพิวเตอร์</span><span>ห้อง</span><span>ฉุกเฉิน</span><span>ทำงาน</span><span>ช้า</span><span>มาก</span>',
            $html,
        );
        $this->assertSame('เครื่องคอมพิวเตอร์ห้องฉุกเฉินทำงานช้ามาก', strip_tags($html), 'nothing added or lost');
    }

    public function test_spaces_and_latin_words_are_kept_and_html_is_escaped(): void
    {
        $html = (string) ThaiText::words('WiFi หอผู้ป่วย <b>&</b>');

        $this->assertStringContainsString('<span>WiFi</span> <span>หอ</span>', $html);
        $this->assertStringContainsString('&lt;', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function test_text_without_thai_and_empty_text_go_out_as_they_are(): void
    {
        $this->assertSame('plain &amp; simple', (string) ThaiText::words('plain & simple'));
        $this->assertSame('', (string) ThaiText::words(''));
        $this->assertSame('', (string) ThaiText::words(null));
    }

    private function jobDueHoursAgo(float $hours, ?Carbon $now = null): MaintenanceRequest
    {
        $job = new MaintenanceRequest();
        $job->status = MaintenanceRequest::STATUS_IN_PROGRESS;
        $job->sla_due_date = ($now ?? Carbon::create(2026, 9, 24, 10, 0))->copy()->subMinutes((int) round($hours * 60));

        return $job;
    }

    public function test_the_overdue_label_is_days_and_hours_or_hours_and_minutes(): void
    {
        $now = Carbon::create(2026, 9, 24, 10, 0);

        $this->assertSame('+2 วัน 3 ชม.', $this->jobDueHoursAgo(51, $now)->overdueLabel($now), '2 days 3 h');
        $this->assertSame('+1 วัน 0 ชม.', $this->jobDueHoursAgo(24, $now)->overdueLabel($now));
        $this->assertSame('+5 ชม. 30 น.', $this->jobDueHoursAgo(5.5, $now)->overdueLabel($now), 'under a day drops the days');
        $this->assertSame('+0 ชม. 7 น.', $this->jobDueHoursAgo(7 / 60, $now)->overdueLabel($now));
    }

    public function test_a_job_on_hold_does_not_grow_more_overdue_while_it_waits(): void
    {
        $now = Carbon::create(2026, 9, 24, 10, 0);
        $job = $this->jobDueHoursAgo(5, $now);          // due at 05:00
        $job->status = MaintenanceRequest::STATUS_ON_HOLD;
        $job->on_hold_at = $now->copy()->subHours(3);   // put on hold at 07:00, already 2 h late

        $this->assertSame('+2 ชม. 0 น.', $job->overdueLabel($now), 'the clock stopped at 07:00');
    }

    public function test_a_job_without_a_deadline_has_no_overdue_label(): void
    {
        $this->assertSame('-', (new MaintenanceRequest())->overdueLabel());
    }
}
