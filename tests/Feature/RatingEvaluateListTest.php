<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R2: the "pending evaluation" list must show exactly the requests that
 * withinRatingWindow() would accept — an OR across closed_at/resolved_at/
 * completed_date used to surface rows the rating guard then rejected.
 */
class RatingEvaluateListTest extends TestCase
{
    use RefreshDatabase;

    private function closedRequest(User $reporter, array $dates): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create(array_merge([
            'reporter_id'    => $reporter->id,
            'status'         => MaintenanceRequest::STATUS_CLOSED,
            'closed_at'      => null,
            'resolved_at'    => null,
            'completed_date' => null,
        ], $dates));
    }

    public function test_pending_list_matches_the_rating_window(): void
    {
        $reporter = User::factory()->create();

        $inWindow = $this->closedRequest($reporter, ['closed_at' => now()->subDays(5)]);
        $expired  = $this->closedRequest($reporter, ['closed_at' => now()->subDays(45)]);

        // closed 40d ago (out) but completed_date 5d ago (in): the old OR
        // showed this; the guard rejects it because COALESCE picks closed_at.
        $mismatch = $this->closedRequest($reporter, [
            'closed_at'      => now()->subDays(40),
            'completed_date' => now()->subDays(5),
        ]);

        // no closed_at, but completed_date is in window: guard accepts it.
        $fallback = $this->closedRequest($reporter, ['completed_date' => now()->subDays(3)]);

        $ids = $this->actingAs($reporter)
            ->get(route('maintenance.requests.rating.evaluate'))
            ->assertOk()
            ->viewData('pendingRequests')
            ->pluck('id')
            ->all();

        $this->assertContains($inWindow->id, $ids);
        $this->assertContains($fallback->id, $ids);
        $this->assertNotContains($expired->id, $ids);
        $this->assertNotContains($mismatch->id, $ids);
    }
}
