<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponseWithToast
{
    /**
     * คืนค่า Response พร้อม Toast
     * (สำหรับ Web จะได้ Session Redirect, สำหรับ API จะได้ JSON)
     *
     * @param Request $request
     * @param array $toast ข้อมูล Toast (type, message, position, timeout, size)
     * @param mixed $webRedirect RedirectResponse สำหรับ Web
     * @param array $jsonPayload ข้อมูลเสริมสำหรับการคืนค่า Json
     * @param int $status HTTP Status Code สำหรับ Json
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function respondWithToast(
        Request $request,
        array $toast,
        $webRedirect,
        array $jsonPayload = [],
        int $status = Response::HTTP_OK
    ) {
        $toastData = [
            'type'     => $toast['type']     ?? 'info',
            'message'  => $toast['message']  ?? '',
            'position' => $toast['position'] ?? 'tc',
            'timeout'  => $toast['timeout']  ?? 2000,
            'size'     => $toast['size']     ?? 'sm',
        ];

        if (!$request->expectsJson()) {
            return $webRedirect->with('toast', $toastData);
        }

        $payload = array_merge($jsonPayload, ['toast' => $toastData]);
        return response()->json($payload, $status);
    }

    /**
     * The text of a failure that is fit to show a user. Our own refusals — abort(409, …), the disposed-asset check (code 101) —
     * are written for them; anything else (SQL with table names, a PHP error) is not: it is logged and replaced.
     */
    protected function friendlyMessage(\Throwable $e, string $fallback = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง'): string
    {
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface || (int) $e->getCode() === 101) {
            return $e->getMessage();
        }

        \Illuminate\Support\Facades\Log::error('[' . static::class . '] ' . get_class($e) . ': ' . $e->getMessage(), [
            'file' => $e->getFile(), 'line' => $e->getLine(),
        ]);

        return $fallback;
    }
}
