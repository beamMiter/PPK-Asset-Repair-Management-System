<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordRecovery;
use App\Support\PasswordResetMessage;
use Illuminate\View\View;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'request' => $request,
        ]);
    }

    /**
     * Handle an incoming new password request: to the login page for the browser, JSON for API-style clients.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = PasswordRecovery::reset(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [PasswordResetMessage::resetRefused($status)],
            ]);
        }

        return $request->expectsJson()
            ? response()->json(['status' => PasswordResetMessage::resetDone()])
            : redirect()->route('login')->with('toast', Toast::success(PasswordResetMessage::resetDone(), 3200));
    }
}
