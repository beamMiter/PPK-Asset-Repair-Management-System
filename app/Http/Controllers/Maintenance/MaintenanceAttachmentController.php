<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MaintenanceRequest as MR;
use App\Models\Attachment;
use App\Services\MaintenanceAttachmentService;
use App\Traits\ApiResponseWithToast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Support\Toast;

class MaintenanceAttachmentController extends Controller
{
    use ApiResponseWithToast;

    protected MaintenanceAttachmentService $attachmentService;

    public function __construct(MaintenanceAttachmentService $attachmentService)
    {
        $this->attachmentService = $attachmentService;
    }

    public function uploadAttachmentFromBlade(Request $request, MR $req)
    {
        Gate::authorize('attach', $req);

        $currentCount = $req->attachments()->count();
        if ($currentCount >= 3) {
            return $this->respondWithToast($request, Toast::warning('สามารถแนบไฟล์ได้สูงสุด 3 ไฟล์ต่อใบงาน', 2500), back(), ['message' => 'สามารถแนบไฟล์ได้สูงสุด 3 ไฟล์ต่อใบงาน'], 422);
        }

        $maxKb = config('uploads.max_kb', 10240);
        $mimetypes = implode(',', config('uploads.mimetypes', ['image/*', 'application/pdf']));
        
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'files'      => ['required', 'array', 'max:3'],
            'files.*'    => ['file', 'max:' . $maxKb, 'mimetypes:' . $mimetypes],
            'is_private' => ['nullable', 'boolean'],
            'captions'   => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return $this->respondWithToast($request, Toast::warning($validator->errors()->first(), 3000), back(), ['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        try {
            $attachments = $this->attachmentService->attachFiles(
                $req,
                $validated['files'],
                $validated['captions'] ?? [],
                optional($request->user())->id,
                'web',
                (bool) ($validated['is_private'] ?? false)
            );

            if (empty($attachments)) {
                return $this->respondWithToast($request, Toast::warning('อัปโหลดไฟล์ไม่สำเร็จ', 2500), back(), [], 422);
            }

            return $this->respondWithToast($request, Toast::success('อัปโหลดไฟล์แนบแล้ว', 1800), back(), ['data' => $req->fresh('attachments.file')]);
        } catch (\Exception $e) {
            Log::error('[MaintenanceAttachmentController] upload failed', ['error' => $e->getMessage()]);
            return $this->respondWithToast($request, Toast::warning('เกิดข้อผิดพลาดในการอัปโหลดไฟล์', 3000), back(), [], 500);
        }
    }

    public function destroyAttachment(Request $request, MR $req, Attachment $attachment)
    {
        Gate::authorize('deleteAttachment', $req);

        abort_unless($attachment->attachable_type === MR::class && (int) $attachment->attachable_id === (int) $req->id, 404);

        try {
            $this->attachmentService->detachFiles($req, [$attachment->id], Auth::id());
            return $this->respondWithToast($request, Toast::success('ลบไฟล์แนบแล้ว', 1600), back(), ['deleted' => true]);
        } catch (\Exception $e) {
            Log::error('[MaintenanceAttachmentController] destroy failed', ['error' => $e->getMessage()]);
            return $this->respondWithToast($request, Toast::warning('เกิดข้อผิดพลาดในการลบไฟล์', 3000), back(), [], 500);
        }
    }
}
