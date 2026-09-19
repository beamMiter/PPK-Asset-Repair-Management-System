@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    @php
        // Logic การดึงรูปภาพและอักษรย่อให้เหมือนกับหน้าอื่นๆ ในระบบ
        $avatarMain = data_get($user, 'avatar_url');
        $avatarThumb = data_get($user, 'avatar_thumb_url');

        // ตัดอักษรย่อจากชื่อผู้ใช้ (2 ตัว)
        $name = trim((string) ($user->name ?? ''));
        $parts = preg_split('/\s+/u', $name) ?: [];
        $initials = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    @endphp

    <div class="w-full flex flex-col min-h-screen bg-white">

        {{-- Header Section: ชิดซ้าย-ขวา ปุ่มนิ่งไม่มีโมชั่น --}}
        <div class="sticky top-16 z-20 bg-white border-b border-slate-200">
            <div class="px-4 md:px-6 lg:px-8 py-5">
                <div class="flex items-center justify-between w-full">

                    {{-- ฝั่งซ้าย: My Profile (Icon เพียวๆ ไม่มีพื้นหลัง) --}}
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[32px] text-[#0F2D5C]">account_circle</span>
                        <div class="flex flex-col">
                            <h1 class="text-[18px] font-bold text-slate-900 leading-tight">My Profile</h1>
                            <p class="text-[13px] text-slate-500">จัดการข้อมูลส่วนตัวและหน่วยงานของคุณ</p>
                        </div>
                    </div>

                    {{-- ฝั่งขวา: ปุ่มแก้ไข (ไม่มี Shadow และไม่มี Motion เวลากด) --}}
                    <div class="flex items-center">
                        @if (Route::has('profile.edit'))
                            <x-ui.button :href="route('profile.edit')" icon="edit">แก้ไขโปรไฟล์</x-ui.button>
                        @endif
                    </div>

                </div>
            </div>
        </div>

        <div class="px-4 md:px-6 lg:px-8 py-10 max-w-5xl mx-auto w-full">

            @if (session('status'))
                <div
                    class="mb-8 rounded-md bg-emerald-50 border border-emerald-100 px-4 py-3 text-[13px] text-emerald-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    {{ session('status') }}
                </div>
            @endif

            {{-- ส่วนสรุปข้อมูลและ Avatar --}}
            <div class="flex items-center gap-6 mb-12">
                <div
                    class="h-20 w-20 rounded-full overflow-hidden flex items-center justify-center bg-emerald-600 shrink-0 border border-emerald-700/10">
                    @if ($avatarThumb || $avatarMain)
                        <img src="{{ $avatarThumb ?: $avatarMain }}" alt="{{ $user->name }}"
                            class="h-full w-full object-cover">
                    @else
                        <span class="text-white font-bold text-[28px] tracking-tighter">
                            {{ $initials }}
                        </span>
                    @endif
                </div>
                <div>
                    <h2 class="text-[24px] font-bold text-slate-900 leading-tight">{{ $user->name }}</h2>
                    <div class="flex flex-wrap items-center gap-3 mt-1.5">
                        <span class="text-[14px] text-slate-500 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[18px]">badge</span>
                            {{ $user->citizen_id ?? 'ไม่ระบุเลขบัตร' }}
                        </span>
                        <span class="h-1 w-1 bg-slate-300 rounded-full"></span>
                        <span class="text-[13px] text-emerald-600 font-bold uppercase tracking-widest">
                            {{ $user->role_label ?? $user->role }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- ส่วนตารางข้อมูล --}}
            <div class="space-y-6">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">info</span>
                    <h3 class="text-[16px] font-bold text-slate-800 uppercase tracking-tight">Account Information</h3>
                </div>

                <div class="w-full">
                    {{-- ชื่อ-นามสกุล --}}
                    <div
                        class="grid grid-cols-1 md:grid-cols-3 py-4 border-b border-slate-100 items-center hover:bg-slate-50/50 transition-colors">
                        <div class="text-[14px] font-bold text-slate-400 uppercase tracking-wider">ชื่อ - นามสกุล</div>
                        <div class="md:col-span-2 text-[15px] font-medium text-slate-800 py-1 md:py-0">{{ $user->name }}
                        </div>
                    </div>

                    {{-- อีเมล --}}
                    <div
                        class="grid grid-cols-1 md:grid-cols-3 py-4 border-b border-slate-100 items-center hover:bg-slate-50/50 transition-colors">
                        <div class="text-[14px] font-bold text-slate-400 uppercase tracking-wider">อีเมลที่ใช้งาน</div>
                        <div class="md:col-span-2 text-[15px] font-medium text-slate-800 py-1 md:py-0">
                            {{ $user->email ?? '-' }}</div>
                    </div>

                    {{-- เลขบัตรประชาชน --}}
                    <div
                        class="grid grid-cols-1 md:grid-cols-3 py-4 border-b border-slate-100 items-center hover:bg-slate-50/50 transition-colors">
                        <div class="text-[14px] font-bold text-slate-400 uppercase tracking-wider">หมายเลขบัตรประชาชน</div>
                        <div class="md:col-span-2 text-[15px] font-medium text-slate-800 py-1 md:py-0 tracking-widest">
                            {{ $user->citizen_id ?? '-' }}</div>
                    </div>

                    {{-- หน่วยงาน / แผนก (เช็คความถูกต้องเรียบร้อย) --}}
                    <div
                        class="grid grid-cols-1 md:grid-cols-3 py-4 border-b border-slate-100 items-center hover:bg-slate-50/50 transition-colors">
                        <div class="text-[14px] font-bold text-slate-400 uppercase tracking-wider">หน่วยงาน / แผนก</div>
                        <div class="md:col-span-2 text-[15px] font-medium text-slate-800 py-1 md:py-0">
                            {{ optional($user->departmentRef)->display_name ?? (optional($user->departmentRef)->name_th ?? '—') }}
                        </div>
                    </div>

                    {{-- สิทธิ์การใช้งาน --}}
                    <div
                        class="grid grid-cols-1 md:grid-cols-3 py-4 border-b border-slate-100 items-center hover:bg-slate-50/50 transition-colors">
                        <div class="text-[14px] font-bold text-slate-400 uppercase tracking-wider">สิทธิ์การเข้าใช้งาน</div>
                        <div class="md:col-span-2 text-[15px] font-bold text-emerald-700 py-1 md:py-0">
                            {{ $user->role_label ?? $user->role }}</div>
                    </div>

                    {{-- วันที่สมัคร --}}
                    <div
                        class="grid grid-cols-1 md:grid-cols-3 py-4 border-b border-slate-100 items-center hover:bg-slate-50/50 transition-colors">
                        <div class="text-[14px] font-bold text-slate-400 uppercase tracking-wider">วันที่สมัครสมาชิก</div>
                        <div class="md:col-span-2 text-[14px] text-slate-500 py-1 md:py-0">
                            {{ $user->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
