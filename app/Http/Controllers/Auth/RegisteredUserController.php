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
