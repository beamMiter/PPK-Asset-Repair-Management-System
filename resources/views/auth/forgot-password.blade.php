@extends('layouts.auth')
@section('title', 'Forgot password')

@section('content')
    <p class="text-sm text-slate-600">
        ลืมรหัสผ่านใช่ไหม? ให้กรอกอีเมลที่ผูกกับบัญชีของคุณ ระบบจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปทางอีเมลนั้น
        (แม้ว่าคุณจะใช้เลขบัตรประชาชนในการเข้าสู่ระบบก็ตาม)
    </p>
    <p class="mt-2 text-sm text-slate-500">
        บัญชีที่ยังไม่มีอีเมลไม่สามารถใช้วิธีนี้ได้ — ให้ติดต่อผู้ดูแลระบบเพื่อตั้งรหัสผ่านใหม่ให้
    </p>

    @if (session('status'))
        <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-4 space-y-4">
        @csrf

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email"
                   type="email"
                   name="email"
                   value="{{ old('email') }}"
                   required
                   autofocus
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2
                          focus:border-[#0E2B51] focus:ring-[#0E2B51]" maxlength="255">
            @error('email')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-2 flex items-center justify-between">
            <a href="{{ route('login') }}" class="text-sm text-[#0E2B51] hover:underline">
                Back to login
            </a>

            <button class="h-11 px-5 rounded-lg bg-[#0E2B51] text-white font-medium
                           hover:opacity-95 focus:ring-2 focus:ring-offset-2 focus:ring-[#0E2B51]">
                Email Password Reset Link
            </button>
        </div>
    </form>
@endsection
