@extends('layouts.app')

@php
    $line = 'border-slate-200';
    

    // Fixing a typo in Bird-snug -> leading-snug
@endphp

@section('title', 'Edit Request Type: ' . $type->name)

@section('page-header')
  <div class="w-full bg-slate-50 border-b {{ $line }}">
    <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-5">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
          <div class="flex items-start gap-3">
            <span class="mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-xl text-emerald-700">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z" />
              </svg>
            </span>
            <div class="min-w-0">
              <h1 class="text-[20px] sm:text-[22px] font-semibold text-slate-900 leading-tight">
                แก้ไขประเภทงานซ่อม
              </h1>
              <div class="mt-1 text-xs sm:text-[13px] text-slate-600 flex flex-wrap gap-x-4 gap-y-1">
                <span>แก้ไข: <span class="font-bold text-slate-900">{{ $type->name }}</span></span>
                <span class="text-slate-300">|</span>
                <span>ID: #{{ $type->id }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="flex flex-wrap items-center justify-start sm:justify-end gap-2">
          <x-ui.back-button :fallback="route('settings.maintenance-types.index')" />
        </div>
      </div>
    </div>
  </div>
@endsection

@section('content')
  <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 pb-8 pt-0">

    @if ($errors->any())
        @push('scripts')
            <script>
                (function() {
                    const errors = @json($errors->all());
                    errors.forEach(err => {
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: {
                                type: 'error',
                                message: err,
                                duration: 3500
                            }
                        }));
                    });
                })();
            </script>
        @endpush
    @endif

    <form method="POST" action="{{ route('settings.maintenance-types.update', $type->id) }}" class="space-y-8" novalidate onsubmit="let btn = this.querySelector('button[type=\'submit\']'); setTimeout(() => { btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed'); btn.innerText = 'กำลังบันทึก...'; }, 10);">
      @csrf
      @method('PUT')

      <div class="mx-auto max-w-screen-2xl px-3 sm:px-6 lg:px-8 mt-10">
        <div class="space-y-10">
          
          <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="hidden lg:block absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>

            {{-- Section 1: Basic Info --}}
            <section>
              <x-ui.section-head no="1" title="ข้อมูลพื้นฐาน" subtitle="แก้ไขชื่อและรายละเอียดประเภทงาน" />

              <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">
                        ชื่อประเภทงานซ่อม <span class="text-rose-500 font-bold">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name', $type->name) }}" autocomplete="off" class="ui-input" required placeholder="เช่น งานซ่อมคอมพิวเตอร์, งานประปา" maxlength="150">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">รายละเอียด / คำอธิบาย</label>
                    <textarea name="description" id="description" rows="4" class="ui-textarea" placeholder="ระบุรายละเอียดเพิ่มเติม...">{{ old('description', $type->description) }}</textarea>
                </div>
              </div>
            </section>

            {{-- Section 2: Display Settings --}}
            <section>
              <x-ui.section-head no="2" title="การตั้งค่าแสดงผล" subtitle="กำหนดลำดับและสถานะการใช้งาน" />

              <div class="space-y-4">
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-slate-700">ลำดับการแสดงผล</label>
                    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $type->sort_order ?? 0) }}" min="0" class="ui-input">
                    <p class="mt-1 text-[11px] text-slate-500 font-normal">ตัวเลขน้อยจะแสดงก่อนในรายการเลือก (ค่าเริ่มต้นคือ 0)</p>
                </div>

                <div>
                    <label for="is_active" class="block text-sm font-medium text-slate-700">สถานะการใช้งาน</label>
                    <select name="is_active" id="is_active" class="ui-input">
                        <option value="1" @selected(old('is_active', (bool)$type->is_active) == true)>เปิดใช้งาน (Active)</option>
                        <option value="0" @selected(old('is_active', (bool)$type->is_active) == false)>ปิดใช้งาน (Inactive)</option>
                    </select>
                </div>
              </div>
            </section>

            {{-- Section 3: SLA Targets --}}
            <section>
              <x-ui.section-head no="3" title="เป้าหมายเวลา (SLA Targets)" subtitle="กำหนดเป้าหมายเวลาพื้นฐานของประเภทงานนี้" />

              <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                      <label for="default_response_minutes" class="block text-sm font-medium text-slate-700">เวลาตอบกลับพื้นฐาน (นาที)</label>
                      <input type="number" name="default_response_minutes" id="default_response_minutes" value="{{ old('default_response_minutes', $type->default_response_minutes) }}" min="0" class="ui-input" placeholder="เช่น 60">
                      <p class="mt-1 text-[11px] text-slate-500 font-normal">ใช้เป็นค่าเริ่มต้นในการรับทราบงาน (Acknowledged)</p>
                  </div>
  
                  <div>
                      <label for="default_resolution_minutes" class="block text-sm font-medium text-slate-700">เวลาซ่อมแซมพื้นฐาน (นาที)</label>
                      <input type="number" name="default_resolution_minutes" id="default_resolution_minutes" value="{{ old('default_resolution_minutes', $type->default_resolution_minutes) }}" min="0" class="ui-input" placeholder="เช่น 1440">
                      <p class="mt-1 text-[11px] text-slate-500 font-normal">ใช้เป็นค่าเริ่มต้นในการปิดงาน (Resolved)</p>
                  </div>
                </div>
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                  <p class="text-[11px] text-emerald-800 leading-relaxed">
                    <strong>คำแนะนำ:</strong> เวลาที่กำหนดหน้านี้จะเป็นตัวชี้วัด (Target) หลักสำหรับ SLA ของใบงานประเภทนี้ทั้งหมด
                  </p>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>

      <x-ui.form-actions :cancel-href="url()->previous() !== url()->current() ? url()->previous() : route('settings.maintenance-types.index')" submit-label="บันทึกการแก้ไข" />
    </form>
  </div>
@endsection
