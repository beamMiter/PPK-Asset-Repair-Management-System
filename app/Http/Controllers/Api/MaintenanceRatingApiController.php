<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenanceTransitionService;
use App\Traits\HandlesMaintenanceRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MaintenanceRatingApiController extends Controller
{
    use HandlesMaintenanceRating;

    /**
     * ดึง “งานที่รอการให้คะแนน” ของ user ปัจจุบัน
     *
     * GET /api/repair-requests/pending-evaluations
     */
    public function pendingEvaluations(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $requests = MaintenanceRequest::with(['technician', 'rating'])
            ->where('reporter_id', $user->id)
            ->whereIn('status', [
                MaintenanceRequest::STATUS_RESOLVED,
                MaintenanceRequest::STATUS_CLOSED,
            ])
            ->whereDoesntHave('rating')
            ->get()
            ->filter(fn (MaintenanceRequest $req) => $this->withinRatingWindow($req))
            ->values();

        return response()->json([
            'data' => $requests,
        ]);
    }

    /**
     * บันทึกคะแนนให้ใบงาน
     *
     * POST /api/repair-requests/{maintenanceRequest}/rating
     * body: { "score": 1-5, "comment": "..." }
     */
    public function store(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // 1) ต้องเป็นคนแจ้งซ่อมเท่านั้น
        if ($maintenanceRequest->reporter_id !== $user->id) {
            return response()->json([
                'message' => 'คุณไม่มีสิทธิ์ให้คะแนนงานนี้',
            ], 403);
        }

        // 2) งานต้องปิดแล้ว (RESOLVED / CLOSED)
        if (! in_array($maintenanceRequest->status, [
            MaintenanceRequest::STATUS_RESOLVED,
            MaintenanceRequest::STATUS_CLOSED,
        ], true)) {
            return response()->json([
                'message' => 'สามารถให้คะแนนได้เฉพาะงานที่ปิดแล้วเท่านั้น',
            ], 422);
        }

        // 3) ห้ามให้คะแนนซ้ำ
        if ($maintenanceRequest->rating) {
            return response()->json([
                'message' => 'งานนี้มีการให้คะแนนไปแล้ว',
            ], 409);
        }

        // 4) เช็ค window เวลา
        if (! $this->withinRatingWindow($maintenanceRequest)) {
            return response()->json([
                'message' => 'เลยระยะเวลาที่สามารถให้คะแนนงานนี้ได้แล้ว',
            ], 422);
        }

        // 5) ต้องมีเจ้าหน้าที่ให้ผูกคะแนน (เหมือนฝั่ง web)
        $technicianId = $this->resolveTechnicianIdForRating($maintenanceRequest);
        if (! $technicianId) {
            return response()->json([
                'message' => 'ยังไม่มีการมอบหมายเจ้าหน้าที่ในงานนี้ จึงยังให้คะแนนไม่ได้',
            ], 422);
        }

        // 6) validate + rule: ถ้าให้ 1–2 ดาว ต้องกรอก comment
        $validator = Validator::make($request->all(), $this->ratingRules())
            ->after(fn ($v) => $this->requireCommentForLowScore($v));

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ข้อมูลไม่ถูกต้อง',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // 7) สร้าง rating
        $rating = MaintenanceRating::create([
            'maintenance_request_id' => $maintenanceRequest->id,
            'rater_id'               => $user->id,
            'technician_id'          => $technicianId,
            'score'                  => $data['score'],
            'comment'                => $data['comment'] ?? null,
        ]);

        // 8) Auto-close งานที่ยัง 'resolved' หลังผู้แจ้งให้คะแนน (เหมือนฝั่ง web)
        $maintenanceRequest->refresh();
        $autoClosed = false;
        if ($maintenanceRequest->status === MaintenanceRequest::STATUS_RESOLVED) {
            try {
                app(MaintenanceTransitionService::class)->applyTransition(
                    $maintenanceRequest,
                    ['status' => MaintenanceRequest::STATUS_CLOSED, 'note' => 'ปิดงานอัตโนมัติหลังผู้แจ้งให้คะแนนเรียบร้อย'],
                    $user->id
                );
                $autoClosed = true;
            } catch (\Throwable $e) {
                Log::warning("Auto-close failed for Request ID {$maintenanceRequest->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => $autoClosed ? 'บันทึกคะแนนและปิดงานเรียบร้อย' : 'บันทึกคะแนนเรียบร้อย',
            'data'    => $rating,
        ], 201);
    }
}
