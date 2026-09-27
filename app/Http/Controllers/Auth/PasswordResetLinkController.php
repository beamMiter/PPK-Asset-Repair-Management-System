<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordRecovery;
use App\Support\PasswordResetMessage;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request: back to the form with a message for the browser, JSON for API-style
     * clients. The answer is the same for an address with an account and one without (see PasswordRecovery::requestLink).
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        PasswordRecovery::requestLink($request->string('email')->toString());

        return $request->expectsJson()
            ? response()->json(['status' => PasswordResetMessage::linkRequested()])
            : back()->with('status', PasswordResetMessage::linkRequested());
    }
}
