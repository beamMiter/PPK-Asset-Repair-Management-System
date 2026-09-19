{{-- resources/views/admin/users/edit.blade.php --}}
@extends('layouts.app')
@section('title', 'แก้ไขผู้ใช้ #' . $user->id)

@php
    // Logic ตัวอักษรย่อ 2 ตัว
    $getInitials = function ($name) {
        $name = trim((string) $name);
        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = mb_substr($parts[0] ?? 'U', 0, 1);
        $second = mb_substr($parts[1] ?? '', 0, 1);
        return strtoupper($first . $second);
    };
@endphp

@section('page-header')
    <div class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-5">
            <div class="flex items-start justify-between gap-4">

                {{-- Title & Avatar --}}
                <div class="flex items-start gap-3">
                    {{-- Avatar Section: ดึงรูปจริงมาโชว์ ถ้าไม่มีโชว์ตัวย่อวงกลม --}}
                    <div
                        class="mt-1 h-12 w-12 shrink-0 overflow-hidden rounded-full border-2 border-white bg-emerald-600">
                        @if ($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                        @else
                            <div
                                class="flex h-full w-full items-center justify-center text-white text-sm font-bold uppercase">
                                {{ $getInitials($user->name) }}
                            </div>
                        @endif
                    </div>

                    <div>
                        <h1 class="text-xl font-semibold text-slate-900 flex items-center gap-2">
                            Edit User
                            <span class="text-slate-500 font-normal">#{{ $user->id }}</span>
                        </h1>
                        <p class="mt-1 text-sm text-slate-600">
                            แก้ไขข้อมูลบัญชีของ <span class="font-semibold text-slate-800">{{ $user->name }}</span>
                        </p>
                    </div>
                </div>

                {{-- Back Button --}}
                <x-ui.back-button :fallback="route('admin.users.index')" />

            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">

        {{-- Error Display --}}
        @if ($errors->any())
            <div class="mb-8 rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                <p class="font-medium">มีข้อผิดพลาดในการบันทึกข้อมูล:</p>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form Tag: Action ไปที่ Update, Method PUT --}}
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="maint-form space-y-8" novalidate
            autocomplete="off">
            @csrf
            @method('PUT')

            {{-- Include Form: เรียกใช้ Input fields ชุดเดียวกับ Create --}}
            @include('admin.users._form', [
                'user' => $user,
                'roles' => $roles,
                'roleLabels' => $roleLabels ?? \App\Models\User::roleLabels(),
                'departments' => $departments,
            ])

            {{-- Action Buttons --}}
            <x-ui.form-actions :cancel-href="url()->previous() !== url()->current() ? url()->previous() : route('admin.users.index')" />
        </form>

    </div>
@endsection
