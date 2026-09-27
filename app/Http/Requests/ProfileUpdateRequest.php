<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function failedAuthorization()
    {
        session()->flash('toast', [
            'type'     => 'error',
            'message'  => 'คุณไม่มีสิทธิ์ดำเนินการนี้',
            'position' => 'tc',
            'timeout'  => 3800,
            'size'     => 'md',
        ]);

        throw new AuthorizationException('This action is unauthorized.');
    }

    public function stopOnFirstFailure(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name  = $this->input('name');
        $email = $this->input('email');

        $this->merge([
            'name'  => is_string($name)  ? trim($name) : $name,
            'email' => is_string($email) ? trim(strtolower($email)) : $email,
        ]);
    }

    public function rules(): array
    {
        $userId = (int) ($this->user()?->id ?? 0);

        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($userId),
            ],
            // แก้จาก integer/exists:id เป็น string/exists:code เพื่อให้ตรงกับ Model ที่พี่ใช้
            'department' => ['nullable', 'string', 'exists:departments,code'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $first = $validator->errors()->first() ?: 'ข้อมูลไม่ถูกต้อง';
        session()->flash('toast', [
            'type'     => 'error',
            'message'  => $first,
            'position' => 'tc',
            'timeout'  => 3800,
            'size'     => 'md',
        ]);

        throw (new ValidationException($validator))
            ->redirectTo($this->getRedirectUrl());
    }

    public function attributes(): array
    {
        return [
            'name'        => 'ชื่อ',
            'email'       => 'อีเมล',
            'department'  => 'แผนก',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'กรุณากรอกชื่อ',
            'name.max'          => 'ชื่อต้องไม่เกิน :max ตัวอักษร',
            'email.required'    => 'กรุณากรอกอีเมล',
            'email.email'       => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.max'          => 'อีเมลต้องไม่เกิน :max ตัวอักษร',
            'email.unique'      => 'อีเมลนี้ถูกใช้ไปแล้ว',
            'department.exists' => 'แผนกที่เลือกไม่ถูกต้อง',
        ];
    }
}
