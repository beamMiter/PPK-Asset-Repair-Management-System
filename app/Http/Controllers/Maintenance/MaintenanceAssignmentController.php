<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MaintenanceAssignmentController extends Controller
{
    public function store(Request $request, MaintenanceRequest $req)
    {
        Gate::authorize('assign', $req);

        // ตัด lead_user_id ออกจากการ validate ไปเลย ไม่ใช้แล้ว
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'user_ids'   => ['nullable', 'array'],
            // only working staff: the picker offers nobody else, and a hand-made request must not either
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->whereNull('suspended_at')->whereIn('role', User::teamRoles())],
        ], [
            'user_ids.*.exists' => 'เลือกได้เฉพาะเจ้าหน้าที่ในทีมที่ยังใช้งานอยู่',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()
                ->with('toast', Toast::warning($validator->errors()->first(), 3000));
        }

        $data = $validator->validated();

        $actorId = $request->user()?->id;

        // เตรียมรายชื่อเจ้าหน้าที่ (คัดเฉพาะที่มีตัวตนและไม่ซ้ำ)
        $userIds = collect($data['user_ids'] ?? [])
            ->filter()
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values();

        // every box unticked would leave a job somebody is working on with nobody on it (only staff could then touch it)
        if ($userIds->isEmpty() && $req->needsTeam()) {
            return back()->with('toast', Toast::warning('งานนี้ดำเนินการอยู่ ต้องเลือกเจ้าหน้าที่อย่างน้อย 1 คน', 3500));
        }

        $workers = User::query()
            ->whereIn('id', $userIds->all())
            ->get(['id', 'name', 'role'])
            ->keyBy('id');

        // ตรวจสอบสิทธิ์การมอบหมายงาน: หัวหน้า/แอดมินเท่านั้นที่จะเพิ่มรับผิดชอบเป็นหัวหน้า/แอดมินได้
        $currentUser = $request->user();
        $isAssignerAdminTeam = $currentUser && ($currentUser->isAdmin() || $currentUser->isSupervisor());

        foreach ($workers as $w) {
            if (in_array($w->role, [User::ROLE_ADMIN, User::ROLE_SUPERVISOR])) {
                if (!$isAssignerAdminTeam) {
                    return back()->withInput()->with('toast', Toast::error('เจ้าหน้าที่ไม่สามารถมอบหมายงานให้ผู้ดูแลระบบหรือหัวหน้างานได้', 4000));
                }
            }
        }

        try {
            DB::transaction(function () use ($req, $workers, $userIds, $actorId) {
                $now = now();

                $lockedReq = MaintenanceRequest::query()
                    ->whereKey($req->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = $lockedReq->assignments()->get()->keyBy('user_id');

                // บังคับเป็น null เสมอ เพราะเราไม่ใช้ระบบเจ้าหน้าที่รับผิดชอบหลัก (Lead) แล้ว
                $lockedReq->technician_id = null;

                if ($workers->isNotEmpty() && $lockedReq->assigned_date === null) {
                    $lockedReq->assigned_date = $now;
                }
                $lockedReq->save();

                foreach ($userIds as $uid) {
                    $worker = $workers->get((int) $uid);
                    if (!$worker) continue;

                    $assignment = $existing->get($worker->id);

                    if ($assignment) {
                        // ถ้ามีรายชื่ออยู่แล้ว แต่อาจจะเคยโดนยกเลิกไป ให้ดึงกลับมาใหม่
                        $updateData = [
                            'role'    => $worker->role,
                            'is_lead' => false, // ทุกคนคือเจ้าหน้าที่เท่ากันหมด ไม่มี Lead
                        ];

                        if ($assignment->status === MaintenanceAssignment::STATUS_CANCELLED) {
                            $updateData['status'] = MaintenanceAssignment::STATUS_IN_PROGRESS;
                            $updateData['response_status'] = MaintenanceAssignment::RESP_PENDING;
                            $updateData['responded_at'] = null;
                        }

                        $assignment->update($updateData);
                    } else {
                        // ถ้าเป็นเจ้าหน้าที่ใหม่ที่เพิ่งเพิ่มเข้ามา
                        MaintenanceAssignment::create([
                            'maintenance_request_id' => $lockedReq->id,
                            'user_id'                => $worker->id,
                            'role'                   => $worker->role,
                            'is_lead'                => false,
                            'assigned_at'            => $now,
                            'response_status'        => MaintenanceAssignment::RESP_PENDING,
                            'status'                 => MaintenanceAssignment::STATUS_IN_PROGRESS,
                        ]);
                    }
                }

                $keepIds = $userIds->all();
                $lockedReq->assignments()
                    ->whereNotIn('user_id', $keepIds)
                    ->update([
                        'status'          => MaintenanceAssignment::STATUS_CANCELLED,
                        'is_lead'         => false,
                        'response_status' => MaintenanceAssignment::RESP_PENDING,
                        'responded_at'    => null,
                        'updated_at'      => $now,
                    ]);
            });

            Log::info('[MaintenanceAssignment::store] team updated - Lead system removed', [
                'request_id' => $req->id,
                'user_count' => $userIds->count(),
                'actor_id'   => $actorId,
            ]);

            return back()->with('toast', Toast::success('อัปเดตรายชื่อเจ้าหน้าที่เรียบร้อยแล้ว', 1800));
        } catch (\Throwable $e) {
            Log::error('[MaintenanceAssignment::store] failed', [
                'request_id' => $req->id,
                'error'      => $e->getMessage()
            ]);
            return back()->with('toast', Toast::error('เกิดข้อผิดพลาด: ' . $e->getMessage(), 3000));
        }
    }
}
