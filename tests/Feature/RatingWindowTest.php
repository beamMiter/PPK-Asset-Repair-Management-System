<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\MaintenanceRatingApiController;
use App\Http\Controllers\MaintenanceRatingController;
use App\Models\MaintenanceRequest;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the Carbon 3 fix in withinRatingWindow(): diffInDays() is now signed,
 * so `now()->diffInDays($closedAt)` on a past date returns a negative number and
 * the "<= deadline" check would pass forever. The fix passes `true` for an
 * absolute day count.
 */
class RatingWindowTest extends TestCase
{
    private function webWindow(MaintenanceRequest $mr): bool
    {
        $m = new ReflectionMethod(MaintenanceRatingController::class, 'withinRatingWindow');
        $m->setAccessible(true);

        return $m->invoke(app(MaintenanceRatingController::class), $mr);
    }

    private function apiWindow(MaintenanceRequest $mr): bool
    {
        $m = new ReflectionMethod(MaintenanceRatingApiController::class, 'withinRatingWindow');
        $m->setAccessible(true);

        return $m->invoke(app(MaintenanceRatingApiController::class), $mr);
    }

    public function test_window_is_open_for_a_recently_closed_request(): void
    {
        $mr = new MaintenanceRequest(['closed_at' => now()->subDays(5)]);

        $this->assertTrue($this->webWindow($mr));
        $this->assertTrue($this->apiWindow($mr));
    }

    public function test_window_is_closed_once_the_deadline_has_passed(): void
    {
        $mr = new MaintenanceRequest(['closed_at' => now()->subDays(45)]);

        $this->assertFalse($this->webWindow($mr));
        $this->assertFalse($this->apiWindow($mr));
    }

    public function test_window_falls_back_to_resolved_at(): void
    {
        $mr = new MaintenanceRequest(['resolved_at' => now()->subDays(45)]);

        $this->assertFalse($this->webWindow($mr));
        $this->assertFalse($this->apiWindow($mr));
    }

    public function test_window_is_closed_when_there_is_no_completion_date(): void
    {
        $this->assertFalse($this->webWindow(new MaintenanceRequest()));
        $this->assertFalse($this->apiWindow(new MaintenanceRequest()));
    }

    /**
     * R3: a future-dated completion must not read as "still inside the window".
     * The web controller already guards with isPast(); the API copy did not,
     * so its abs day-diff (~0) passed the "<= deadline" check.
     */
    public function test_window_is_closed_for_a_future_dated_request(): void
    {
        $mr = new MaintenanceRequest(['closed_at' => now()->addDays(3)]);

        $this->assertFalse($this->webWindow($mr));
        $this->assertFalse($this->apiWindow($mr));
    }
}
