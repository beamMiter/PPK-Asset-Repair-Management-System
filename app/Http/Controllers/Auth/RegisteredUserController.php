<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-registration for the TRIAL system (local / internal testing): a 13-digit citizen ID, a name and a password make a plain member
 * account, and an admin sets the role afterwards. Nothing verifies that the citizen ID belongs to whoever typed it - on purpose, for now.
 * The real login will be built on the hospital's personnel database once this system is connected to it; this controller is what
 * that replaces. Do not expose a trial instance to the public internet.
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],

            'citizen_id'  => [
                'required',
                'digits:13',
                'unique:users,citizen_id',
            ],

            'email'       => [
                'nullable',
                'string',
                'lowercase',
                'email:rfc',
                'max:255',
                'unique:users,email',
            ],

            'password'    => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name'        => $validated['name'],
            'citizen_id'  => $validated['citizen_id'],
            'email'       => $validated['email'] ?? null,
            'password'    => Hash::make($validated['password']),
        ]);

        event(new Registered($user));
        Auth::login($user);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->intended(route('dashboard'));
    }
}
