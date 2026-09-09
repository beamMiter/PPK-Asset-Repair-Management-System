<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRating;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MaintenanceRatingApiController extends Controller
{
    /**
     * กำหนดช่วงเวลาที่อนุญาตให้ให้คะแนน (วัน)
     */
    protected int $ratingDeadlineDays = 30;

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
            ->filter(function (MaintenanceRequest $req) {
                return $this->withinRatingWindow($req);
            })
            ->values(); // reset index

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

        // 5) validate + rule: ถ้าให้ 1–2 ดาว ต้องกรอก comment
        $validator = Validator::make($request->all(), [
            'score'   => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $validator->after(function ($v) {
            $data    = $v->getData();
            $score   = isset($data['score']) ? (int) $data['score'] : null;
            $comment = trim($data['comment'] ?? '');

            if ($score !== null && $score <= 2 && $comment === '') {
                $v->errors()->add('comment', 'ถ้าให้ 1–2 ดาว กรุณาระบุความคิดเห็นเพิ่มเติม');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ข้อมูลไม่ถูกต้อง',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // 6) สร้าง rating
        $rating = MaintenanceRating::create([
            'maintenance_request_id' => $maintenanceRequest->id,
            'rater_id'               => $user->id,
            'technician_id'          => $maintenanceRequest->technician_id,
            'score'                  => $data['score'],
            'comment'                => $data['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'บันทึกคะแนนเรียบร้อย',
            'data'    => $rating,
        ], 201);
    }

    /**
     * ใช้ logic เดียวกับฝั่ง web: closed_at > resolved_at > completed_date
     */
    protected function withinRatingWindow(MaintenanceRequest $maintenanceRequest): bool
    {
        $base = $maintenanceRequest->closed_at
            ?? $maintenanceRequest->resolved_at
            ?? $maintenanceRequest->completed_date;

        if (! $base) {
            return false;
        }

        // Carbon 3: diffInDays() is signed — $base is in the past, so pass
        // `true` for an absolute day count (Carbon 2's default behaviour).
        return (int) now()->diffInDays($base, true) <= $this->ratingDeadlineDays;
    }
}
