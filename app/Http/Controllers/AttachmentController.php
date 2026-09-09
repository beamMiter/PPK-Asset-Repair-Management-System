<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\MaintenanceRequest as MR;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'error'   => 'deprecated',
            'message' => 'This endpoint is no longer supported. Use /maintenance/requests/{req} and its attachments relation.',
        ], 410);
    }

    public function indexByRequest(MR $req)
    {
        $list = $req->attachments()
            ->orderByDesc('created_at')
            ->paginate(20);

        $list->getCollection()->transform(function (Attachment $a) {
            return [
                'id'            => $a->id,
                'url'           => $a->url,
                'filename'      => $a->filename,
                'mime'          => $a->mime,
                'size'          => $a->size,
                'is_private'    => $a->is_private,
                'caption'       => $a->caption,
                'alt_text'      => $a->alt_text,
                'order_column'  => $a->order_column,
                'uploaded_by'   => $a->uploaded_by,
                'created_at'    => $a->created_at,
            ];
        });

        return response()->json($list);
    }

    public function show(Request $request, Attachment $attachment)
    {
        if (!$attachment->is_private) {
            $publicUrl = $attachment->url;
            abort_unless($publicUrl, 404);
            return redirect()->away($publicUrl);
        }

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

    public function storeForRequest(Request $request, MR $req)
    {
        return response()->json([
            'error'   => 'deprecated',
            'message' => 'This endpoint is no longer supported. Use maintenance.requests.attachments.upload.',
        ], 410);
    }
}
