<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request)
    {
        try {
            $request->authenticate();
            $request->session()->regenerate();

            session()->put('toast', [
                'type'     => 'success',
                'message'  => 'เข้าสู่ระบบสำเร็จ',
                'position' => 'br',
                'timeout'  => 2800,
            ]);

            // API clients get 204; a browser is redirected below
            if ($request->expectsJson()) {
                return response()->noContent();
            }

            $user = $request->user();

            // === เงื่อนไข redirect ===
            // ถ้าเป็น Member (computer_officer) → dashboard ปกติ
            if (method_exists($user, 'isMember') && $user->isMember()) {
                return redirect()->intended('/dashboard');
            }

            // ทุกตำแหน่งอื่น → ไปหน้า My Jobs
            return redirect('/repair/my-jobs');

        } catch (ValidationException $e) {
            return back()
                ->with('toast', [
                    'type'     => 'error',
                    // the real reason: wrong credentials vs. temporarily locked out
                    'message'  => $e->validator->errors()->first() ?: 'เลขบัตรประชาชนหรือรหัสผ่านไม่ถูกต้อง',
                    'position' => 'tr',
                    'timeout'  => 4000,
                ])
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect('/')->with('toast', [
            'type'     => 'info',
            'message'  => 'ออกจากระบบเรียบร้อยแล้ว',
            'position' => 'tr',
            'timeout'  => 2400,
        ]);
    }
}
