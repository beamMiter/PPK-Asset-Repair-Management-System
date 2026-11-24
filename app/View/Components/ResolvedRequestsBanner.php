<?php

namespace App\View\Components;

use App\Models\MaintenanceRequest;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/**
 * The note a reporter gets where they land (the dashboard, above the list of requests) when the repair of a request they filed is done
 * ("ซ่อมบำรุงเสร็จสิ้น") and waits for THEIR approval ("อนุมัติปิดงาน"; a rating can only follow it): how many, and a way to the list already
 * filtered. Only what THEY reported, whatever else they may see (an admin sees every request, the banner is still about their own).
 * Nothing at all when nothing waits for them. The sibling of ReturnedRequestsBanner.
 */
class ResolvedRequestsBanner extends Component
{
    public int $waiting = 0;

    public function __construct()
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $this->waiting = MaintenanceRequest::query()
            ->where('reporter_id', $user->id)
            ->where('status', MaintenanceRequest::STATUS_RESOLVED)
            ->count();
    }

    public function shouldRender(): bool
    {
        return $this->waiting > 0;
    }

    public function render(): View|Closure|string
    {
        return view('components.resolved-requests-banner');
    }
}
