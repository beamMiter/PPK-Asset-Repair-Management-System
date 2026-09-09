<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceRequest as MR;
use App\Services\MaintenanceTransitionService;
use App\Traits\ApiResponseWithToast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use App\Support\Toast;

class MaintenanceTransitionController extends Controller
{
    use ApiResponseWithToast;

    protected MaintenanceTransitionService $transitionService;

    public function __construct(MaintenanceTransitionService $transitionService)
    {
        $this->transitionService = $transitionService;
    }

    public function transition(Request $request, MR $req)
    {
        Gate::authorize('transition', $req);

        $user = $request->user();
        $actorId = $user ? $user->id : null;
        $isTeam = $user && ($user->isAdmin() || $user->isSupervisor() || $user->isTechnician());

        $rules = [
            'status' => $isTeam
                ? ['bail', 'required', Rule::in(array_merge([MR::STATUS_PENDING], array_keys(MR::statusLabels())))]
                : ['prohibited'],
            'note' => ['nullable', 'string', 'max:2000'],
            'technician_id' => array_values(array_filter([
                Rule::prohibitedIf(!$isTeam),
                'nullable', 'integer', 'exists:users,id',
            ])),
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $msg = 'ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
            return $this->respondWithToast($request, Toast::warning($msg, 2200), redirect()->back()->withErrors($validator)->withInput(), ['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->transitionService->applyTransition($req, $validator->validated(), $actorId);
            return $this->respondWithToast($request, Toast::success('อัปเดตสถานะใบงานเรียบร้อยแล้ว', 1800), redirect()->route('maintenance.requests.show', $req->id), ['data' => $req->fresh(['technician', 'assignments.user'])]);
        } catch (\Exception $e) {
            return $this->respondWithToast($request, Toast::warning($e->getMessage(), 3000), redirect()->route('maintenance.requests.show', $req->id), ['message' => $e->getMessage()], $this->statusForException($e));
        }
    }

    /**
     * Map a caught exception to an HTTP status. Rule rejections in the
     * service use abort(409, ...), which throws an HttpException whose
     * getCode() is 0 — read getStatusCode(); fall back to getCode() for
     * plain exceptions that carry a meaningful one.
     */
    protected function statusForException(\Throwable $e): int
    {
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return in_array($e->getCode(), [403, 409, 422], true) ? (int) $e->getCode() : 500;
    }

    protected function handleAction(Request $request, MR $req, string $gate, string $status, string $successMsg, array $additionalData = [])
    {
        $actorId = (int) Auth::id();
        try {
            Gate::authorize($gate, $req);
            
            $data = array_merge(['status' => $status], $additionalData);
            $this->transitionService->applyTransition($req, $data, $actorId);

            return $this->respondWithToast($request, Toast::success($successMsg, 1800), redirect()->route('maintenance.requests.show', $req->id), ['message' => $successMsg]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->respondWithToast($request, Toast::warning('คุณไม่มีสิทธิ์ทำรายการนี้', 2200), redirect()->route('maintenance.requests.show', $req->id), ['message' => 'คุณไม่มีสิทธิ์ทำรายการนี้'], 403);
        } catch (\Exception $e) {
            Log::error("[MaintenanceTransitionController] action failed", [
                'mr_id'   => $req->id,
                'user_id' => $actorId,
                'action'  => $status,
                'error'   => $e->getMessage()
            ]);
            return $this->respondWithToast($request, Toast::warning($e->getMessage(), 2200), redirect()->route('maintenance.requests.show', $req->id), ['message' => $e->getMessage()], $this->statusForException($e));
        }
    }

    public function acknowledgeCase(Request $request, MR $req)
    {
        return $this->handleAction($request, $req, 'acknowledge', MR::STATUS_ACKNOWLEDGED, 'รับทราบแล้ว');
    }

    public function rejectCase(Request $request, MR $req)
    {
        $data = $request->validate([
            'reject_reason' => ['nullable', 'string', 'max:2000'],
            'remark'        => ['nullable', 'string', 'max:2000'], // fallback
        ]);
        
        $reason = trim($data['reject_reason'] ?? $data['remark'] ?? '') ?: 'เจ้าหน้าที่ไม่รับเรื่อง';
        
        return $this->handleAction($request, $req, 'reject', MR::STATUS_REJECTED, 'ไม่รับเรื่องเรียบร้อยแล้ว', ['note' => $reason]);
    }

    public function acceptCase(Request $request, MR $req)
    {
        return $this->handleAction($request, $req, 'accept', MR::STATUS_ACCEPTED, 'รับเรื่องแล้ว');
    }

    public function startCase(Request $request, MR $req)
    {
        return $this->handleAction($request, $req, 'startWork', MR::STATUS_IN_PROGRESS, 'เริ่มดำเนินการแล้ว');
    }

    public function holdCase(Request $request, MR $req)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        return $this->handleAction($request, $req, 'hold', MR::STATUS_ON_HOLD, 'หยุดการซ่อมบำรุงชั่วคราวเรียบร้อยแล้ว', ['note' => trim($data['note'])]);
    }

    public function resumeCase(Request $request, MR $req)
    {
        return $this->handleAction($request, $req, 'resume', MR::STATUS_IN_PROGRESS, 'กลับมาดำเนินการต่อแล้ว');
    }

    public function resolveCase(Request $request, MR $req)
    {
        $data = $request->validate(['resolution_note' => ['required', 'string', 'max:2000']]);
        return $this->handleAction($request, $req, 'resolve', MR::STATUS_RESOLVED, 'ซ่อมบำรุงเสร็จสิ้นเรียบร้อยแล้ว ระบบเตรียมส่งให้ผู้แจ้งตรวจสอบ', ['note' => trim($data['resolution_note'])]);
    }

    public function closeCase(Request $request, MR $req)
    {
        $actorId = (int) Auth::id();
        try {
            Gate::authorize('close', $req);
            $this->transitionService->applyTransition($req, ['status' => MR::STATUS_CLOSED], $actorId);

            $successMsg = 'อนุมัติผลการซ่อมบำรุงเรียบร้อยแล้ว';

            if ($request->expectsJson()) {
                return $this->respondWithToast(
                    $request,
                    Toast::success($successMsg, 1800),
                    redirect()->route('maintenance.requests.show', $req->id),
                    ['message' => $successMsg]
                );
            }

            return redirect()
                ->route('maintenance.requests.show', $req->id)
                ->with('toast', Toast::success($successMsg, 1800))
                ->with('show_post_close_modal', true);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->respondWithToast($request, Toast::warning('คุณไม่มีสิทธิ์ทำรายการนี้', 2200), redirect()->route('maintenance.requests.show', $req->id), ['message' => 'คุณไม่มีสิทธิ์ทำรายการนี้'], 403);
        } catch (\Exception $e) {
            return $this->respondWithToast($request, Toast::warning($e->getMessage(), 2200), redirect()->route('maintenance.requests.show', $req->id), ['message' => $e->getMessage()], $this->statusForException($e));
        }
    }

    public function cancelCase(Request $request, MR $req)
    {
        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        
        $note = trim($data['cancel_reason'] ?? '') ?: 'ยกเลิกโดยผู้ใช้งาน';
        
        return $this->handleAction($request, $req, 'cancel', MR::STATUS_CANCELLED, 'ยกเลิกการซ่อมบำรุงเรียบร้อยแล้ว', ['note' => $note]);
    }
}
