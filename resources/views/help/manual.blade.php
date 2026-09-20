@extends('layouts.app')

@section('title', 'User Manual')

@section('content')
    <div class="max-w-7xl mx-auto px-6 py-12" x-data="{
        activeRole: '{{ auth()->user()->isTechnician() || auth()->user()->isAdmin() || auth()->user()->isSupervisor() ? 'user' : 'user' }}'
    }">

        {{-- Manual Intro Header (Integrated) --}}
        <div class="text-center mb-12">
            <div
                class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white mb-6 border border-slate-200/60 overflow-hidden p-3">
                <img src="{{ asset('icon/manual.webp') }}" alt="Manual Icon" class="w-full h-full object-contain">
            </div>
            <h1 class="text-4xl font-bold text-[#0F2D5C] tracking-tight">คู่มือการใช้งานระบบเบื้องต้น</h1>
            <p class="text-slate-500 text-lg mt-3 max-w-2xl mx-auto">
                คำแนะนำขั้นตอนการใช้งานระบบบริหารจัดการงานซ่อมบำรุงอย่างละเอียด สำหรับบุคลากรทุกระดับ</p>
        </div>

        {{-- Role Switcher Tabs (Only for Tech Staff) --}}
        @if (auth()->user()->isTechnician() || auth()->user()->isAdmin() || auth()->user()->isSupervisor())
            <div class="mb-12 flex justify-center">
                <div class="relative w-full max-w-2xl border-b border-slate-200">
                    <div class="grid grid-cols-2 relative z-10">
                        <button @click="activeRole = 'user'"
                            :class="activeRole === 'user' ? 'text-[#0F2D5C]' : 'text-slate-400 hover:text-slate-600'"
                            class="pb-4 pt-2 px-2 md:px-4 text-[13px] md:text-[15px] font-bold transition-all flex flex-col md:flex-row items-center justify-center gap-1 md:gap-3 text-center whitespace-normal md:whitespace-nowrap tracking-wide">
                            <span class="material-symbols-outlined text-[22px]">medical_services</span>
                            <span>บุคลากร / ผู้ใช้งานทั่วไป</span>
                        </button>
                        <button @click="activeRole = 'staff'"
                            :class="activeRole === 'staff' ? 'text-[#0F2D5C]' : 'text-slate-400 hover:text-slate-600'"
                            class="pb-4 pt-2 px-2 md:px-4 text-[13px] md:text-[15px] font-bold transition-all flex flex-col md:flex-row items-center justify-center gap-1 md:gap-3 text-center whitespace-normal md:whitespace-nowrap tracking-wide">
                            <span class="material-symbols-outlined text-[22px]">engineering</span>
                            <span>เจ้าหน้าที่เทคนิค / ระบบ</span>
                        </button>
                    </div>
                    {{-- Sliding Underline --}}
                    <div class="absolute bottom-0 left-0 h-0.5 bg-[#0F2D5C] transition-all duration-300 ease-in-out"
                        :style="{ width: '50%', transform: activeRole === 'user' ? 'translateX(0)' : 'translateX(100%)' }">
                    </div>
                </div>
            </div>
        @endif

        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Sidebar Navigation --}}
            <aside class="lg:w-64 shrink-0">
                <div class="lg:sticky lg:top-[100px]">
                    <nav class="flex overflow-x-auto lg:flex-col gap-2 pb-2 lg:pb-0 snap-x [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                        {{-- Common Item --}}
                        <a href="#overview"
                            class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                            <span
                                class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                            <span
                                class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">info</span>
                            ภาพรวมระบบ
                        </a>

                        {{-- User Specific Menu --}}
                        <div x-show="activeRole === 'user'" class="flex lg:flex-col gap-2 lg:gap-1 shrink-0">
                            <div class="hidden lg:block mt-4 mb-2 px-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                                คู่มือการใช้งาน</div>
                            <a href="#reporting"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">edit_note</span>
                                การแจ้งซ่อมใหม่
                            </a>
                            <a href="#tracking"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">troubleshoot</span>
                                การติดตามสถานะ
                            </a>
                            <a href="#status-guide"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">dynamic_feed</span>
                                ความหมายของสถานะ
                            </a>
                            <a href="#completion"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">verified</span>
                                การตรวจสอบและปิดงาน
                            </a>
                        </div>

                        {{-- Staff Specific Menu --}}
                        <div x-show="activeRole === 'staff'" x-cloak class="flex lg:flex-col gap-2 lg:gap-1 shrink-0">
                            <div class="hidden lg:block mt-4 mb-2 px-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                                การจัดการระบบ</div>
                            <a href="#managing"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">engineering</span>
                                การจัดการงานซ่อม
                            </a>
                            <a href="#dashboards"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">analytics</span>
                                แดชบอร์ดและสถิติ
                            </a>
                            <a href="#sla-settings"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">rule</span>
                                การจัดการประเภทงานซ่อมและ SLA
                            </a>
                        </div>

                        {{-- Shared Bottom Item --}}
                        <div class="flex lg:flex-col gap-2 lg:gap-1 shrink-0">
                            <div class="hidden lg:block mt-4 mb-2 px-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                                ข้อมูลระบบ</div>
                            <a href="#chat-communication"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">forum</span>
                                การสื่อสารผ่าน Live Chat
                            </a>
                            <a href="#assets"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">inventory_2</span>
                                ทะเบียนทรัพย์สิน
                            </a>
                        </div>
                    </nav>
                </div>
            </aside>

            {{-- Main Manual Content --}}
            <div class="flex-1 space-y-10 pb-24">

                {{-- Section: Overview (Always Show) --}}
                <section id="overview" class="scroll-mt-24">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                        <span class="material-symbols-outlined text-[24px] text-[#0F2D5C]">info</span>
                        ภาพรวมระบบ
                    </h2>
                    <div class="prose prose-slate max-w-none text-slate-600 leading-relaxed">
                        <p>
                            ระบบบริหารจัดการงานซ่อมบำรุง (Asset Repair Management System)
                            ถูกพัฒนาขึ้นเพื่อรวบรวมและติดตามขั้นตอนการซ่อมบำรุงทรัพย์สินของโรงพยาบาลพระปกเกล้า
                            ให้มีความรวดเร็ว โปร่งใส และสามารถตรวจสอบสถิติเชิงลึกได้
                        </p>

                    </div>
                </section>


                {{-- ROLE: USER CONTENT --}}
                <div x-show="activeRole === 'user'" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                    class="space-y-10">
                    {{-- Section: Reporting --}}
                    <section id="reporting" class="scroll-mt-24">
                        <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                            <span class="material-symbols-outlined text-[24px] text-blue-600">edit_note</span>
                            การแจ้งซ่อมใหม่
                        </h2>
                        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden ">
                            <div class="p-6">
                                <p class="text-slate-600 mb-6">ผู้ใช้งานทั่วไปสามารถแจ้งซ่อมได้ผ่านเมนู "แจ้งซ่อมบำรุง"
                                    โดยทำตามขั้นตอนดังนี้:</p>
                                <div class="space-y-8">
                                    <div class="flex gap-4">
                                        <div
                                            class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            1</div>
                                        <div>
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">
                                                เลือกทรัพย์สินที่ต้องการแจ้งซ่อม</div>
                                            <p class="text-[15px] text-slate-600 leading-relaxed">พิมพ์รหัสทรัพย์สิน (รพจ.
                                                หรือ Asset Code)
                                                ระบบจะดึงข้อมูลจากฐานข้อมูลโดยอัตโนมัติ หรือค้นหาตามชื่อทรัพย์สิน</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-4 border-t border-slate-50 pt-8">
                                        <div
                                            class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            2</div>
                                        <div>
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">ระบุรายละเอียดปัญหา</div>
                                            <p class="text-[15px] text-slate-600 leading-relaxed">
                                                ใส่ชื่อเรื่องและรายละเอียดของปัญหาที่พบ
                                                พร้อมแนบภาพถ่ายประกอบ (ถ้ามี) เพื่อให้ช่างวิเคราะห์เบื้องต้นได้แม่นยำ</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-4 border-t border-slate-50 pt-8">
                                        <div
                                            class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            3</div>
                                        <div>
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">
                                                ระบุสถานที่และข้อมูลติดต่อ</div>
                                            <p class="text-[15px] text-slate-600 leading-relaxed">ตรวจสอบหน่วยงาน
                                                เบอร์โทรศัพท์
                                                และสถานที่ตั้งที่ถูกต้อง เพื่อให้เจ้าหน้าที่เข้าถึงพื้นที่ได้อย่างรวดเร็ว
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Section: Tracking --}}
                    <section id="tracking" class="scroll-mt-24">
                        <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                            <span class="material-symbols-outlined text-[24px] text-sky-600">troubleshoot</span>
                            การติดตามสถานะ
                        </h2>
                        <p class="text-slate-600">
                            คุณสามารถติดตามสถานะงานซ่อมของคุณได้ที่เมนู <b>"งานแจ้งซ่อมของฉัน"</b>
                            ซึ่งจะแสดงรายการงานทั้งหมดที่คุณเป็นผู้แจ้ง พร้อมสถานะปัจจุบันของแต่ละงานแบบเรียลไทม์
                        </p>
                    </section>

                    {{-- Section: Status Guide --}}
                    <section id="status-guide" class="scroll-mt-24">
                        <h2 class="text-2xl font-bold text-slate-900 mb-8 flex items-center gap-3">
                            <span class="material-symbols-outlined text-[24px] text-[#0F2D5C]">dynamic_feed</span>
                            ความหมายของสัญลักษณ์สถานะ
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @php
                                $statuses = [
                                    [
                                        'icon' => 'hourglass_empty',
                                        'color' => 'text-amber-600',
                                        'name' => 'รอดำเนินการ',
                                        'desc' => 'ใบแจ้งซ่อมถูกส่งเข้าระบบแล้ว รอเจ้าหน้าที่รับทราบ',
                                    ],
                                    [
                                        'icon' => 'visibility',
                                        'color' => 'text-blue-500',
                                        'name' => 'รับทราบแล้ว',
                                        'desc' => 'เจ้าหน้าที่เทคนิคเห็นข้อมูลแล้ว',
                                    ],
                                    [
                                        'icon' => 'thumb_up',
                                        'color' => 'text-emerald-600',
                                        'name' => 'รับเรื่องแล้ว',
                                        'desc' => 'เจ้าหน้าที่ได้ตรวจสอบและรับเข้าสู่คิวงานเรียบร้อยแล้ว',
                                    ],
                                    [
                                        'icon' => 'autorenew',
                                        'color' => 'text-blue-700',
                                        'name' => 'กำลังดำเนินการ',
                                        'desc' => 'ช่างกำลังปฏิบัติงาน ณ พื้นที่',
                                    ],
                                    [
                                        'icon' => 'pause_circle',
                                        'color' => 'text-slate-500',
                                        'name' => 'หยุดการซ่อมบำรุงชั่วคราว',
                                        'desc' => 'รออะไหล่ หรือติดปัญหาเฉพาะหน้า',
                                    ],
                                    [
                                        'icon' => 'task_alt',
                                        'color' => 'text-emerald-600',
                                        'name' => 'ซ่อมบำรุงเสร็จสิ้น',
                                        'desc' => 'แก้ไขปัญหาเรียบร้อยแล้ว',
                                    ],
                                    [
                                        'icon' => 'verified',
                                        'color' => 'text-emerald-800',
                                        'name' => 'อนุมัติผลการซ่อมบำรุง',
                                        'desc' => 'ตรวจสอบและยืนยันการรับมอบงาน',
                                    ],
                                    [
                                        'icon' => 'cancel',
                                        'color' => 'text-rose-600',
                                        'name' => 'ยกเลิกการซ่อมบำรุง',
                                        'desc' => 'งานถูกยกเลิกโดยผู้แจ้งหรือแอดมิน',
                                    ],
                                    [
                                        'icon' => 'error',
                                        'color' => 'text-rose-700',
                                        'name' => 'ไม่รับเรื่อง',
                                        'desc' => 'ข้อมูลไม่ถูกต้อง หรือไม่อยู่ในเงื่อนไขการซ่อม',
                                    ],
                                ];
                            @endphp
                            @foreach ($statuses as $s)
                                <div
                                    class="bg-white p-4 rounded-lg border border-slate-100 flex items-start gap-4 transition-all hover:border-blue-100">
                                    <span
                                        class="material-symbols-outlined text-[24px] {{ $s['color'] }} shrink-0 mt-1">{{ $s['icon'] }}</span>
                                    <div>
                                        <div class="font-bold text-slate-900 text-[15px]">{{ $s['name'] }}</div>
                                        <p class="text-[14px] text-slate-600 mt-1 leading-relaxed">{{ $s['desc'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- Section: Completion & Closing --}}
                    <section id="completion" class="scroll-mt-24">
                        <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                            <span class="material-symbols-outlined text-[24px] text-emerald-600">verified</span>
                            การตรวจสอบและปิดงาน
                        </h2>

                        <div class="bg-amber-50 border border-amber-100 rounded-xl p-6 mb-8">
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-amber-600">volunteer_activism</span>
                                </div>
                                <div class="space-y-1">
                                    <div class="font-bold text-amber-900 text-lg">ขอความร่วมมือ: ตรวจสอบและอนุมัติผลการซ่อม
                                    </div>
                                    <p class="text-amber-700 leading-relaxed">
                                        เมื่อเจ้าหน้าที่แจ้งว่าดำเนินการเสร็จสิ้น
                                        <b>ขอความร่วมมือท่านช่วยตรวจสอบความเรียบร้อย</b>
                                        หากพบว่าใช้งานได้ปกติแล้ว รบกวนกดอนุมัติผลทันที เพื่อให้ข้อมูลระยะเวลาการดำเนินงาน
                                        (SLA) ของหน่วยงานมีความถูกต้องและสะท้อนประสิทธิภาพที่แท้จริง
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden ">
                            <div class="p-6 space-y-6">
                                <div class="flex gap-4">
                                    <div
                                        class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                        1</div>
                                    <div>
                                        <div class="font-bold text-slate-900 mb-1 text-[18px]">กด "อนุมัติผลการซ่อม"</div>
                                        <p class="text-[15px] text-slate-600 leading-relaxed">
                                            ปุ่มนี้จะปรากฏขึ้นเมื่อสถานะงานเป็น "ซ่อมบำรุงเสร็จสิ้น"
                                            การกดปุ่มนี้เป็นการยืนยันว่าท่านได้รับทรัพย์สินคืนและใช้งานได้ปกติแล้ว
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-4 border-t border-slate-100 pt-6">
                                    <div
                                        class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold shrink-0 text-sm">
                                        2</div>
                                    <div>
                                        <div class="font-bold text-slate-900 mb-1 text-[18px]">ประเมินความพึงพอใจ (ถ้ามี)
                                        </div>
                                        <p class="text-[15px] text-slate-600 leading-relaxed">
                                            หลังจากอนุมัติผล ท่านสามารถให้คะแนนการบริการได้ตามความสมัครใจ
                                            <b>(ไม่ได้บังคับ)</b>
                                            ข้อมูลนี้จะถูกนำไปใช้เพื่อพัฒนาคุณภาพการให้บริการของเจ้าหน้าที่ต่อไป
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-4 p-4 bg-slate-50 rounded-lg border border-slate-100">
                                    <div class="flex gap-3">
                                        <span class="material-symbols-outlined text-slate-400 text-[20px]">verified_user</span>
                                        <div class="text-[13px] text-slate-500 italic">
                                            หมายเหตุ: ระบบจะบันทึกคะแนนประเมินเฉพาะงานที่มีสถานะ "อนุมัติผลการซ่อม (Closed)" เท่านั้น เพื่อความถูกต้องของสถิติผลงานช่าง
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>


                </div>

                {{-- ROLE: STAFF CONTENT --}}
                <div x-show="activeRole === 'staff'" x-cloak x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                    class="space-y-10">
                    {{-- Section: Managing (Technicians) --}}
                    <section id="managing" class="scroll-mt-24">
                        <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                            <span class="material-symbols-outlined text-[24px] text-indigo-600">engineering</span>
                            การจัดการงานซ่อม
                        </h2>
                        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden ">
                            <div class="p-6 space-y-8">
                                <!-- Step 1: Receiving -->
                                <div class="flex gap-4">
                                    <div
                                        class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                        1</div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900 mb-2 text-[18px]">การรับงาน</div>
                                        <ul class="space-y-3">
                                            <li class="flex items-start gap-2">
                                                <span
                                                    class="material-symbols-outlined text-[20px] text-blue-500 mt-0.5">assignment_turned_in</span>
                                                <span class="text-[15px] text-slate-600 leading-relaxed">กด
                                                    <b>"รับทราบ"</b> เพื่อยืนยันว่าได้รับรู้ปัญหาแล้ว
                                                    (มีผลต่อสถิติเวลาตอบกลับ)</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <span
                                                    class="material-symbols-outlined text-[20px] text-emerald-600 mt-0.5">task_alt</span>
                                                <span class="text-[15px] text-slate-600 leading-relaxed">กด
                                                    <b>"รับเรื่อง"</b> เพื่อยืนยันการรับงานเข้าสู่คิวปฏิบัติงาน
                                                    (มีผลต่อสถิติเวลารับเรื่อง)</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Step 2: Operating -->
                                <div class="flex gap-4 border-t border-slate-50 pt-8">
                                    <div
                                        class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                        2</div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900 mb-2 text-[18px]">การดำเนินการ</div>
                                        <ul class="space-y-3">
                                            <li class="flex items-start gap-2">
                                                <span
                                                    class="material-symbols-outlined text-[20px] text-sky-600 mt-0.5">play_circle</span>
                                                <span class="text-[15px] text-slate-600 leading-relaxed">กด
                                                    <b>"ดำเนินการ"</b> เมื่อเริ่มลงมือซ่อมบำรุง ณ สถานที่จริง</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <span
                                                    class="material-symbols-outlined text-[20px] text-amber-500 mt-0.5">pause_circle</span>
                                                <span
                                                    class="text-[15px] text-slate-600 leading-relaxed">หากต้องรออะไหล่หรือติดปัญหาเฉพาะหน้า
                                                    สามารถกด <b>"หยุดชั่วคราว"</b> ได้</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Step 3: Closing -->
                                <div class="flex gap-4 border-t border-slate-50 pt-8">
                                    <div
                                        class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                        3</div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900 mb-2 text-[18px]">การปิดงาน (สำคัญต่อ SLA)
                                        </div>
                                        <ul class="space-y-3">
                                            <li class="flex items-start gap-2">
                                                <span
                                                    class="material-symbols-outlined text-[20px] text-emerald-600 mt-0.5">done_all</span>
                                                <span
                                                    class="text-[15px] text-slate-600 leading-relaxed">เมื่อดำเนินการเสร็จสิ้น
                                                    ให้กด <b>"เสร็จสิ้น"</b> เพื่อส่งมอบงานคืน</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <span
                                                    class="material-symbols-outlined text-[20px] text-amber-500 mt-0.5">info</span>
                                                <span
                                                    class="text-[15px] text-slate-600 leading-relaxed"><b>โปรดขอความร่วมมือผู้แจ้ง:</b>
                                                    ตรวจสอบงานและกด <b>"อนุมัติปิดงาน"</b> ทันทีเมื่อซ่อมเสร็จ
                                                    เพื่อผลลัพธ์ที่ดีของ SLA</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>


                    {{-- Section: SLA Settings --}}
                    <section id="sla-settings" class="scroll-mt-24">
                        <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                            <span class="inline-flex h-6 w-6 items-center justify-center shrink-0">
                                <img src="/icon/sla.webp" class="w-full h-full object-contain" alt="SLA icon">
                            </span>
                            การจัดการประเภทงานซ่อมและ SLA
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                                <h3 class="font-bold text-slate-900 mb-3 text-[18px]">การจัดการประเภทงานซ่อม</h3>
                                <p class="text-[15px] text-slate-600 leading-relaxed mb-4">
                                    ผู้ดูแลระบบสามารถกำหนด <b>"ประเภทงานซ่อม"</b> (เช่น Software, Network, Hardware)
                                    เพื่อจัดกลุ่มข้อมูลและมอบหมายทีมเจ้าหน้าที่ที่เกี่ยวข้องโดยอัตโนมัติ
                                </p>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                                <h3 class="font-bold text-slate-900 mb-3 text-[18px]">การกำหนดค่าเป้าหมาย SLA</h3>
                                <p class="text-[15px] text-slate-600 leading-relaxed mb-4">
                                    ส่วนนี้ใช้สำหรับกำหนด <b>"ค่าเป้าหมาย (SLA Targets)"</b> เพื่อใช้เป็นเกณฑ์ในการวัดผล
                                    <b>"ค่าเฉลี่ยประสิทธิภาพ"</b> ของทีมซ่อมบำรุงในแดชบอร์ด:
                                </p>
                                <ul class="space-y-3 text-[15px] text-slate-600">
                                    <li class="flex items-start gap-3">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500 mt-1.5 shrink-0"></span>
                                        <span><b>เวลาตอบกลับ (Response Time):</b> กำหนดช่วงเวลาเป้าหมายที่เจ้าหน้าที่ควรจะกด
                                            <b>"รับทราบ"</b> หลังจากมีการแจ้งซ่อมเข้ามา</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 mt-1.5 shrink-0"></span>
                                        <span><b>เวลาแก้ไขเสร็จ (Resolution Time):</b>
                                            กำหนดช่วงเวลาเป้าหมายที่งานซ่อมประเภทนั้นๆ ควรจะดำเนินการจนถึงสถานะ
                                            <b>"เสร็จสิ้น"</b></span>
                                    </li>
                                </ul>
                                <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100/50">
                                    <div class="text-[14px] text-blue-800 font-bold mb-1">กลไกการคำนวณ Due Date อัตโนมัติ</div>
                                    <p class="text-[13px] text-blue-700 leading-relaxed">
                                        เมื่อเลือก "ประเภทงานซ่อม" ระบบจะนำเวลาเป้าหมาย (Resolution Time) ของประเภทนั้นๆ มาคำนวณหา "วันที่ควรเสร็จ" ให้โดยอัตโนมัติทันที
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Section: Staff Guidelines for SLA Accuracy --}}
                        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-6">
                            <div class="flex gap-4">
                                <div
                                    class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-indigo-600">campaign</span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-indigo-900 text-lg mb-2">แนวทางการประสานงานผู้แจ้ง
                                        (สำคัญต่อผล SLA)</h4>
                                    <p class="text-indigo-700 text-[15px] leading-relaxed mb-4">
                                        เพื่อให้ค่าสถิติสะญลักษณ์ถึงประสิทธิภาพการทำงานที่แท้จริง
                                        เจ้าหน้าที่ควรปฏิบัติตามแนวทางดังนี้:
                                    </p>
                                    <div class="space-y-6">
                                        <ul class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <li class="bg-white/60 p-4 rounded-lg border border-indigo-100/50 ">
                                                <div
                                                    class="font-bold text-indigo-900 text-[15px] mb-1 flex items-center gap-2">
                                                    1. แจ้งเมื่อเริ่มงาน
                                                </div>
                                                <p class="text-[14px] text-indigo-800 leading-relaxed">เมื่อกด
                                                    <b>"ดำเนินการ"</b> ควรแจ้งผู้รับบริการให้ทราบถึงแผนการซ่อมเบื้องต้น
                                                    และระยะเวลาที่คาดว่าจะเสร็จ
                                                </p>
                                            </li>
                                            <li class="bg-white/60 p-4 rounded-lg border border-indigo-100/50 ">
                                                <div
                                                    class="font-bold text-indigo-900 text-[15px] mb-1 flex items-center gap-2">
                                                    2. ตรวจสอบหน้างานจริง
                                                </div>
                                                <p class="text-[14px] text-indigo-800 leading-relaxed">เมื่อซ่อมเสร็จ
                                                    โปรดให้ผู้แจ้งตรวจสอบผลงานจนเป็นที่พอใจก่อนที่คุณจะกด <b>"เสร็จสิ้น"</b>
                                                    ในระบบ</p>
                                            </li>
                                        </ul>

                                        <div class="bg-indigo-600 p-5 rounded-lg border border-indigo-700">
                                            <div class="relative z-10">
                                                <div class="text-white font-bold mb-2 text-lg">
                                                    สำคัญที่สุด: กำชับการ "อนุมัติปิดงาน"
                                                </div>
                                                <p class="text-indigo-50 leading-relaxed text-[15px]">
                                                    ขอความร่วมมือให้ผู้แจ้งกด <b>"อนุมัติปิดงาน"</b> ในระบบ <b>ทันที</b>
                                                    หลังจากตรวจสอบเสร็จสิ้น
                                                    เพื่อให้สถิติเวลาที่ทำงานจริงถูกบันทึกอย่างแม่นยำ
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 mt-8">
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-blue-600">info</span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-blue-900 text-lg mb-1">หมายเหตุสำคัญ</h4>
                                    <p class="text-blue-700 text-[15px] leading-relaxed">
                                        การแก้ไขค่า SLA จะมีผลเฉพาะกับ <b>"ใบแจ้งซ่อมใหม่ที่เกิดขึ้นหลังจากนั้นเท่านั้น"</b>
                                        โดยที่หน้า SLA Dashboard
                                        จะสรุปข้อมูลประสิทธิภาพของช่างเทียบกับค่าเป้าหมายเหล่านี้แบบเรียลไทม์
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Section: Dashboards --}}
                    <section id="dashboards" class="scroll-mt-24">
                        <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                            <span class="material-symbols-outlined text-[24px] text-amber-600">analytics</span>
                            แดชบอร์ดและสถิติ
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                                <h3 class="font-bold text-slate-900 mb-2 text-[16px]">SLA & Performance</h3>
                                <p class="text-[14px] text-slate-600 leading-relaxed">
                                    แสดงภาพรวมสถิติความเร็วในการทำงานรายเดือน เทียบกับค่าเป้าหมายที่กำหนดไว้ เพื่อวิเคราะห์จุดคอขวดของระบบ
                                </p>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                                <h3 class="font-bold text-slate-900 mb-2 text-[16px]">Technician Leaderboard</h3>
                                <p class="text-[14px] text-slate-600 leading-relaxed">
                                    ระบบจัดอันดับช่างเทคนิคตามคะแนนความพึงพอใจและจำนวนงานที่ปิดสำเร็จ เพื่อเป็นขวัญและกำลังใจในการปฏิบัติงาน
                                </p>
                            </div>
                        </div>
                    </section>
                </div>


                {{-- Section: Live Chat (Always Show) --}}
                <section id="chat-communication" class="scroll-mt-24">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                        <span class="material-symbols-outlined text-[24px] text-indigo-500">forum</span>
                        การสื่อสารผ่าน Live Chat
                    </h2>
                    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden relative">
                        <div class="p-6 relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                            <div class="space-y-6">
                                <h3 x-show="activeRole === 'user'" class="text-[18px] font-bold text-slate-900">ระบบสอบถามและประสานงานเบื้องต้น</h3>
                                <h3 x-show="activeRole === 'staff'" x-cloak class="text-[18px] font-bold text-slate-900">การกำกับดูแลและติดตามบทสนทนา</h3>
                                <p x-show="activeRole === 'user'" class="text-slate-600 text-[15px] leading-relaxed">
                                    คุณสามารถใช้ช่องทางแชทเพื่อสอบถามข้อมูลเบื้องต้น หรือแจ้งรายละเอียดเพิ่มเติมเกี่ยวกับปัญหาหน้างานกับช่างได้ทันที
                                    เพื่อให้การสื่อสารเป็นไปอย่างรวดเร็วและชัดเจน
                                </p>
                                <p x-show="activeRole === 'staff'" x-cloak class="text-slate-600 text-[15px] leading-relaxed">
                                    เจ้าหน้าที่และแอดมินสามารถเข้าดู "ห้องแชท" หรือ "กระทู้สนทนา" ของบุคลากรแต่ละท่านได้เพื่อติดตามความคืบหน้า
                                    รวมถึงเข้าช่วยเหลือหรือตอบคำถามในกรณีที่ช่างเทคนิคต้องการการสนับสนุนเพิ่มเติม
                                </p>
                                <div class="space-y-5">
                                    {{-- Content for USER --}}
                                    <div x-show="activeRole === 'user'" class="space-y-5">
                                        <div class="flex gap-4">
                                            <div
                                                class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                                                <span
                                                    class="material-symbols-outlined text-indigo-600">contact_support</span>
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-900 text-[15px]">สอบถามข้อมูลได้ทันที
                                                </div>
                                                <p class="text-[14px] text-slate-500">
                                                    ไม่ต้องโทรศัพท์หาเจ้าหน้าที่ สามารถสอบถามความคืบหน้าผ่านแชทได้โดยตรง</p>
                                            </div>
                                        </div>
                                        <div class="flex gap-4">
                                            <div
                                                class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                                                <span
                                                    class="material-symbols-outlined text-indigo-600">notifications_active</span>
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-900 text-[15px]">แจ้งเตือนเมื่อช่างตอบกลับ
                                                </div>
                                                <p class="text-[14px] text-slate-500">
                                                    ระบบจะแจ้งเตือนทันทีเมื่อเจ้าหน้าที่เทคนิคมีการเคลื่อนไหวในแชทของคุณ</p>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Content for STAFF/ADMIN --}}
                                    <div x-show="activeRole === 'staff'" x-cloak class="space-y-5">
                                        <div class="flex gap-4">
                                            <div
                                                class="w-10 h-10 rounded-full bg-[#0F2D5C] flex items-center justify-center shrink-0 text-white ">
                                                <span
                                                    class="material-symbols-outlined text-[20px]">admin_panel_settings</span>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-bold text-slate-900 text-[15px]">บริหารจัดการบทสนทนา</div>
                                                <p class="text-[14px] text-slate-500 mb-4">
                                                    เจ้าหน้าที่สามารถมองเห็นรายการแชทแยกตาม "งานของฉัน" หรือ "งานใหม่" เพื่อความคล่องตัวในการปฏิบัติงาน
                                                </p>
                                                <ul class="space-y-2 text-[13px] text-indigo-700 bg-slate-50 p-4 rounded-lg border border-slate-100">
                                                    <li class="flex items-center gap-2">
                                                        <span class="material-symbols-outlined text-[16px] text-indigo-500">check_circle</span> จัดการทุกห้องแชทที่ได้รับมอบหมาย
                                                    </li>
                                                    <li class="flex items-center gap-2">
                                                        <span class="material-symbols-outlined text-[16px] text-indigo-500">check_circle</span> ตัวกรอง "ยังไม่ได้อ่าน" เพื่อไม่ให้พลาดการติดต่อ
                                                    </li>
                                                    <li class="flex items-center gap-2">
                                                        <span class="material-symbols-outlined text-[16px] text-indigo-500">check_circle</span> เข้าถึงหน้าจัดการงานซ่อมได้ทันทีจากแชท
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="relative">
                                <div
                                    class="bg-white border border-slate-200 rounded-xl overflow-hidden aspect-[4/3] lg:aspect-auto">
                                    {{-- Mock UI for Chat Drawer --}}
                                    <div class="bg-[#0F2D5C] p-4 flex items-center gap-3 text-white">
                                        <div
                                            class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold">
                                            C</div>
                                        <div class="flex-1">
                                            <div class="text-sm font-bold text-white">Live Chat List</div>
                                            <div class="text-[10px] opacity-80">บทสนทนาที่คุณเข้าร่วม</div>
                                        </div>
                                    </div>
                                    <div class="p-3 border-b border-slate-100 italic text-[11px] text-slate-400">
                                        ตัวอย่างรายการแชท</div>
                                    <div class="p-3 space-y-4">
                                        <div class="flex gap-3">
                                            <div
                                                class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs">
                                                ซ</div>
                                            <div class="flex-1 space-y-1">
                                                <div class="flex justify-between items-center text-slate-900">
                                                    <span class="text-xs font-bold">ซ่อมแอร์ไม่เย็น...</span>
                                                    <span
                                                        class="px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[9px] font-bold">ใหม่
                                                        2</span>
                                                </div>
                                                <div class="text-[11px] text-slate-500 line-clamp-1">ช่าง:
                                                    กำลังเข้าไปตรวจสอบครับ...</div>
                                            </div>
                                        </div>
                                        <div class="flex gap-3 pt-3 border-t border-slate-50">
                                            <div
                                                class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs">
                                                C</div>
                                            <div class="flex-1 space-y-1 opacity-50">
                                                <div class="flex justify-between items-center text-slate-900">
                                                    <span class="text-xs font-bold">Computer เปิดไม่ติด</span>
                                                </div>
                                                <div class="text-[11px] text-slate-500 line-clamp-1">
                                                    ตรวจสอบสายไฟเรียบร้อยแล้วค่ะ</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- Badge Decoration --}}
                                <div
                                    class="absolute -right-4 -bottom-4 w-12 h-12 bg-rose-500 rounded-full flex items-center justify-center text-white font-bold border-4 border-white animate-pulse">
                                    2</div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Section: Assets (Always Show) --}}
                <section id="assets" class="scroll-mt-24">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                        <span class="material-symbols-outlined text-[24px] text-teal-600">inventory_2</span>
                        ทะเบียนทรัพย์สิน
                    </h2>
                    <div class="bg-slate-900 rounded-xl p-8 text-white relative overflow-hidden">
                        <div class="relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                            <div>
                                <h3 class="text-xl font-bold mb-4">เข้าถึงข้อมูลได้ทันทีผ่าน QR Code</h3>
                                <p class="text-slate-300 mb-8 text-[15px] leading-relaxed">
                                    ทรัพย์สินทุกชิ้นจะมีรหัสประจำตัวและ QR Code
                                    เมื่อคุณทำการแสกนจะสามารถตรวจดูรายละเอียดทรัพย์สิน สัญญาการรับประกัน ประวัติการซ่อมบำรุง
                                    ย้อนหลัง
                                </p>
                                <div class="p-4 bg-white/5 rounded-lg border border-white/10 mb-8">
                                    <div class="text-teal-400 font-bold mb-1 text-[14px]">การเชื่อมโยงสถานะอัตโนมัติ</div>
                                    <p class="text-slate-400 text-[13px] leading-relaxed">
                                        ระบบจะเปลี่ยนสถานะทรัพย์สินเป็น "In Repair" ทันทีเมื่อช่างกดรับเรื่อง และจะกลับเป็นสถานะปกติเมื่อคุณกด "อนุมัติผลการซ่อม"
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-x-6 gap-y-2 text-slate-400 text-sm font-medium">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[14px] text-teal-600">check</span> HIS ID integration
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[14px] text-teal-600">check</span> Warranty Tracking
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[14px] text-teal-600">check</span> Repair History
                                    </div>
                                </div>
                            </div>
                            <div
                                class="hidden lg:block bg-gradient-to-br from-white/10 to-transparent p-1 rounded-xl backdrop-blur-sm border border-white/10">
                                <div class="bg-slate-800 rounded-[calc(0.75rem)] p-6 text-center">
                                    <span class="material-symbols-outlined text-[80px] text-teal-400">qr_code_2</span>
                                    <div class="mt-4 text-slate-400 text-sm">Example QR Interface</div>
                                </div>
                            </div>
                        </div>
                        {{-- Decorative background shapes --}}
                        <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl"></div>
                    </div>
                </section>

            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        html {
            scroll-behavior: smooth;
        }

        /* Active Link Styling for In-page Nav */
        .manual-nav-active {
            background-color: #f1f5f9;
            color: #0F2D5C !important;
            font-weight: 700;
        }

        .manual-nav-active .active-indicator {
            opacity: 1;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Simple scroll spy to highlight active menu item
        window.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('aside nav a');

            let currentSectionId = '';
            sections.forEach(section => {
                if (section.offsetParent !== null) { // Only check visible sections
                    const sectionTop = section.offsetTop;
                    if (window.scrollY >= sectionTop - 180) {
                        currentSectionId = section.getAttribute('id');
                    }
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('manual-nav-active');
                if (link.getAttribute('href') === `#${currentSectionId}`) {
                    link.classList.add('manual-nav-active');
                }
            });
        });
    </script>
@endpush
