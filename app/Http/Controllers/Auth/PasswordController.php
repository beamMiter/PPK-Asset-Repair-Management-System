<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        // A password is changed because another person may have it: their sessions, "remember me" cookies and API tokens end
        // here too (this device stays signed in).
        $wasForced = PasswordChange::apply($request->user(), $validated['password'], $request->session()->getId());

        // asked to change it before anything else: now there is something else to do
        return $wasForced
            ? redirect()->route('dashboard')->with('toast', \App\Support\Toast::success('เปลี่ยนรหัสผ่านเรียบร้อยแล้ว เริ่มใช้งานระบบได้เลย', 3200))
            : back()->with('status', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
}
