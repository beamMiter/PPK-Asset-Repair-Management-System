<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MaintenanceLogController extends Controller
{
    public function index(MaintenanceRequest $req)
    {
        Gate::authorize('view', $req);

        $logs = $req->logs()
            ->select([
                'id',
                'request_id',
                'user_id',
                'action',
                'note',
                'from_status',
                'to_status',
                'created_at'
            ])
            ->with(['user:id,name'])
            ->latest('created_at')
            ->paginate(20);

        Log::info('[MaintenanceLog::index] listed logs', [
            'request_id' => $req->id,
            'total'      => $logs->total(),
            'actor_id'   => request()->user()?->id,
        ]);

        return response()->json($logs);
    }
}
