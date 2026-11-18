<?php

namespace App\View\Components;

use App\Models\MaintenanceRequest;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/**
 * The note a reporter gets where they land (the dashboard, above the list of requests) when requests they filed were sent back to them to fix
 * ("ส่งกลับแก้ไข"): how many, how many are close to being cancelled automatically, and a way to the list already filtered. Only what THEY reported,
 * whatever else they may see (an admin sees every request, the banner is still about their own). Nothing at all when nothing waits for them.
 * "Close to expiring" is MaintenanceRequest::returnIsExpiring(), the same rule as the badge and the list's filter.
 */
class ReturnedRequestsBanner extends Component
{
    public int $returned = 0;

    public int $expiring = 0;

    /** Days left on the soonest one that is close to expiring (null when none is). */
    public ?int $soonestDaysLeft = null;

    /** After how many days the rule cancels a request sent back (0 = it never does). */
    public int $expiresAfterDays = 0;

    public function __construct()
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $requests = MaintenanceRequest::query()
            ->where('reporter_id', $user->id)
            ->where('status', MaintenanceRequest::STATUS_RETURNED)
            ->get(['id', 'status', 'status_updated_at']);

        $close = $requests->filter(fn (MaintenanceRequest $r) => $r->returnIsExpiring());

        $this->returned = $requests->count();
        $this->expiring = $close->count();
        $this->soonestDaysLeft = $close->map(fn (MaintenanceRequest $r) => $r->returnDaysLeft())->min();
        $this->expiresAfterDays = (int) config('repair.return_expires_after_days');
    }

    public function shouldRender(): bool
    {
        return $this->returned > 0;
    }

    public function render(): View|Closure|string
    {
        return view('components.returned-requests-banner');
    }
}
