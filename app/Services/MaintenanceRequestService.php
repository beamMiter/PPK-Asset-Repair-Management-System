<?php

namespace App\Services;

use App\Models\MaintenanceRequest as MR;
use App\Models\Asset;
use App\Models\User;
use App\Events\MaintenanceRequestCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MaintenanceRequestService
{
    protected MaintenanceAttachmentService $attachmentService;
    protected MaintenanceTransitionService $transitionService;

    public function __construct(
        MaintenanceAttachmentService $attachmentService,
        MaintenanceTransitionService $transitionService
    ) {
        $this->attachmentService = $attachmentService;
        $this->transitionService = $transitionService;
    }

    /**
     * ดำเนินการสร้างคำขอซ่อมบำรุงใหม่ (Store)
     */
    public function createRequest(array $data, ?User $user, array $files = [], array $captions = []): MR
    {
        $actorId = $user?->id;
        $isTeam = $user && ($user->isAdmin() || $user->isSupervisor() || $user->isTechnician());
        $departmentId = $data['department_id'] ?? null;

        if (!$isTeam) {
            // Priority default removed
        }

        if (!empty($data['asset_id'])) {
            $asset = Asset::find($data['asset_id']);
            if ($asset && $asset->status === 'disposed') {
                throw new \Exception('ไม่สามารถแจ้งซ่อมทรัพย์สินที่จำหน่ายออกแล้วได้', 101);
            }
            if (empty($departmentId)) {
                $departmentId = $asset->department_id;
            }
        }

        $req = DB::transaction(function () use ($data, $user, $departmentId, $actorId, $files, $captions) {
            $newReq = MR::create([
                'title'          => $data['title'],
                'description'    => $data['description'] ?? null,
                'status'         => MR::STATUS_PENDING,
                'request_date'   => now(),
                'asset_id'       => $data['asset_id'] ?? null,
                'department_id'  => $departmentId,
                'type_id'        => $data['type_id'] ?? null,
                'location_text'  => $data['location_text'] ?? null,
                'reporter_id'    => $user instanceof User ? $user->id : null,
                'reporter_name'  => $data['reporter_name'] ?? ($user instanceof User ? $user->name : null),
                'reporter_email' => $data['reporter_email'] ?? ($user instanceof User ? $user->email : null),
                'reporter_phone' => $data['reporter_phone'] ?? null,
                'technician_id'  => null,
            ]);

            Log::info('[MaintenanceRequestService] created', [
                'id'            => $newReq->id,
                'request_no'    => $newReq->request_no,
                'actor_id'      => $actorId,
            ]);

            if (!empty($files)) {
                $this->attachmentService->attachFiles($newReq, $files, $captions, $actorId);
            }
            
            return $newReq;
        });

        if ($req) {
            DB::afterCommit(function () use ($req) {
                broadcast(new MaintenanceRequestCreated([
                    'id'         => $req->id,
                    'request_no' => $req->request_no ?? null,
                    'title'      => $req->title,
                    'status'     => $req->status,
                    'created_at' => $req->created_at?->toIso8601String(),
                ]));
            });
        }

        return $req;
    }

    /**
     * ดำเนินการแก้ไขใบงาน (Update)
     */
    public function updateRequest(MR $req, array $data, ?User $user, array $files = [], array $captions = [], array $removeAttachments = []): MR
    {
        $actorId = $user instanceof User ? $user->id : null;
        $isTeam = $user && ($user->isAdmin() || $user->isSupervisor() || $user->isTechnician());

        DB::transaction(function () use (&$data, $user, $actorId, $isTeam, $req, $files, $captions, $removeAttachments) {
            $originalStatus = $req->status;
            $originalTechId = (int) ($req->technician_id ?? 0);

            $incomingTechId = array_key_exists('technician_id', $data) ? (int) ($data['technician_id'] ?? 0) : $originalTechId;
            $incomingUserIds = $data['user_ids'] ?? null;
            $forceUpdateTeam = array_key_exists('update_team_flag', $data) || array_key_exists('user_ids', $data);

            if ($forceUpdateTeam && empty($incomingUserIds) && !array_key_exists('technician_id', $data)) {
                $incomingTechId = 0;
            }

            if (!$isTeam) {
                if (($data['status'] ?? null) === MR::STATUS_CANCELLED) {
                    if (!in_array($req->status, [MR::STATUS_PENDING, MR::STATUS_ACCEPTED], true) || !empty($req->technician_id)) {
                        unset($data['status']);
                    }
                } else {
                    unset($data['status']);
                }

                if (array_key_exists('type_id', $data) && !($req->status === MR::STATUS_PENDING && empty($req->technician_id))) {
                    unset($data['type_id']);
                }

                unset(
                    $data['technician_id'],
                    $data['user_ids'],
                    $data['request_date'], // the SLA clock starts here: only the team may correct it
                    $data['cost'],
                    $data['resolution_note'],
                    $data['operation_date'],
                    $data['operation_method'],
                    $data['property_code'],
                    $data['require_precheck'],
                    $data['remark'],
                    $data['issue_software'],
                    $data['issue_hardware']
                );

                $incomingTechId = $originalTechId;

                // `user_ids` was read above, before it was stripped from $data: without this a reporter could still
                // add staff to — or, with an empty list, wipe the team of — their own request.
                $incomingUserIds = null;
                $forceUpdateTeam = false;
            }

            $req->fill($data);

            if ($isTeam && ($data['status'] ?? null) === MR::STATUS_ACCEPTED && empty($req->technician_id) && $actorId) {
                $req->technician_id = $actorId;
                $incomingTechId     = $actorId;
            }

            $req->save();

            $techChanged   = $isTeam && $originalTechId !== $incomingTechId;
            $statusChanged = array_key_exists('status', $data) && $originalStatus !== $req->status;

            // Handle transition logs & assignments if status or tech changed
            if ($statusChanged || $techChanged) {
                $transitionData = ['status' => $req->status];
                if ($techChanged) $transitionData['technician_id'] = $incomingTechId;
                
                // Rollback status temporally for TransitionService to detect the change properly
                $req->status = $originalStatus;
                $req->technician_id = $originalTechId;
                
                $this->transitionService->applyTransition($req, $transitionData, $actorId);
            } elseif ($forceUpdateTeam) {
                $this->transitionService->syncAssignments($req, $incomingUserIds ?: [], $actorId);
            }

            // Remove attachments
            $toRemove = array_filter($removeAttachments, fn($v) => is_numeric($v));
            if (!empty($toRemove)) {
                $this->attachmentService->detachFiles($req, $toRemove, $actorId);
            }

            // Add new attachments
            if (!empty($files)) {
                $this->attachmentService->attachFiles($req, $files, $captions, $actorId);
            }

            // Operation log handling
            $opKeys = ['operation_date', 'operation_method', 'property_code', 'remark', 'require_precheck', 'issue_software', 'issue_hardware'];
            $hasOp  = !empty(array_intersect_key($data, array_flip($opKeys))) || $req->operationLog()->exists();

            if ($hasOp) {
                $opDate = !empty($data['operation_date']) ? Carbon::parse($data['operation_date'])->toDateString() : null;
                $req->operationLog()->updateOrCreate(
                    ['maintenance_request_id' => $req->id],
                    [
                        'operation_date'   => $opDate,
                        'operation_method' => $data['operation_method'] ?? null,
                        'property_code'    => $data['property_code'] ?? null,
                        'require_precheck' => (bool) ($data['require_precheck'] ?? false),
                        'remark'           => $data['remark'] ?? null,
                        'issue_software'   => (bool) ($data['issue_software'] ?? false),
                        'issue_hardware'   => (bool) ($data['issue_hardware'] ?? false),
                        'user_id'          => $actorId,
                    ]
                );
            }
        });

        return $req->fresh(['attachments.file', 'operationLog']);
    }
}
