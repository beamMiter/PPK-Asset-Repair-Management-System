<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActiveLogins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // A password is changed because another person may have it: their sessions, "remember me" cookies and API tokens end
        // here too (this device stays signed in).
        ActiveLogins::endAll($user, $request->session()->getId());

        return back()->with('status', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
}
