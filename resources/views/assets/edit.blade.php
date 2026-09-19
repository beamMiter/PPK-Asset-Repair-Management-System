@extends('layouts.app')

@php
    $line = 'border-slate-200';

@endphp

@section('header-wrap-class', 'no-gap')

@section('title', 'Edit Asset ' . ($asset->asset_code ?: '#' . $asset->id))

@section('page-header')
    <div class="w-full bg-slate-50 border-b {{ $line }}">
        <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                {{-- LEFT --}}
                <div class="min-w-0">
                    <div class="flex items-start gap-2.5">
                        <span class="mt-1 text-emerald-600">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5Z" />
                            </svg>
                        </span>

                        <div class="min-w-0">
                            <h1 class="text-[20px] sm:text-[22px] font-semibold text-slate-900 leading-tight">
                                ทะเบียนครุภัณฑ์
                                <span
                                    class="ml-2 text-slate-500 text-[13px] sm:text-[14px] font-semibold">#{{ $asset->id }}</span>
                            </h1>

                            <div class="mt-1 text-xs sm:text-[13px] text-slate-600 flex flex-wrap gap-x-4 gap-y-1">
                                <span>แก้ไขรายละเอียดครุภัณฑ์</span>
                                @if ($asset->updated_at)
                                    <span>
                                        อัปเดต:
                                        <span
                                            class="font-medium text-slate-900">{{ $asset->updated_at->format('Y-m-d H:i') }}</span>
                                    </span>
                                @endif
                                <span>
                                    รหัส: <span class="font-semibold text-slate-900">{{ $asset->asset_code }}</span>
                                </span>
                                <span class="truncate">
                                    ชื่อ: <span class="font-semibold text-slate-900">{{ $asset->name }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT --}}
                <div class="flex flex-wrap items-center justify-start sm:justify-end gap-2">
                    <x-ui.button variant="primary" :href="route('maintenance.requests.create', ['asset_id' => $asset->id])"
                        icon="add">สร้างคำขอซ่อมใหม่</x-ui.button>
                    <x-ui.back-button :fallback="route('assets.index')" />
                </div>

            </div>
        </div>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('assets.update', $asset) }}" enctype="multipart/form-data" class="space-y-8"
        novalidate>
        @csrf
        @method('PUT')

        @include('assets._form', [
            'asset' => $asset,
            'categories' => $categories ?? collect(),
            'departments' => $departments ?? collect(),
        ])

        <div class="mx-auto max-w-screen-2xl px-3 sm:px-6 lg:px-8 pb-10">
            <x-ui.form-actions cancel-href="javascript:history.back()" submit-label="บันทึกการแก้ไข" />
        </div>
    </form>
@endsection
