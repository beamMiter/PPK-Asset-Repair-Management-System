@extends('layouts.app')
@php
    $line = 'border-slate-200';
@endphp
@section('title', 'แจ้งซ่อมบำรุง')
@section('page-header')
    <div class="w-full bg-slate-50 border-b {{ $line }}">
        <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-xl text-emerald-700">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 5v14m7-7H5" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <h1 class="text-[20px] sm:text-[22px] font-semibold text-slate-900 leading-tight">
                                ทะเบียนแจ้งซ่อม
                            </h1>
                            <div class="mt-1 text-xs sm:text-[13px] text-slate-600 flex flex-wrap gap-x-4 gap-y-1">
                                <span>สร้างใบงานแจ้งซ่อมใหม่</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-start sm:justify-end gap-2">
                    <x-ui.back-button :fallback="route('maintenance.requests.index')" />
                </div>
            </div>
        </div>
    </div>
@endsection
@section('content')
    <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 pb-8 pt-0">

        <form method="POST" action="{{ route('maintenance.requests.store') }}" enctype="multipart/form-data"
            data-dirty-check="true"
            class="space-y-8" novalidate
            onsubmit="let btn = this.querySelector('button[type=\'submit\']'); setTimeout(() => { btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed'); btn.innerText = 'กำลังบันทึก...'; }, 10);">
            @csrf
            @include('maintenance.requests._form', [
                'req' => null,
                'assets' => $assets ?? collect(),
                'depts' => $depts ?? collect(),
                'attachments' => [],
            ])

        </form>
    </div>
@endsection
