<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PasswordRecovery;
use App\Support\PasswordResetMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Symfony\Component\HttpFoundation\Response;

/** The same two steps as the browser pages (App\Services\PasswordRecovery), in JSON. */
class PasswordResetController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => ['required','email']]);

        PasswordRecovery::requestLink($request->string('email')->toString());

        // 200 whether or not the address has an account: a different answer would list the addresses that do
        return response()->json([
            'message' => PasswordResetMessage::linkRequested(),
        ], Response::HTTP_OK);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => ['required','string'],
            'email'    => ['required','email'],
            'password' => ['required','confirmed', PasswordRule::defaults()],
        ]);

        $status = PasswordRecovery::reset(
            $request->only('email','password','password_confirmation','token')
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => PasswordResetMessage::resetDone(),
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => PasswordResetMessage::resetRefused($status),
            'code'    => 'password_reset_failed',
        ], Response::HTTP_BAD_REQUEST);
    }
}
