<?php

namespace App\Http\Requests\Auth;

use App\Services\LoginAttempt;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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
     * Attempt to authenticate the request's credentials (the checks themselves live in App\Services\LoginAttempt, shared with
     * the API login).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $attempt = LoginAttempt::for((string) $this->input('citizen_id'), (string) $this->ip());

        $this->ensureIsNotRateLimited($attempt);

        $user = $attempt->userWithPassword((string) $this->input('password'));

        if (! $user) {
            $attempt->fail();

            throw ValidationException::withMessages([
                // ✅ ผูก error กับช่อง citizen_id
                'citizen_id' => LoginAttempt::WRONG,
            ]);
        }

        // Only after the password is right, so this message cannot be used to probe which accounts exist.
        // It still counts as an attempt, so it is no cheaper to guess against than any other account.
        if ($user->isSuspended()) {
            $attempt->fail();

            throw ValidationException::withMessages([
                'citizen_id' => \App\Http\Middleware\EnsureAccountIsActive::MESSAGE,
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
        $attempt->succeed();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function ensureIsNotRateLimited(LoginAttempt $attempt): void
    {
        $seconds = $attempt->waitSeconds();

        if ($seconds <= 0) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'citizen_id' => LoginAttempt::lockedMessage($seconds),
        ]);
    }
}
