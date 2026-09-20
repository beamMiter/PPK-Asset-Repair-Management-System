<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * A private file is exactly as visible as the thing it is attached to: a request's files follow the request
     * policy (reporter, assigned team, admin team), an asset's files follow the asset policy. Before this, any
     * signed-in user could read any private attachment just by changing the id in the URL.
     */
    private function authorizeDownload(User $user, Attachment $attachment): void
    {
        $target = $attachment->attachable;

        if ($target instanceof MR || $target instanceof Asset) {
            Gate::forUser($user)->authorize('view', $target);

            return;
        }

        // orphaned / unknown target: only whoever uploaded it, or an admin
        abort_unless($user->isAdmin() || (int) $attachment->uploaded_by === (int) $user->id, 403);
    }

    public function show(Request $request, Attachment $attachment)
    {
        if (!$attachment->is_private) {
            $publicUrl = $attachment->url;
            abort_unless($publicUrl, 404);
            return redirect()->away($publicUrl);
        }

        $this->authorizeDownload($request->user(), $attachment);

        // retention: an attachment past its expiry is no longer served
        abort_if($attachment->expires_at && $attachment->expires_at->isPast(), 410, 'ไฟล์แนบนี้หมดอายุแล้ว');

        // path / disk / mime / size live on the linked File, not on Attachment —
        // reading them off $attachment made every private download 404.
        $file = $attachment->file;
        abort_unless($file, 404);

        $disk = $file->disk ?: 'local';
        $path = $file->path;

        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        $stream = Storage::disk($disk)->readStream($path);
        abort_unless($stream !== false, 404);

        $mime     = $file->mime ?: 'application/octet-stream';
        $size     = $file->size ?: null;
        $filename = $attachment->filename;
        $download = $request->boolean('download', false);

        $headers = [
            'Content-Type'            => $mime,
            'X-Content-Type-Options'  => 'nosniff',
            'Cache-Control'           => 'private, max-age=0, must-revalidate',
        ];

        if ($size) {
            $headers['Content-Length'] = (string) $size;
        }

        $disposition = $download ? 'attachment' : 'inline';
        $headers['Content-Disposition'] = $disposition.'; filename="'.addslashes($filename).'"';

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    public function store(Request $request)
    {
        return response()->json([
            'error'   => 'deprecated',
            'message' => 'This endpoint is no longer supported. Use MaintenanceRequestController@uploadAttachmentFromBlade or the API variant.',
        ], 410);
    }

    public function destroy(Attachment $attachment)
    {
        return response()->json([
            'error'   => 'deprecated',
            'message' => 'This endpoint is no longer supported. Use maintenance.requests.attachments.destroy.',
        ], 410);
    }
}
