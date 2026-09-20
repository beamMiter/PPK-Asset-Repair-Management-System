<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Exceptions\Handler as BaseHandler;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends BaseHandler
{
    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Permission denied: show toast and redirect back or to dashboard
        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            $message = $e->getMessage() ?: 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'toast' => [
                        'type' => 'error',
                        'message' => $message,
                        'timeout' => 3500,
                        'position' => 'tc',
                        'size' => 'md',
                    ],
                ], 403);
            }

            // Redirect back or to dashboard with toast
            $redirectTo = $request->headers->get('referer')
                ? redirect()->back()
                : redirect()->route('dashboard');

            return $redirectTo->with('toast', [
                'type' => 'error',
                'message' => $message,
                'timeout' => 3500,
                'position' => 'tc',
                'size' => 'md',
            ]);
        }
        return parent::render($request, $e);
    }
}
