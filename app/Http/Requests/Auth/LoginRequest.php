<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // ✅ ใช้ citizen_id แทน email
            'citizen_id' => ['required', 'digits:13'],
            'password'   => ['required', 'string'],
        ];
    }

    /** Thai messages — the default validation strings are English and were the only thing shown. */
    public function messages(): array
    {
        return [
            'citizen_id.required' => 'กรุณากรอกเลขบัตรประชาชน',
            'citizen_id.digits'   => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก (ไม่ต้องมีเว้นวรรคหรือขีด)',
            'password.required'   => 'กรุณากรอกรหัสผ่าน',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Only after the password is right, so this message cannot be used to probe which accounts exist.
        // It still counts as an attempt, so it is no cheaper to guess against than any other account.
        $suspended = User::where('citizen_id', $this->input('citizen_id'))->first();
        if ($suspended && $suspended->isSuspended() && Hash::check((string) $this->input('password'), (string) $suspended->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'citizen_id' => \App\Http\Middleware\EnsureAccountIsActive::MESSAGE,
            ]);
        }

        if (! Auth::attempt(
            $this->only('citizen_id', 'password'),
            $this->boolean('remember')
        )) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                // ✅ ผูก error กับช่อง citizen_id
                'citizen_id' => 'เลขบัตรประชาชนหรือรหัสผ่านไม่ถูกต้อง',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        // Say it is a temporary lock-out, not "wrong password" — otherwise a locked user keeps
        // retrying the right password and thinks login is broken.
        throw ValidationException::withMessages([
            'citizen_id' => "พยายามเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอ {$seconds} วินาทีแล้วลองใหม่",
        ]);
    }

    public function throttleKey(): string
    {
        // ✅ ใช้ citizen_id + IP เป็น key
        return Str::transliterate(
            Str::lower((string) $this->input('citizen_id')) . '|' . $this->ip()
        );
    }
}
