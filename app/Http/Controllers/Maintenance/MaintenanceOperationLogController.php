<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Support\Toast;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MaintenanceOperationLogController extends Controller
{
    public function upsert(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        Gate::authorize('updateOperationLog', $maintenanceRequest);

        $actorId = Auth::id();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'operation_date'   => ['nullable', 'date'],
            'operation_method' => ['nullable', Rule::in(['requisition', 'service_fee', 'other'])],
            'property_code'    => ['nullable', 'string', 'max:100'],
            'require_precheck' => ['nullable', 'boolean'],
            'remark'           => ['nullable', 'string', 'max:5000'],
            'issue_software'   => ['nullable', 'boolean'],
            'issue_hardware'   => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()
                ->with('toast', Toast::warning($validator->errors()->first(), 3000));
        }

        $data = $validator->validated();

        // // Normalize date to Y-m-d (ถ้ามีการส่งมา)
        if (!empty($data['operation_date'])) {
            $data['operation_date'] = Carbon::parse($data['operation_date'])->toDateString();
        }

        // // ปรับค่า Boolean สำหรับ Checkbox (Default เป็น false)
        $data['require_precheck'] = (bool) ($data['require_precheck'] ?? false);
        $data['issue_software']   = (bool) ($data['issue_software'] ?? false);
        $data['issue_hardware']   = (bool) ($data['issue_hardware'] ?? false);
        $data['user_id']          = $actorId;

        try {
            DB::transaction(function () use ($maintenanceRequest, $data) {
                $maintenanceRequest->operationLog()->updateOrCreate(
                    ['maintenance_request_id' => $maintenanceRequest->id],
                    $data
                );
            });

            Log::info('[MaintenanceOperationLog::upsert] saved successfully', [
                'request_id'       => $maintenanceRequest->id,
                'operation_date'   => $data['operation_date'] ?? null,
                'operation_method' => $data['operation_method'] ?? null,
                'property_code'    => $data['property_code'] ?? null,
                'require_precheck' => $data['require_precheck'],
                'issue_software'   => $data['issue_software'],
                'issue_hardware'   => $data['issue_hardware'],
                'actor_id'         => $actorId,
            ]);

            return redirect()
                ->route('maintenance.requests.show', $maintenanceRequest)
                ->with('toast', Toast::success('บันทึกรายงานการปฏิบัติงานเรียบร้อยแล้ว', 1800));

        } catch (\Throwable $e) {
            Log::error('[MaintenanceOperationLog::upsert] save failed', [
                'request_id' => $maintenanceRequest->id,
                'error'      => $e->getMessage(),
                'actor_id'   => $actorId,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('toast', Toast::error('เกิดข้อผิดพลาดในการบันทึกข้อมูล', 2200));
        }
    }
}
