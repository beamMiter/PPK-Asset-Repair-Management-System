<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest as MR;
use App\Services\MaintenanceTransitionService;
use App\Support\Toast;
use App\Traits\ApiResponseWithToast;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        $user = $request->user();
        $actorId = $user ? $user->id : null;
        $isTeam = $user && ($user->isAdmin() || $user->isSupervisor() || $user->isTechnician());

        // Every member of the staff gets to the step check below: the web's acknowledge / accept / reject / send-back buttons are open to
        // all of them, and a request nobody is on yet (every new one) could not be taken from the app when only admins and the people
        // already on it came in. What each move asks is decided by its own ability there. A member is turned away before the body is read.
        if (! $isTeam) {
            throw new AuthorizationException('อนุญาตเฉพาะเจ้าหน้าที่เท่านั้น');
        }

        // Naming the person in charge stays with those who run the job: an admin, a supervisor, or somebody already on it
        if ($request->has('technician_id')) {
            Gate::authorize('transition', $req);
        }

        $rules = [
            'status' => $isTeam
                ? ['bail', 'required', Rule::in(array_merge([MR::STATUS_PENDING], array_keys(MR::statusLabels())))]
                : ['prohibited'],
            'note' => ['nullable', 'string', 'max:2000'],
            'technician_id' => array_values(array_filter([
                Rule::prohibitedIf(! $isTeam),
                'nullable', 'integer', 'exists:users,id',
            ])),
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $msg = 'ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';

            return $this->respondWithToast($request, Toast::warning($msg, 2200), redirect()->back()->withErrors($validator)->withInput(), ['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // each step asks what its own button asks: open to all staff (acknowledge, accept, reject, send back), the people on the job
        // (hold, resume, resolve), only the reporter (approve), …
        $target = $validator->validated()['status'] ?? null;
        if ($target && $target !== $req->status) {
            Gate::authorize('moveTo', [$req, $target]);
        }

        try {
            $this->transitionService->applyTransition($req, $validator->validated(), $actorId);

            return $this->respondWithToast($request, Toast::success('อัปเดตสถานะใบงานเรียบร้อยแล้ว', 1800), redirect()->route('maintenance.requests.show', $req->id), ['data' => $req->fresh(['technician', 'assignments.user'])]);
        } catch (\Exception $e) {
            $msg = $this->friendlyMessage($e);

            return $this->respondWithToast($request, Toast::warning($msg, 3000), redirect()->route('maintenance.requests.show', $req->id), ['message' => $msg], $this->statusForException($e));
        }
    }

    /**
     * Refuse an action nobody may take *before* its body is validated: a 422 for a member who could not have done it anyway
     * tells him what a valid request looks like. Null when the action is allowed.
     */
    protected function refuseUnless(Request $request, MR $req, string $ability)
    {
        if (Gate::allows($ability, $req)) {
            return null;
        }

        return $this->respondWithToast($request, Toast::warning('คุณไม่มีสิทธิ์ทำรายการนี้', 2200), redirect()->route('maintenance.requests.show', $req->id), ['message' => 'คุณไม่มีสิทธิ์ทำรายการนี้'], 403);
    }

    /**
     * Map a caught exception to an HTTP status. Rule rejections in the
     * service use abort(409, ...), which throws an HttpException whose
     * getCode() is 0 — read getStatusCode(); fall back to getCode() for
     * plain exceptions that carry a meaningful one.
     */
    protected function statusForException(\Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
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
        } catch (AuthorizationException $e) {
            return $this->respondWithToast($request, Toast::warning('คุณไม่มีสิทธิ์ทำรายการนี้', 2200), redirect()->route('maintenance.requests.show', $req->id), ['message' => 'คุณไม่มีสิทธิ์ทำรายการนี้'], 403);
        } catch (\Exception $e) {
            Log::error('[MaintenanceTransitionController] action failed', [
                'mr_id' => $req->id,
                'user_id' => $actorId,
                'action' => $status,
                'error' => $e->getMessage(),
            ]);
            $msg = $this->friendlyMessage($e);

            return $this->respondWithToast($request, Toast::warning($msg, 2200), redirect()->route('maintenance.requests.show', $req->id), ['message' => $msg], $this->statusForException($e));
        }
    }

    public function acknowledgeCase(Request $request, MR $req)
    {
        return $this->handleAction($request, $req, 'acknowledge', MR::STATUS_ACKNOWLEDGED, 'รับทราบแล้ว');
    }

    public function rejectCase(Request $request, MR $req)
    {
        if ($refused = $this->refuseUnless($request, $req, 'reject')) {
            return $refused;
        }

        $data = $request->validate([
            'reject_reason' => ['nullable', 'string', 'max:2000'],
            'remark' => ['nullable', 'string', 'max:2000'], // fallback
        ]);

        $reason = trim($data['reject_reason'] ?? $data['remark'] ?? '') ?: 'เจ้าหน้าที่ไม่รับเรื่อง';

        return $this->handleAction($request, $req, 'reject', MR::STATUS_REJECTED, 'ไม่รับเรื่องเรียบร้อยแล้ว', ['note' => $reason]);
    }

    /**
     * "ส่งกลับแก้ไข": before the job is taken on, hand it back to whoever filed it. The reason (what to fix, and how) is required,
     * more strictly than for a cancellation, because it is the whole point: it is what the reporter acts on. The request keeps its
     * number and its history; "ไม่รับเรื่อง" (rejectCase) is the different, final case.
     */
    public function sendBackCase(Request $request, MR $req)
    {
        if ($refused = $this->refuseUnless($request, $req, 'sendBack')) {
            return $refused;
        }

        $data = $request->validate(['return_reason' => ['required', 'string', 'max:2000']], [], ['return_reason' => 'สิ่งที่ต้องแก้ไข']);

        return $this->handleAction($request, $req, 'sendBack', MR::STATUS_RETURNED, 'ส่งกลับให้ผู้แจ้งแก้ไขเรียบร้อยแล้ว', ['note' => trim($data['return_reason'])]);
    }

    /** The reporter has fixed a request that was sent back and sends it again: it goes back to the queue under the same number. */
    public function resubmitCase(Request $request, MR $req)
    {
        return $this->handleAction($request, $req, 'resubmit', MR::STATUS_PENDING, 'ส่งใบงานอีกครั้งเรียบร้อยแล้ว');
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
        if ($refused = $this->refuseUnless($request, $req, 'hold')) {
            return $refused;
        }

        $data = $request->validate(['note' => ['required', 'string', 'max:1000']], [], ['note' => 'เหตุผลในการพักชั่วคราว']);

        return $this->handleAction($request, $req, 'hold', MR::STATUS_ON_HOLD, 'หยุดการซ่อมบำรุงชั่วคราวเรียบร้อยแล้ว', ['note' => trim($data['note'])]);
    }

    public function resumeCase(Request $request, MR $req)
    {
        return $this->handleAction($request, $req, 'resume', MR::STATUS_IN_PROGRESS, 'กลับมาดำเนินการต่อแล้ว');
    }

    public function resolveCase(Request $request, MR $req)
    {
        if ($refused = $this->refuseUnless($request, $req, 'resolve')) {
            return $refused;
        }

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

        } catch (AuthorizationException $e) {
            return $this->respondWithToast($request, Toast::warning('คุณไม่มีสิทธิ์ทำรายการนี้', 2200), redirect()->route('maintenance.requests.show', $req->id), ['message' => 'คุณไม่มีสิทธิ์ทำรายการนี้'], 403);
        } catch (\Exception $e) {
            $msg = $this->friendlyMessage($e);

            return $this->respondWithToast($request, Toast::warning($msg, 2200), redirect()->route('maintenance.requests.show', $req->id), ['message' => $msg], $this->statusForException($e));
        }
    }

    public function cancelCase(Request $request, MR $req)
    {
        if ($refused = $this->refuseUnless($request, $req, 'cancel')) {
            return $refused;
        }

        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $note = trim($data['cancel_reason'] ?? '') ?: 'ยกเลิกโดยผู้ใช้งาน';

        return $this->handleAction($request, $req, 'cancel', MR::STATUS_CANCELLED, 'ยกเลิกการซ่อมบำรุงเรียบร้อยแล้ว', ['note' => $note]);
    }
}
