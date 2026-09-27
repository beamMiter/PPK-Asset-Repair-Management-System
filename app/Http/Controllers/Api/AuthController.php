<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginAttempt;
use App\Services\PasswordChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    protected function abilitiesFor(User $user): array
    {
        if ($user->isSupervisor()) {
            return [
                'manage-users', 'assets.read', 'assets.write', 'maintenance.request', 'maintenance.manage', 'stats.view'
            ];
        }

        if ($user->isWorker()) {
            return [
                'assets.read', 'maintenance.work', 'stats.view'
            ];
        }

        return [
            'assets.read', 'maintenance.request', 'stats.view'
        ];
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'citizen_id'  => ['required','digits:13'],
            'password'    => ['required','string'],
            'device_name' => ['nullable','string','max:120'],
        ]);

        // the same checks as the browser form (App\Services\LoginAttempt)
        $attempt = LoginAttempt::for($data['citizen_id'], (string) $request->ip());

        if (($seconds = $attempt->waitSeconds()) > 0) {
            return response()->json([
                'message' => LoginAttempt::lockedMessage($seconds),
                'code'    => 'too_many_attempts',
            ], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => $seconds]);
        }

        $user = $attempt->userWithPassword($data['password']);

        if (! $user) {
            $attempt->fail();
            return response()->json([
                'message' => LoginAttempt::WRONG,
                'code'    => 'invalid_credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isSuspended()) {
            $attempt->fail();
            return response()->json([
                'message' => \App\Http\Middleware\EnsureAccountIsActive::MESSAGE,
                'code'    => 'account_suspended',
            ], Response::HTTP_FORBIDDEN);
        }

        $attempt->succeed();

        $device    = $data['device_name'] ?? ('api-'.Str::random(6));
        $abilities = $this->abilitiesFor($user);
        $token     = $user->createToken($device, $abilities);

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => [
                'id'         => $user->id,
                'name'       => $user->name,
                'citizen_id' => $user->citizen_id,
                'email'      => $user->email,
                'role'       => $user->role ?? null,
                'abilities'  => $abilities,
                // true: every other call answers 403 `password_change_required` until the person changes it on the profile page
                'must_change_password' => (bool) $user->must_change_password,
            ],
        ], Response::HTTP_CREATED);
    }

    public function tokens(Request $request)
    {
        $user = $request->user();
        $list = $user->tokens()->latest('id')->get()->map(fn($t) => [
            'id'           => $t->id,
            'name'         => $t->name,
            'abilities'    => $t->abilities,
            'last_used_at' => $t->last_used_at,
            'created_at'   => $t->created_at,
        ]);

        return response()->json(['data' => $list]);
    }

    public function revokeToken(Request $request, string $tokenId)
    {
        $user  = $request->user();
        $token = $user->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            return response()->json([
                'message' => 'ไม่พบโทเค็น',
                'code'    => 'token_not_found',
            ], Response::HTTP_NOT_FOUND);
        }

        $token->delete();

        return response()->json(['message' => 'ยกเลิกโทเค็นเรียบร้อยแล้ว']);
    }

    /**
     * Change my own password (a bearer token). It is also the way out for somebody an admin gave a password to: every other call answers
     * 403 `password_change_required` until this succeeds. The token used here stays valid; every other token ends.
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        $token = $request->user()->currentAccessToken();
        $keepTokenId = $token instanceof PersonalAccessToken ? (int) $token->getKey() : null;

        PasswordChange::apply($request->user(), $data['password'], null, $keepTokenId);

        return response()->json(['message' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว', 'must_change_password' => false]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();                       // a call with a bearer token: that token ends
        } elseif ($request->hasSession()) {
            // Signed in by the browser's own session (a same-site call): there is no token to delete — it used to be
            // `->delete()` on a TransientToken, which has no such method, so this answered 500. The session ends instead.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'ออกจากระบบเรียบร้อยแล้ว']);
    }

    public function logoutAll(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'ยกเลิกโทเค็นทั้งหมดเรียบร้อยแล้ว']);
    }

    public function me(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'id'         => $u->id,
            'name'       => $u->name,
            'citizen_id' => $u->citizen_id,
            'email'      => $u->email,
            'role'       => $u->role ?? null,
            'abilities'  => $this->abilitiesFor($u),
        ]);
    }
}
