@extends('layouts.app')

@section('title', 'User Manual')

@section('content')
    <div class="max-w-7xl mx-auto px-6 py-12" x-data="{
        activeRole: 'user'
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
                        {{-- Common Items --}}
                            <a href="#overview"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">info</span>
                                ภาพรวมระบบ
                            </a>
                            <a href="#getting-started"
                                class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                <span
                                    class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                <span
                                    class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">login</span>
                                เริ่มต้นใช้งานและบัญชี
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
                            @can('manage-system')
                                <a href="#system-admin"
                                    class="manual-nav-item shrink-0 snap-start px-4 py-2.5 rounded-lg text-[14px] lg:text-[15px] font-medium text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 lg:gap-3 relative overflow-hidden group">
                                    <span
                                        class="active-indicator absolute left-0 top-0 bottom-0 w-1 bg-[#0F2D5C] opacity-0 transition-opacity"></span>
                                    <span
                                        class="material-symbols-outlined text-[20px] lg:text-[22px] group-hover:scale-110 transition-transform">admin_panel_settings</span>
                                    ผู้ใช้งานและการตั้งค่าระบบ
                                </a>
                            @endcan
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
                    <div class="prose prose-slate max-w-none text-slate-600 leading-relaxed space-y-3">
                        <p>
                            ระบบบริหารจัดการงานซ่อมบำรุง (Asset Repair Management System)
                            ถูกพัฒนาขึ้นเพื่อรวบรวมและติดตามขั้นตอนการซ่อมบำรุงทรัพย์สินของโรงพยาบาลพระปกเกล้า
                            ให้มีความรวดเร็ว โปร่งใส และสามารถตรวจสอบสถิติเชิงลึกได้
                        </p>
                        <p>
                            คู่มือนี้แบ่งเป็น 2 ส่วน คือ <b>บุคลากร / ผู้ใช้งานทั่วไป</b> (แจ้งซ่อม ติดตามงาน ปิดงาน และประเมินความพึงพอใจ) และ
                            <b>เจ้าหน้าที่เทคนิค / ระบบ</b> (รับงาน ดำเนินการ แดชบอร์ด และการตั้งค่า) ผู้ที่เป็นเจ้าหน้าที่ หัวหน้างาน
                            หรือผู้ดูแลระบบสลับดูได้จากแท็บด้านบน ส่วน <b>Live Chat</b> และ <b>ทะเบียนทรัพย์สิน</b> อยู่ท้ายคู่มือ ใช้ร่วมกันทุกกลุ่ม
                        </p>
                    </div>
                </section>

                {{-- Section: Getting started (Always Show) --}}
                <section id="getting-started" class="scroll-mt-24">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                        <span class="material-symbols-outlined text-[24px] text-[#0F2D5C]">login</span>
                        เริ่มต้นใช้งานและบัญชีของคุณ
                    </h2>
                    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden ">
                        <div class="p-6 space-y-8">
                            <div class="flex gap-4">
                                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                    1</div>
                                <div class="flex-1">
                                    <div class="font-bold text-slate-900 mb-1 text-[18px]">เข้าสู่ระบบ</div>
                                    <div class="text-[15px] text-slate-600 leading-relaxed space-y-2">กรอก <b>เลขบัตรประชาชน 13 หลัก (CID)</b> และรหัสผ่านที่หน้าเข้าสู่ระบบ ระบบจะพาไปที่ Dashboard</div>
                                </div>
                            </div>
                            <div class="flex gap-4 border-t border-slate-50 pt-8">
                                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                    2</div>
                                <div class="flex-1">
                                    <div class="font-bold text-slate-900 mb-1 text-[18px]">ยังไม่มีบัญชี</div>
                                    <div class="text-[15px] text-slate-600 leading-relaxed space-y-2">กด <b>Register</b> ใต้ปุ่มเข้าสู่ระบบ แล้วกรอกชื่อ-สกุล เลขบัตรประชาชน อีเมล (ไม่บังคับ) และรหัสผ่าน จะได้บัญชี "บุคลากรทั่วไป" ผู้ดูแลระบบจะกำหนดบทบาทและหน่วยงานให้ภายหลัง<p class="text-[13px] text-slate-500 italic">ระบบอยู่ในช่วงทดสอบภายในองค์กร การสมัครด้วยตนเองนี้ยังไม่ได้ตรวจสอบกับฐานข้อมูลบุคลากรของโรงพยาบาล</p></div>
                                </div>
                            </div>
                            <div class="flex gap-4 border-t border-slate-50 pt-8">
                                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                    3</div>
                                <div class="flex-1">
                                    <div class="font-bold text-slate-900 mb-1 text-[18px]">ลืมรหัสผ่าน</div>
                                    <div class="text-[15px] text-slate-600 leading-relaxed space-y-2">กด <b>ลืมรหัสผ่าน</b> แล้วกรอกอีเมลที่ผูกกับบัญชี ระบบจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปทางอีเมลนั้น (แม้จะเข้าสู่ระบบด้วยเลขบัตรประชาชนก็ตาม) หากบัญชีไม่มีอีเมล ให้ติดต่อผู้ดูแลระบบ</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                        <h3 class="font-bold text-slate-900 mb-3 text-[18px]">โปรไฟล์ของฉัน</h3>
                        <p class="text-[15px] text-slate-600 leading-relaxed">เมนู <b>"โปรไฟล์ของฉัน"</b> (ด้านล่างของเมนูซ้ายมือ) ใช้เปลี่ยนรูปโปรไฟล์ (ตัดรูปให้พอดีก่อนบันทึกได้) แก้ไขชื่อ อีเมล หน่วยงาน และ <b>เปลี่ยนรหัสผ่าน</b> ได้ด้วยตนเอง</p>
                        </div>
                        <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                        <h3 class="font-bold text-slate-900 mb-3 text-[18px]">เรื่องรหัสผ่านและความปลอดภัย</h3>
                        <ul class="space-y-3 text-[15px] text-slate-600">
                        <li class="flex items-start gap-3"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 mt-1.5 shrink-0"></span><span>รหัสผ่านยาวอย่างน้อย <b>8 ตัว</b> และมีทั้ง<b>ตัวอักษรและตัวเลข</b></span></li>
                        <li class="flex items-start gap-3"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 mt-1.5 shrink-0"></span><span>กรอกรหัสผ่านผิดหลายครั้งติดกัน ระบบจะให้รอสักครู่ก่อนลองใหม่ เพื่อป้องกันการเดารหัสผ่าน</span></li>
                        <li class="flex items-start gap-3"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 mt-1.5 shrink-0"></span><span>ถ้าผู้ดูแลระบบตั้งรหัสผ่านให้ คุณต้อง<b>ตั้งรหัสผ่านใหม่ด้วยตนเอง</b>ก่อนใช้งานส่วนอื่น</span></li>
                        <li class="flex items-start gap-3"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 mt-1.5 shrink-0"></span><span>บัญชีที่ถูกระงับเข้าสู่ระบบไม่ได้ (ข้อมูลงานเดิมยังอยู่ครบ) ให้ติดต่อผู้ดูแลระบบ</span></li>
                        </ul>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="font-bold text-slate-900 mb-3 text-[18px]">บทบาทของผู้ใช้งาน</h3>
                        <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
                        <table class="w-full min-w-[560px] text-[14px] text-left">
                        <thead class="bg-slate-50"><tr><th class="px-4 py-3 font-bold text-slate-700 w-48">บทบาท</th><th class="px-4 py-3 font-bold text-slate-700 ">ทำอะไรได้บ้าง</th></tr></thead>
                        <tbody>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">บุคลากรทั่วไป</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">แจ้งซ่อมและติดตามงานของตนเอง อนุมัติปิดงานและประเมินความพึงพอใจ ดู Dashboard และทะเบียนทรัพย์สิน ตั้งกระทู้และตอบใน Live Chat</td></tr>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">เจ้าหน้าที่ IT / ซ่อมบำรุง<br><span class="font-normal text-[12px] text-slate-500">IT Support, Network Engineer, Programmer, เจ้าหน้าที่ซ่อมบำรุง</span></td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">ทุกอย่างของบุคลากรทั่วไป และเมนู <b>รายการงานซ่อม</b> สำหรับรับงาน ดำเนินการ พักงาน และปิดงานซ่อม บันทึกรายงานการปฏิบัติงาน เพิ่ม/แก้ไขทรัพย์สิน ดู SLA Dashboard และ Technician Rating ล็อก/ปลดล็อกกระทู้</td></tr>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">หัวหน้างาน</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">จัดการทุกขั้นตอนของทุกใบงานและมอบหมายทีมได้ เพิ่ม แก้ไข และลบทรัพย์สิน ดู SLA Dashboard และ Technician Rating (ล็อกกระทู้ใน Live Chat ไม่ได้)</td></tr>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">ผู้ดูแลระบบ</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">ทุกอย่างข้างต้น และเมนู <b>การจัดการระบบ</b> (ประเภทใบแจ้งซ่อม การแจ้งเตือน ผู้ใช้งานระบบ) ลบกระทู้ได้ ตั้งกระทู้ได้ไม่จำกัดจำนวน</td></tr>
                        </tbody>
                        </table>
                        </div>
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
                                <p class="text-slate-600 mb-6">เข้าเมนู <b>"แจ้งซ่อมบำรุง"</b> แล้วกดปุ่ม <b>"สร้างใบแจ้งซ่อม"</b>
                                    (หรือกด <b>"สร้างคำขอซ่อมใหม่"</b> ในหน้ารายละเอียดของทรัพย์สินชิ้นนั้น ระบบจะเลือกทรัพย์สินให้ทันที) แล้วกรอกตามขั้นตอนดังนี้:</p>
                                <div class="space-y-8">
                                    <div class="flex gap-4">
                                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            1</div>
                                        <div class="flex-1">
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">ข้อมูลหลัก: ทรัพย์สิน หน่วยงาน สถานที่</div>
                                            <div class="text-[15px] text-slate-600 leading-relaxed space-y-2">พิมพ์รหัสหรือชื่อทรัพย์สินเพื่อค้นหาและเลือก ระบบจะดึงข้อมูลของทรัพย์สินนั้นมาให้ หากไม่ได้ซ่อมครุภัณฑ์ที่อยู่ในระบบให้เว้นไว้ได้ จากนั้นเลือกหน่วยงานและระบุสถานที่/ตำแหน่งที่ช่างต้องไปหา</div>
                                        </div>
                                    </div>
                                    <div class="flex gap-4 border-t border-slate-50 pt-8">
                                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            2</div>
                                        <div class="flex-1">
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">รายละเอียดปัญหา</div>
                                            <div class="text-[15px] text-slate-600 leading-relaxed space-y-2"><b>หัวข้อ</b> เป็นช่องเดียวที่ต้องกรอก ส่วน <b>รายละเอียด / อาการเสีย</b> ยิ่งชัดเจนช่างยิ่งวิเคราะห์ได้แม่นยำ ส่วน <b>ประเภทงาน</b> เลือกได้ตามความเหมาะสม (ช่วยให้ระบบกำหนดเวลาเป้าหมายในการดำเนินการ) หากไม่แน่ใจปล่อยให้เจ้าหน้าที่ระบุภายหลังได้</div>
                                        </div>
                                    </div>
                                    <div class="flex gap-4 border-t border-slate-50 pt-8">
                                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            3</div>
                                        <div class="flex-1">
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">ข้อมูลผู้แจ้ง</div>
                                            <div class="text-[15px] text-slate-600 leading-relaxed space-y-2">ตรวจสอบชื่อ เบอร์โทรศัพท์ และอีเมลให้ถูกต้อง เพื่อให้เจ้าหน้าที่ติดต่อกลับหรือเข้าถึงพื้นที่ได้อย่างรวดเร็ว</div>
                                        </div>
                                    </div>
                                    <div class="flex gap-4 border-t border-slate-50 pt-8">
                                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            4</div>
                                        <div class="flex-1">
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">แนบไฟล์ (ถ้ามี)</div>
                                            <div class="text-[15px] text-slate-600 leading-relaxed space-y-2">แนบได้สูงสุด <b>3 ไฟล์</b> เป็นรูปภาพ (JPG, PNG, WebP, HEIC) หรือ PDF ขนาดไม่เกิน <b>{{ (int) (config('uploads.max_kb') / 1024) }} MB</b> ต่อไฟล์ กด "แนบไฟล์เอกสาร" หรือ "ถ่ายรูปจากกล้อง" (บนมือถือ)</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-8 p-4 bg-slate-50 rounded-lg border border-slate-100 space-y-2 text-[14px] text-slate-600 leading-relaxed">
                                    <div class="flex gap-3"><span class="material-symbols-outlined text-slate-400 text-[20px] shrink-0">edit</span>
                                        <span>แก้ไขใบแจ้งซ่อมได้จนกว่าจะมีเจ้าหน้าที่รับผิดชอบ (สถานะ "รอดำเนินการ" หรือ "รับทราบแล้ว")</span></div>
                                    <div class="flex gap-3"><span class="material-symbols-outlined text-slate-400 text-[20px] shrink-0">attach_file</span>
                                        <span>เพิ่มหรือลบไฟล์แนบได้จนกว่าเจ้าหน้าที่จะเริ่มดำเนินการ</span></div>
                                    <div class="flex gap-3"><span class="material-symbols-outlined text-slate-400 text-[20px] shrink-0">build_circle</span>
                                        <span>ทรัพย์สินที่แจ้งซ่อมจะเปลี่ยนสถานะเป็น "กำลังซ่อม" ทันทีที่ส่งใบแจ้งซ่อม (ดูหัวข้อทะเบียนทรัพย์สิน)</span></div>
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
                        <div class="space-y-4 text-slate-600 leading-relaxed">
                            <p>
                                ติดตามงานของคุณได้ที่เมนู <b>"แจ้งซ่อมบำรุง"</b> ซึ่งแสดง <b>รายการใบงานซ่อมบำรุง</b> ที่คุณเป็นผู้แจ้ง
                                ค้นหาด้วยเลขใบงาน หัวข้อ หรือชื่อผู้แจ้ง และกรองตามสถานะหรือประเภทงานได้ กด "ดูรายละเอียด" เพื่อเปิดใบงาน
                            </p>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-blue-500 mt-0.5 shrink-0">timeline</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed"><b>แถบความคืบหน้า</b> แสดงขั้น แจ้งเรื่อง → รับทราบแล้ว → รับเรื่องแล้ว → กำลังดำเนินการ → เสร็จสิ้น</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-slate-500 mt-0.5 shrink-0">history</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">ปุ่ม <b>"ประวัติการดำเนินงาน"</b> แสดงทุกการเปลี่ยนสถานะ พร้อมเหตุผลที่เจ้าหน้าที่บันทึกไว้ เช่น เหตุที่พักงานหรือไม่รับเรื่อง</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-indigo-500 mt-0.5 shrink-0">groups</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">กล่อง <b>"เจ้าหน้าที่รับผิดชอบ"</b> บอกว่าใครเป็นผู้ดูแลงานของคุณ และหัวข้อ <b>"การวิเคราะห์เวลา"</b> แสดงกำหนดเสร็จตามเป้าหมาย</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-emerald-600 mt-0.5 shrink-0">print</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">ปุ่ม <b>"พิมพ์ PDF"</b> พิมพ์ใบงานของคุณเก็บไว้เป็นหลักฐานได้</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-rose-500 mt-0.5 shrink-0">cancel</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">ยกเลิกใบงานเองได้เมื่อเจ้าหน้าที่ <b>รับเรื่องแล้ว</b> (สถานะ รับเรื่องแล้ว / กำลังดำเนินการ / หยุดชั่วคราว) กดปุ่ม <b>"ยกเลิกการซ่อมบำรุง"</b> และระบุเหตุผล ก่อนหน้านั้นเจ้าหน้าที่เป็นผู้พิจารณารับเรื่องหรือไม่รับเรื่อง</span>
                                </li>
                            </ul>
                        </div>
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
                                        'desc' => 'เจ้าหน้าที่เทคนิคเห็นข้อมูลและรับทราบปัญหาแล้ว',
                                    ],
                                    [
                                        'icon' => 'thumb_up',
                                        'color' => 'text-emerald-600',
                                        'name' => 'รับเรื่องแล้ว',
                                        'desc' => 'เจ้าหน้าที่ตรวจสอบและรับเข้าสู่คิวงานเรียบร้อยแล้ว',
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
                                        'desc' => 'รออะไหล่ หรือติดปัญหาเฉพาะหน้า (เวลาช่วงนี้ไม่นับรวมใน SLA)',
                                    ],
                                    [
                                        'icon' => 'task_alt',
                                        'color' => 'text-emerald-600',
                                        'name' => 'ซ่อมบำรุงเสร็จสิ้น',
                                        'desc' => 'ช่างแก้ไขปัญหาเรียบร้อยแล้ว รอผู้แจ้งตรวจสอบและอนุมัติ',
                                    ],
                                    [
                                        'icon' => 'task',
                                        'color' => 'text-emerald-600',
                                        'name' => 'อนุมัติผลการซ่อมบำรุง',
                                        'desc' => 'ผู้แจ้งตรวจสอบและยืนยันรับมอบงานแล้ว (ปิดงาน)',
                                    ],
                                    [
                                        'icon' => 'cancel',
                                        'color' => 'text-rose-600',
                                        'name' => 'ยกเลิกการซ่อมบำรุง',
                                        'desc' => 'งานถูกยกเลิกโดยผู้แจ้ง เจ้าหน้าที่ผู้รับผิดชอบ หรือผู้ดูแล พร้อมเหตุผล',
                                    ],
                                    [
                                        'icon' => 'error',
                                        'color' => 'text-rose-700',
                                        'name' => 'ไม่รับเรื่อง',
                                        'desc' => 'ข้อมูลไม่ครบ แจ้งซ้ำ หรือไม่อยู่ในหน้าที่ของทีมเจ้าหน้าที่ (มีเหตุผลกำกับ)',
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
                                        หากพบว่าใช้งานได้ปกติแล้ว รบกวนกดอนุมัติทันที เพื่อให้ข้อมูลระยะเวลาการดำเนินงาน
                                        (SLA) ของหน่วยงานมีความถูกต้องและสะท้อนประสิทธิภาพที่แท้จริง
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden ">
                            <div class="p-6 space-y-6">
                            <div class="flex gap-4">
                                <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                    1</div>
                                <div class="flex-1">
                                    <div class="font-bold text-slate-900 mb-1 text-[18px]">กด "อนุมัติปิดงาน"</div>
                                    <div class="text-[15px] text-slate-600 leading-relaxed space-y-2">ปุ่มนี้ปรากฏเมื่อสถานะงานเป็น "ซ่อมบำรุงเสร็จสิ้น" การกดเป็นการยืนยันว่าท่านได้รับทรัพย์สินคืนและใช้งานได้ปกติแล้ว สถานะจะเปลี่ยนเป็น "อนุมัติผลการซ่อมบำรุง" <b>เจ้าหน้าที่กดแทนผู้แจ้งไม่ได้</b> (หัวหน้างานและผู้ดูแลระบบทำแทนได้)</div>
                                </div>
                            </div>
                            <div class="flex gap-4 border-t border-slate-50 pt-8">
                                <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold shrink-0 text-sm">
                                    2</div>
                                <div class="flex-1">
                                    <div class="font-bold text-slate-900 mb-1 text-[18px]">ประเมินความพึงพอใจ (ถ้ามี)</div>
                                    <div class="text-[15px] text-slate-600 leading-relaxed space-y-2">หลังอนุมัติ ระบบจะถามว่าต้องการประเมินเลยหรือไม่ ให้คะแนน 1–5 ดาวได้ตามความสมัครใจ <b>(ไม่ได้บังคับ)</b> ข้อมูลนี้ใช้พัฒนาคุณภาพการให้บริการของเจ้าหน้าที่ หากยังไม่ประเมิน กลับมาทำภายหลังได้ที่เมนู <b>"ประเมินความพึงพอใจ"</b> (แท็บ "รอประเมิน")</div>
                                </div>
                            </div>
                                <div class="mt-4 p-4 bg-slate-50 rounded-lg border border-slate-100">
                                    <div class="flex gap-3">
                                        <span class="material-symbols-outlined text-slate-400 text-[20px] shrink-0">verified_user</span>
                                        <div class="text-[13px] text-slate-500 italic leading-relaxed">
                                            หมายเหตุ: ประเมินได้เฉพาะ <b>ผู้แจ้งซ่อมของงานนั้น</b> ครั้งเดียวต่อใบงาน ภายใน
                                            {{ \App\Http\Controllers\Maintenance\MaintenanceRatingController::RATING_DEADLINE_DAYS }}
                                            วันหลังปิดงาน และเฉพาะงานที่มีสถานะ "อนุมัติผลการซ่อมบำรุง" และมีเจ้าหน้าที่ประจำงานแล้ว เพื่อความถูกต้องของสถิติผลงานช่าง
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
                        <p class="text-slate-600 leading-relaxed mb-6">
                            งานที่ต้องจัดการอยู่ในเมนู <b>"รายการงานซ่อม"</b> (มีตัวนับจำนวนงานแต่ละสถานะ ค้นหาและกรองได้ และมีปุ่มลัด รับทราบ / รับเรื่อง / ไม่รับเรื่อง บนการ์ดงาน)
                            หรือเมนู <b>"แจ้งซ่อมบำรุง"</b> เมื่อเปิดใบงานจะเห็นปุ่มของขั้นตอนถัดไป เฉพาะขั้นที่คุณมีสิทธิ์ทำในสถานะนั้น
                        </p>
                        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden ">
                            <div class="p-6 space-y-8">
                                <div class="flex gap-4">
                                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                        1</div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900 mb-1 text-[18px]">การรับงาน</div>
                                        <div class="text-[15px] text-slate-600 leading-relaxed space-y-2"><ul class="space-y-3">
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-blue-500 mt-0.5 shrink-0">assignment_turned_in</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">กด <b>"รับทราบ"</b> เพื่อยืนยันว่าได้รับรู้ปัญหาแล้ว (มีผลต่อสถิติเวลาตอบรับ)</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-emerald-600 mt-0.5 shrink-0">task_alt</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">กด <b>"รับเรื่อง"</b> เพื่อรับงานเข้าสู่คิวปฏิบัติงาน (มีผลต่อสถิติเวลารับงาน)</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-rose-500 mt-0.5 shrink-0">block</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">กด <b>"ไม่รับเรื่อง"</b> พร้อมระบุเหตุผล เมื่อข้อมูลไม่ครบ แจ้งซ้ำ หรือไม่ใช่หน้าที่ของทีม (ทำได้ในสถานะรอดำเนินการ / รับทราบแล้ว)</span>
                                </li>
                                </ul></div>
                                    </div>
                                </div>
                                <div class="flex gap-4 border-t border-slate-50 pt-8">
                                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                        2</div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900 mb-1 text-[18px]">มอบหมายทีมเจ้าหน้าที่</div>
                                        <div class="text-[15px] text-slate-600 leading-relaxed space-y-2"><ul class="space-y-3">
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-indigo-500 mt-0.5 shrink-0">groups</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">ที่กล่อง <b>"เจ้าหน้าที่รับผิดชอบ"</b> ในหน้าใบงาน กดปุ่มไอคอนเพิ่มคน <b>"มอบหมายทีมเจ้าหน้าที่"</b> (ปุ่มนี้แสดงเฉพาะผู้ที่มีสิทธิ์มอบหมายงานนั้น) ค้นหาชื่อ กรองตามตำแหน่ง (ตัวกรองถูกตั้งตามประเภทงานให้อัตโนมัติ) แล้วกดบันทึก</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-emerald-600 mt-0.5 shrink-0">how_to_reg</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">งานที่<b>ยังไม่มีเจ้าหน้าที่ในทีมจะเริ่มดำเนินการไม่ได้</b> เจ้าหน้าที่ที่กด "ดำเนินการ" จะถูกบันทึกเป็นผู้รับผิดชอบหลักของงานนั้น</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-slate-500 mt-0.5 shrink-0">swap_horiz</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">เจ้าหน้าที่ส่งต่องานที่ตนอยู่ในทีม (เช่น IT Support → Programmer) หรือจัดคนให้งานที่ยังไม่มีผู้รับผิดชอบได้ ส่วนการแก้ทีมของงานที่คนอื่นทำอยู่และงานที่ซ่อมเสร็จแล้ว เป็นหน้าที่ของหัวหน้างานและผู้ดูแลระบบ</span>
                                </li>
                                </ul></div>
                                    </div>
                                </div>
                                <div class="flex gap-4 border-t border-slate-50 pt-8">
                                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                        3</div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900 mb-1 text-[18px]">การดำเนินการ</div>
                                        <div class="text-[15px] text-slate-600 leading-relaxed space-y-2"><ul class="space-y-3">
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-sky-600 mt-0.5 shrink-0">play_circle</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">กด <b>"ดำเนินการ"</b> เมื่อเริ่มลงมือซ่อมบำรุง ณ สถานที่จริง</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-amber-500 mt-0.5 shrink-0">pause_circle</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">หากต้องรออะไหล่หรือติดปัญหาเฉพาะหน้า กด <b>"หยุดชั่วคราว"</b> และระบุเหตุผล (เฉพาะผู้ที่ได้รับมอบหมาย) <b>เวลาช่วงที่หยุดไม่ถูกนับใน SLA</b></span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-blue-500 mt-0.5 shrink-0">keyboard_double_arrow_right</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">เมื่อพร้อมแล้วกด <b>"กลับเข้าดำเนินการ"</b> (งานที่หยุดอยู่ปิดซ่อมไม่ได้ ต้องกลับเข้าดำเนินการก่อน)</span>
                                </li>
                                </ul></div>
                                    </div>
                                </div>
                                <div class="flex gap-4 border-t border-slate-50 pt-8">
                                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                        4</div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900 mb-1 text-[18px]">การปิดงาน (สำคัญต่อ SLA)</div>
                                        <div class="text-[15px] text-slate-600 leading-relaxed space-y-2"><ul class="space-y-3">
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-emerald-600 mt-0.5 shrink-0">done_all</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">เมื่อดำเนินการเสร็จ กด <b>"เสร็จสิ้น"</b> และระบุรายละเอียดการแก้ปัญหา (เฉพาะผู้ที่ได้รับมอบหมาย) สถานะเป็น "ซ่อมบำรุงเสร็จสิ้น"</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-amber-500 mt-0.5 shrink-0">info</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed"><b>โปรดขอความร่วมมือผู้แจ้ง:</b> ตรวจสอบงานและกด <b>"อนุมัติปิดงาน"</b> ทันที เจ้าหน้าที่ปิดงานแทนผู้แจ้งไม่ได้ (หัวหน้างานและผู้ดูแลระบบทำแทนได้)</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-rose-500 mt-0.5 shrink-0">cancel</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">ถ้าต้องยกเลิก กด <b>"ยกเลิกการซ่อมบำรุง"</b> พร้อมเหตุผล ทำได้ในสถานะรับเรื่องแล้ว / กำลังดำเนินการ / หยุดชั่วคราว</span>
                                </li>
                                </ul></div>
                                    </div>
                                </div>
                                <div class="flex gap-4 border-t border-slate-50 pt-8">
                                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                        5</div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900 mb-1 text-[18px]">บันทึกรายงานและเอกสาร</div>
                                        <div class="text-[15px] text-slate-600 leading-relaxed space-y-2"><ul class="space-y-3">
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-indigo-500 mt-0.5 shrink-0">description</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">ส่วน <b>"รายงานการปฏิบัติงานและค่าใช้จ่าย"</b> บันทึกวิธีคิดค่าใช้จ่าย (เบิกอะไหล่ / ค่าจ้างซ่อม-บริการ / อื่น ๆ) รพจ. ประเภทงานที่ปฏิบัติ และหมายเหตุ บันทึกภายหลังได้ แต่หลังปิดงานแล้วแก้ไขได้เฉพาะหัวหน้างานและผู้ดูแลระบบ</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-slate-500 mt-0.5 shrink-0">attach_file</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">แนบไฟล์ได้สูงสุด 3 ไฟล์ต่อใบงาน (ผู้ที่ได้รับมอบหมายแนบ/ลบได้ระหว่างทำงาน)</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-emerald-600 mt-0.5 shrink-0">print</span>
                                    <span class="text-[15px] text-slate-600 leading-relaxed">ปุ่ม <b>"พิมพ์ PDF"</b> พิมพ์ใบงาน และปุ่ม <b>"ประวัติการดำเนินงาน"</b> ดูทุกขั้นตอนที่ผ่านมา</span>
                                </li>
                                </ul></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Staff Guidelines for SLA Accuracy --}}
                        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-6 mt-8">
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-indigo-600">campaign</span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-indigo-900 text-lg mb-2">แนวทางการประสานงานผู้แจ้ง (สำคัญต่อผล SLA)</h4>
                                    <p class="text-indigo-700 text-[15px] leading-relaxed mb-4">
                                        เพื่อให้ค่าสถิติสะท้อนประสิทธิภาพการทำงานที่แท้จริง เจ้าหน้าที่ควรปฏิบัติตามแนวทางดังนี้:
                                    </p>
                                    <div class="space-y-6">
                                        <ul class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <li class="bg-white/60 p-4 rounded-lg border border-indigo-100/50 ">
                                                <div class="font-bold text-indigo-900 text-[15px] mb-1 flex items-center gap-2">1. แจ้งเมื่อเริ่มงาน</div>
                                                <p class="text-[14px] text-indigo-800 leading-relaxed">เมื่อกด <b>"ดำเนินการ"</b> ควรแจ้งผู้รับบริการให้ทราบถึงแผนการซ่อมเบื้องต้นและระยะเวลาที่คาดว่าจะเสร็จ</p>
                                            </li>
                                            <li class="bg-white/60 p-4 rounded-lg border border-indigo-100/50 ">
                                                <div class="font-bold text-indigo-900 text-[15px] mb-1 flex items-center gap-2">2. ตรวจสอบหน้างานจริง</div>
                                                <p class="text-[14px] text-indigo-800 leading-relaxed">เมื่อซ่อมเสร็จ โปรดให้ผู้แจ้งตรวจสอบผลงานจนเป็นที่พอใจก่อนที่คุณจะกด <b>"เสร็จสิ้น"</b> ในระบบ</p>
                                            </li>
                                        </ul>
                                        <div class="bg-indigo-600 p-5 rounded-lg border border-indigo-700">
                                            <div class="relative z-10">
                                                <div class="text-white font-bold mb-2 text-lg">สำคัญที่สุด: กำชับการ "อนุมัติปิดงาน"</div>
                                                <p class="text-indigo-50 leading-relaxed text-[15px]">
                                                    ขอความร่วมมือให้ผู้แจ้งกด <b>"อนุมัติปิดงาน"</b> ในระบบ <b>ทันที</b> หลังจากตรวจสอบเสร็จสิ้น
                                                    เพื่อให้สถิติเวลาที่ทำงานจริงถูกบันทึกอย่างแม่นยำ
                                                </p>
                                            </div>
                                        </div>
                                    </div>
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
                            <h3 class="font-bold text-slate-900 mb-2 text-[16px]">Main Dashboard</h3>
                            <p class="text-[14px] text-slate-600 leading-relaxed">ภาพรวมการแจ้งซ่อมของทั้งโรงพยาบาล: ใบแจ้งซ่อมสะสม สถิติปีนี้ งานคงค้าง งานที่เสร็จสิ้น และกราฟแนวโน้ม กรองตามช่วงเวลาได้ ผู้ที่เป็นเจ้าหน้าที่ขึ้นไปจะเห็นปริมาณงานที่แต่ละคนรับผิดชอบด้วย (เมนู Dashboard)</p>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                            <h3 class="font-bold text-slate-900 mb-2 text-[16px]">SLA Dashboard</h3>
                            <p class="text-[14px] text-slate-600 leading-relaxed">แสดงเวลาตอบรับ เวลารับงาน เวลาแก้ไขงาน และ <b>อัตราบรรลุเป้าหมาย SLA</b> พร้อมกราฟแนวโน้ม การกระจายของงาน และงานที่เกินเวลาแยกตามประเภท กด <b>"พิมพ์รายงานสรุป SLA"</b> เพื่อออกรายงาน PDF: เลือกงานที่เกินเวลาที่จะแสดง ใส่หมายเหตุ และลายเซ็นผู้จัดทำ</p>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                            <h3 class="font-bold text-slate-900 mb-2 text-[16px]">Technician Rating</h3>
                            <p class="text-[14px] text-slate-600 leading-relaxed">สรุปผลการประเมินเจ้าหน้าที่: อันดับคะแนนเฉลี่ย จำนวนที่ถูกประเมิน และระดับ (ดีมาก / ดี / ปานกลาง / ควรปรับปรุง) ค้นหาและเรียงลำดับได้ กด "ดูรายละเอียด" เพื่อดูคะแนนรายบุคคล หากถูกประเมินน้อยกว่า {{ \App\Support\RatingLevel::ENOUGH_REVIEWS }} ครั้ง ระบบจะเตือนว่าข้อมูลยังน้อย ไม่ควรตัดสินจากคะแนนเพียงไม่กี่ครั้ง</p>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                            <h3 class="font-bold text-slate-900 mb-2 text-[16px]">ประเมินความพึงพอใจ</h3>
                            <p class="text-[14px] text-slate-600 leading-relaxed">เมนูของผู้แจ้ง สำหรับให้คะแนนงานที่ปิดแล้ว แท็บ "รอประเมิน" เรียงงานที่ใกล้หมดเวลาไว้บนสุด และแท็บ "ประเมินแล้ว" ดูประวัติการให้คะแนน</p>
                            </div>
                        </div>
                        <p class="text-[13px] text-slate-500 mt-4">SLA Dashboard และ Technician Rating เปิดให้ผู้ดูแลระบบ หัวหน้างาน และเจ้าหน้าที่ IT / ซ่อมบำรุง</p>
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
                            <p class="text-[15px] text-slate-600 leading-relaxed mb-4">ผู้ดูแลระบบกำหนด <b>"ประเภทงานซ่อม"</b> (เช่น Software, Network, Hardware) ได้ที่เมนู <b>การจัดการระบบ → ประเภทใบแจ้งซ่อม</b> โดยระบุชื่อ คำอธิบาย ลำดับการแสดงผล เปิด/ปิดการใช้งาน และเวลาเป้าหมาย</p>
                            <p class="text-[15px] text-slate-600 leading-relaxed">ประเภทช่วยจัดกลุ่มข้อมูลและใช้คำนวณ SLA ของใบแจ้งซ่อม รวมทั้งตั้งตัวกรองตำแหน่งเริ่มต้นเมื่อมอบหมายทีม (การมอบหมายทีมยังเลือกเองทุกครั้ง ไม่ได้ทำให้อัตโนมัติ) เจ้าหน้าที่เปลี่ยนประเภทของใบงานได้จากหน้ารายการงานซ่อม</p>
                            </div>
                            <div class="bg-white border border-slate-200 rounded-lg p-6 ">
                            <h3 class="font-bold text-slate-900 mb-3 text-[18px]">การกำหนดค่าเป้าหมาย SLA</h3>
                            <p class="text-[15px] text-slate-600 leading-relaxed mb-4">ค่าเป้าหมายรายประเภทเป็น <b>หน่วยนาที</b> ตั้งได้ในหน้าประเภทใบแจ้งซ่อม หรือแก้ทุกประเภทพร้อมกันที่ตาราง <b>SLA Configuration</b> ใน SLA Dashboard (ผู้ดูแลระบบ หัวหน้างาน และเจ้าหน้าที่แก้ค่าที่ SLA Dashboard ได้) เพื่อใช้เป็นเกณฑ์วัดผลของทีมซ่อมบำรุง:</p>
                            <ul class="space-y-3 text-[15px] text-slate-600">
                            <li class="flex items-start gap-3"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 mt-1.5 shrink-0"></span><span><b>เวลาตอบกลับ (Response Time):</b> ช่วงเวลาเป้าหมายที่เจ้าหน้าที่ควรกด <b>"รับทราบ"</b> หลังมีการแจ้งซ่อม</span></li>
                            <li class="flex items-start gap-3"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 mt-1.5 shrink-0"></span><span><b>เวลาซ่อมแซม (Resolution Time):</b> ช่วงเวลาเป้าหมายที่งานประเภทนั้นควรดำเนินการจนถึง <b>"ซ่อมบำรุงเสร็จสิ้น"</b></span></li>
                            </ul>
                            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100/50">
                            <div class="text-[14px] text-blue-800 font-bold mb-1">กลไกการคำนวณกำหนดเสร็จอัตโนมัติ</div>
                            <p class="text-[13px] text-blue-700 leading-relaxed">เมื่อใบแจ้งซ่อมมีประเภท ระบบจะนำวันที่แจ้งบวกเวลาเป้าหมายของประเภทนั้นเป็น "กำหนดเสร็จ" ให้ทันที ถ้าเปลี่ยนประเภทภายหลังจะคำนวณใหม่ และเวลาที่งานหยุดชั่วคราวจะไม่ถูกนับ</p>
                            </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-6">
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-blue-600">info</span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-blue-900 text-lg mb-1">หมายเหตุสำคัญ</h4>
                                    <p class="text-blue-700 text-[15px] leading-relaxed">
                                        การแก้ไขค่า SLA ของประเภทงานมีผลกับ <b>"ใบแจ้งซ่อมที่ถูกกำหนดประเภทหลังจากนั้นเท่านั้น"</b>
                                        ใบงานที่มีกำหนดเสร็จอยู่แล้วยังคงกำหนดเดิม เว้นแต่จะมีการเปลี่ยนประเภทของใบงานนั้น
                                        หน้า SLA Dashboard สรุปประสิทธิภาพของทีมเทียบกับกำหนดเสร็จของแต่ละใบงาน
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Section: System administration (admins only) --}}
                    @can('manage-system')
                        <section id="system-admin" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                                <span class="material-symbols-outlined text-[24px] text-slate-700">admin_panel_settings</span>
                                ผู้ใช้งานและการตั้งค่าระบบ (ผู้ดูแลระบบ)
                            </h2>
                            <div class="bg-white border border-slate-200 rounded-lg overflow-hidden ">
                                <div class="p-6 space-y-8">
                                    <div class="flex gap-4">
                                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            1</div>
                                        <div class="flex-1">
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">ผู้ใช้งานระบบ</div>
                                            <div class="text-[15px] text-slate-600 leading-relaxed space-y-2"><ul class="space-y-3">
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-indigo-500 mt-0.5 shrink-0">manage_accounts</span>
                                        <span class="text-[15px] text-slate-600 leading-relaxed">เมนู <b>การจัดการระบบ → ผู้ใช้งานระบบ</b> ค้นหาผู้ใช้ และแก้ไขชื่อ เลขบัตรประชาชน อีเมล <b>หน่วยงาน</b> และ <b>บทบาท</b> (ผู้ที่สมัครเองเริ่มเป็นบุคลากรทั่วไป)</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-amber-500 mt-0.5 shrink-0">key</span>
                                        <span class="text-[15px] text-slate-600 leading-relaxed">ตั้ง <b>รหัสผ่านใหม่</b> ให้ผู้ใช้ได้ (ขั้นต่ำ 8 ตัว) เจ้าของบัญชีจะต้องเปลี่ยนรหัสผ่านเองเมื่อเข้าใช้ครั้งถัดไป</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-rose-500 mt-0.5 shrink-0">block</span>
                                        <span class="text-[15px] text-slate-600 leading-relaxed"><b>ระงับ / เปิดใช้งานบัญชี</b>: บัญชีที่ถูกระงับเข้าระบบไม่ได้และมอบหมายงานใหม่ให้ไม่ได้ แต่ประวัติงานยังอยู่ครบ (ระงับบัญชีของตนเองไม่ได้) ระบบไม่มีการลบผู้ใช้</span>
                                    </li>
                                    </ul></div>
                                        </div>
                                    </div>
                                    <div class="flex gap-4 border-t border-slate-50 pt-8">
                                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            2</div>
                                        <div class="flex-1">
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">การแจ้งเตือน (เสียง)</div>
                                            <div class="text-[15px] text-slate-600 leading-relaxed space-y-2"><ul class="space-y-3">
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-blue-500 mt-0.5 shrink-0">notifications_active</span>
                                        <span class="text-[15px] text-slate-600 leading-relaxed">เมนู <b>การจัดการระบบ → การแจ้งเตือน</b> เลือกเสียงแจ้งเตือนงานใหม่ ทดสอบเสียง และอัปโหลดไฟล์ .mp3 / .wav เข้าคลังเสียง (ใช้ร่วมกันทั้งระบบ)</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-slate-500 mt-0.5 shrink-0">volume_up</span>
                                        <span class="text-[15px] text-slate-600 leading-relaxed">เจ้าหน้าที่แต่ละคนเปิด/ปิดเสียงเตือนงานใหม่ของตนเองได้ที่ปุ่มกระดิ่งบนแถบด้านบน (บุคลากรทั่วไปไม่มีปุ่มนี้)</span>
                                    </li>
                                    </ul></div>
                                        </div>
                                    </div>
                                    <div class="flex gap-4 border-t border-slate-50 pt-8">
                                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shrink-0 text-sm">
                                            3</div>
                                        <div class="flex-1">
                                            <div class="font-bold text-slate-900 mb-1 text-[18px]">ประเภทใบแจ้งซ่อมและกระทู้</div>
                                            <div class="text-[15px] text-slate-600 leading-relaxed space-y-2"><ul class="space-y-3">
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-emerald-600 mt-0.5 shrink-0">build_circle</span>
                                        <span class="text-[15px] text-slate-600 leading-relaxed">เมนู <b>ประเภทใบแจ้งซ่อม</b> เพิ่ม/แก้ไข/ปิดใช้งานประเภทงาน และเป้าหมาย SLA (ดูหัวข้อด้านบน)</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-indigo-500 mt-0.5 shrink-0">forum</span>
                                        <span class="text-[15px] text-slate-600 leading-relaxed">ใน Live Chat ผู้ดูแลระบบลบกระทู้ของใครก็ได้ ล็อก/ปลดล็อก ลบข้อความ และตั้งกระทู้ได้ไม่จำกัดจำนวนต่อวัน</span>
                                    </li>
                                    </ul></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @endcan
                </div>

                {{-- Section: Live Chat (Always Show) --}}
                <section id="chat-communication" class="scroll-mt-24">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                        <span class="material-symbols-outlined text-[24px] text-indigo-500">forum</span>
                        การสื่อสารผ่าน Live Chat
                    </h2>
                    @php
                        $chatPerDay = (int) config('chat.threads_per_day');
                        $chatBurst = (int) config('chat.message_burst_max');
                        $chatBurstSeconds = (int) config('chat.message_burst_seconds');
                        $chatLockDays = (int) config('chat.lock_idle_after_days');
                        $chatDeleteDays = (int) config('chat.delete_locked_after_days');
                        $chatPurgeDays = (int) config('chat.purge_deleted_after_days');
                        $chatWarnDays = (int) config('chat.warn_days_before');
                    @endphp
                    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden relative">
                        <div class="p-6 relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
                            <div class="space-y-6">
                                <h3 x-show="activeRole === 'user'" class="text-[18px] font-bold text-slate-900">กระดานสนทนาภายในองค์กร</h3>
                                <h3 x-show="activeRole === 'staff'" x-cloak class="text-[18px] font-bold text-slate-900">กระดานสนทนาและการดูแลกระทู้</h3>
                                <p class="text-slate-600 text-[15px] leading-relaxed">
                                    เมนู <b>Livechat</b> คือ<b>กระดานสนทนากลาง</b> ทุกคนที่เข้าสู่ระบบเห็นทุกกระทู้และตอบได้ ใช้สอบถามข้อมูลเบื้องต้น ประสานงาน หรือพูดคุยเรื่องงานซ่อมกับเจ้าหน้าที่
                                    ข้อความใหม่แสดงทันทีโดยไม่ต้องรีเฟรชหน้า (ไม่ใช่ห้องส่วนตัว และไม่ผูกกับใบงานใดใบงานหนึ่ง)
                                </p>
                                <div class="space-y-5">
                                    <div class="flex gap-4">
                                        <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-indigo-600">search</span>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 text-[15px]">เปิดอ่านและค้นหา</div>
                                            <p class="text-[14px] text-slate-500 leading-relaxed">มี 3 แท็บ: <b>ทั้งหมด</b> / <b>กระทู้ที่มีส่วนร่วม</b> (ที่คุณตั้งหรือเคยตอบ) / <b>ซ่อนไว้</b> (แสดงเมื่อมีกระทู้ที่ซ่อน) ค้นหาจากหัวข้อได้ ป้าย "ใหม่ N" คือจำนวนข้อความที่คุณยังไม่ได้อ่าน</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-4">
                                        <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-indigo-600">add_comment</span>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 text-[15px]">ตั้งกระทู้ใหม่</div>
                                            <p class="text-[14px] text-slate-500 leading-relaxed">กด <b>"สร้างกระทู้ใหม่"</b> ตั้งได้ <b>วันละ {{ $chatPerDay }} กระทู้</b> (นับใหม่ตั้งแต่ 00:00 น. ตามเวลาไทย) แถบ <b>"จำนวนการตั้งกระทู้ของคุณวันนี้คงเหลือ"</b> ใต้ชื่อกระดานสนทนาแสดงเป็นเศษส่วน เช่น 5/5 (เป็นสีเหลืองเมื่อเหลือ 1 ครั้ง และปิดปุ่มเมื่อครบ) หัวข้อจะปรากฏต่อผู้ใช้ทุกคน ผู้ดูแลระบบไม่จำกัดจำนวน</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-4">
                                        <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-indigo-600">send</span>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 text-[15px]">ส่งข้อความ</div>
                                            <p class="text-[14px] text-slate-500 leading-relaxed">พิมพ์ได้ไม่เกิน 3,000 ตัวอักษร กด Enter เพื่อส่ง (Shift + Enter ขึ้นบรรทัดใหม่) มีปุ่ม Emoji เลื่อนขึ้นบนสุดเพื่อโหลดข้อความก่อนหน้า ส่งถี่เกิน {{ $chatBurst }} ข้อความใน {{ $chatBurstSeconds }} วินาที ระบบจะให้รอสักครู่</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-4">
                                        <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-indigo-600">visibility_off</span>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 text-[15px]">ซ่อนกระทู้ที่ไม่ต้องการเห็น</div>
                                            <p class="text-[14px] text-slate-500 leading-relaxed">กดไอคอนซ่อนในกระทู้เพื่อนำออกจากรายการ "กระทู้ที่มีส่วนร่วม" ของคุณ (ไม่กระทบคนอื่น) กระทู้จะกลับมาเมื่อคุณพิมพ์ในกระทู้นั้นอีก หรือเปิดคืนได้ที่แท็บ "ซ่อนไว้"</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-4">
                                        <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-indigo-600">notifications_active</span>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 text-[15px]">แชทลอยและการแจ้งเตือน</div>
                                            <p class="text-[14px] text-slate-500 leading-relaxed">ปุ่มลอยมุมจอ <b>"กระทู้ที่มีส่วนร่วม"</b> แสดงกระทู้ที่คุณมีส่วนร่วมพร้อมเลขข้อความใหม่ และมีเสียงเตือนเมื่อมีข้อความเข้า กดกระดิ่งในแชทลอยเพื่ออนุญาตการแจ้งเตือนบนเดสก์ท็อป และ "ไปที่กระทู้ทั้งหมด" เพื่อเปิดหน้า Livechat</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="relative">
                                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden aspect-[4/3] lg:aspect-auto">
                                    {{-- Mock UI for the chat widget --}}
                                    <div class="bg-[#0F2D5C] p-4 flex items-center gap-3 text-white">
                                        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold">C</div>
                                        <div class="flex-1">
                                            <div class="text-sm font-bold text-white">กระทู้ที่มีส่วนร่วม</div>
                                            <div class="text-[10px] opacity-80">กระทู้ที่คุณตั้งหรือเคยตอบ</div>
                                        </div>
                                    </div>
                                    <div class="p-3 border-b border-slate-100 italic text-[11px] text-slate-400">ตัวอย่างรายการในแชทลอย</div>
                                    <div class="p-3 space-y-4">
                                        <div class="flex gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs">ซ</div>
                                            <div class="flex-1 space-y-1">
                                                <div class="flex justify-between items-center text-slate-900">
                                                    <span class="text-xs font-bold">ซ่อมแอร์ไม่เย็น...</span>
                                                    <span class="px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[9px] font-bold">ใหม่ 2</span>
                                                </div>
                                                <div class="text-[11px] text-slate-500 line-clamp-1">ช่าง: กำลังเข้าไปตรวจสอบครับ...</div>
                                            </div>
                                        </div>
                                        <div class="flex gap-3 pt-3 border-t border-slate-50">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs">C</div>
                                            <div class="flex-1 space-y-1 opacity-50">
                                                <div class="flex justify-between items-center text-slate-900">
                                                    <span class="text-xs font-bold">Computer เปิดไม่ติด</span>
                                                </div>
                                                <div class="text-[11px] text-slate-500 line-clamp-1">ตรวจสอบสายไฟเรียบร้อยแล้วค่ะ</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- Badge Decoration --}}
                                <div class="absolute -right-4 -bottom-4 w-12 h-12 bg-rose-500 rounded-full flex items-center justify-center text-white font-bold border-4 border-white animate-pulse">2</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="font-bold text-slate-900 mb-3 text-[18px]">ใครทำอะไรได้ในกระทู้</h3>
                        <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
                        <table class="w-full min-w-[560px] text-[14px] text-left">
                        <thead class="bg-slate-50"><tr><th class="px-4 py-3 font-bold text-slate-700 w-48">การกระทำ</th><th class="px-4 py-3 font-bold text-slate-700 w-64">ทำได้โดย</th><th class="px-4 py-3 font-bold text-slate-700 ">หมายเหตุ</th></tr></thead>
                        <tbody>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">ตอบในกระทู้</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">ทุกคน</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">เมื่อกระทู้ยังไม่ถูกล็อก</td></tr>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">แก้ไขข้อความ</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">เจ้าของข้อความเท่านั้น (ขณะกระทู้ยังไม่ล็อก)</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">กดจุดสามจุด (⋮) ข้างข้อความ แล้วเลือก "แก้ไข" ข้อความจะเปลี่ยนเป็นช่องพิมพ์ในที่เดิม (Enter บันทึก, Esc ยกเลิก) และมีป้าย "แก้ไขแล้ว" กำกับให้ทุกคนเห็น ผู้ดูแลลบข้อความของคนอื่นได้ แต่แก้ไขแทนไม่ได้</td></tr>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">ลบข้อความ</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">เจ้าของข้อความ (ขณะกระทู้ยังไม่ล็อก) และผู้ดูแลกระทู้</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">กดจุดสามจุด (⋮) แล้วเลือก "ลบ" และยืนยันในหน้าต่างที่ขึ้นมา ข้อความจะกลายเป็น "ข้อความนี้ถูกลบ" สำหรับทุกคน ไม่มีใครเห็นเนื้อหาเดิมอีก</td></tr>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">ล็อก / ปลดล็อกกระทู้</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">ผู้ดูแลระบบ และทีม IT / ช่าง (IT Support, Network Engineer, Programmer, เจ้าหน้าที่ซ่อมบำรุง)</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">หัวหน้างาน บุคลากรทั่วไป และแม้แต่เจ้าของกระทู้ ล็อกหรือปลดล็อกไม่ได้ กระทู้ที่ล็อกจะไม่มีใครส่งข้อความเพิ่มได้</td></tr>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">ลบกระทู้</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">เจ้าของกระทู้ และผู้ดูแลระบบ</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">กระทู้และข้อความทั้งหมดถูกซ่อนทันที</td></tr>
                        <tr class="border-t border-slate-100"><td class="px-4 py-3 align-top text-slate-600 leading-relaxed font-semibold text-slate-900">ซ่อนกระทู้</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">ทุกคน (เฉพาะรายการของตนเอง)</td><td class="px-4 py-3 align-top text-slate-600 leading-relaxed">ไม่กระทบผู้อื่น คนที่มีส่วนร่วมแต่ไม่ใช่เจ้าของกระทู้ใช้ "ซ่อน" แทนการลบ</td></tr>
                        </tbody>
                        </table>
                        </div>
                    </div>

                    @if ($chatLockDays > 0 || $chatDeleteDays > 0)
                        <div class="mt-6 bg-amber-50 border border-amber-100 rounded-xl p-6">
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-amber-600">hourglass_top</span>
                                </div>
                                <div class="space-y-1">
                                    <div class="font-bold text-amber-900 text-lg">อายุของกระทู้</div>
                                    <p class="text-amber-700 text-[15px] leading-relaxed">
                                        เพื่อไม่เก็บข้อมูลส่วนบุคคลไว้นานเกินความจำเป็น ระบบจัดการกระทู้ที่ไม่มีการใช้งานให้อัตโนมัติทุกคืน:
                                    </p>
                                    <ul class="list-disc pl-5 space-y-1 text-amber-700 text-[15px] leading-relaxed">
                                        @if ($chatLockDays > 0)
                                            <li>กระทู้ที่<b>ไม่มีการตอบครบ {{ $chatLockDays }} วัน</b> จะถูก<b>ล็อก</b>อัตโนมัติ ผู้ดูแลกระทู้ปลดล็อกได้ และการปลดล็อกจะเริ่มนับใหม่</li>
                                        @endif
                                        @if ($chatDeleteDays > 0)
                                            <li>กระทู้ที่<b>ถูกล็อกครบ {{ $chatDeleteDays }} วัน</b>โดยไม่มีการปลดล็อก จะถูก<b>ลบ</b>อัตโนมัติ@if ($chatPurgeDays > 0) และหลังจากนั้นอีก {{ $chatPurgeDays }} วันจะถูกลบถาวร (ก่อนถึงเวลานั้นผู้ดูแลระบบกู้คืนได้)@endif</li>
                                        @endif
                                        @if ($chatLockDays > 0 && $chatWarnDays > 0)
                                            <li>ระบบเตือนล่วงหน้าในกระทู้: ก่อนถูกล็อก {{ $chatWarnDays }} วันจะแจ้งวันที่ และกระทู้ที่ล็อกแล้วจะบอกวันที่ที่จะถูกลบ</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif
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
                                <h3 class="text-xl font-bold mb-4">ข้อมูลครุภัณฑ์และประวัติการซ่อมในที่เดียว</h3>
                                <p class="text-slate-300 mb-6 text-[15px] leading-relaxed">
                                    เมนู <b>"ทะเบียนทรัพย์สิน"</b> เปิดให้ทุกคนดู ค้นหาจากรหัส ชื่อ หรือ Serial number และกรองตามสถานะ ประเภท ที่ตั้ง หมวดหมู่ และหน่วยงานเจ้าของ
                                    เมื่อเปิดรายการจะเห็นรายละเอียด เอกสารและไฟล์แนบ (เช่น คู่มือ) และ <b>ประวัติการแจ้งซ่อมล่าสุด</b> ของทรัพย์สินชิ้นนั้น
                                    กด <b>"สร้างคำขอซ่อมใหม่"</b> ได้จากหน้านี้ ระบบจะเลือกทรัพย์สินให้ทันที
                                </p>
                                <div class="p-4 bg-white/5 rounded-lg border border-white/10 mb-6">
                                    <div class="text-teal-400 font-bold mb-1 text-[14px]">การเชื่อมโยงสถานะอัตโนมัติ</div>
                                    <p class="text-slate-400 text-[13px] leading-relaxed">
                                        ทรัพย์สินเปลี่ยนเป็น <b>"กำลังซ่อม"</b> ทันทีที่มีใบแจ้งซ่อมค้างอยู่ (ตั้งแต่ "รอดำเนินการ" จนถึง "หยุดชั่วคราว")
                                        และกลับเป็น <b>"ใช้งานปกติ"</b> เมื่อใบงานสุดท้ายจบลง คือ ซ่อมบำรุงเสร็จสิ้น อนุมัติผล ยกเลิก หรือไม่รับเรื่อง
                                        หากยังมีใบแจ้งซ่อมอื่นของทรัพย์สินชิ้นนั้นค้างอยู่ สถานะจะยังเป็น "กำลังซ่อม" ส่วน "จำหน่ายแล้ว" ระบบไม่เปลี่ยนให้
                                    </p>
                                </div>
                                <div class="p-4 bg-white/5 rounded-lg border border-white/10 mb-6">
                                    <div class="text-teal-400 font-bold mb-1 text-[14px]">สำหรับเจ้าหน้าที่</div>
                                    <p class="text-slate-400 text-[13px] leading-relaxed">
                                        ผู้ดูแลระบบ หัวหน้างาน และเจ้าหน้าที่ IT / ซ่อมบำรุง กด <b>"ลงทะเบียน"</b> เพื่อเพิ่มทรัพย์สิน และแก้ไขข้อมูลได้ (ลบได้เฉพาะหัวหน้างานและผู้ดูแลระบบ)
                                        แนบเอกสารได้ทั้งรูปภาพ PDF และเอกสารสำนักงาน (Word, Excel, PowerPoint, TXT, CSV) ไม่เกิน {{ (int) (config('uploads.max_kb') / 1024) }} MB ต่อไฟล์
                                        ปุ่ม <b>"ดึงข้อมูล HIS"</b> ช่วยกรอกข้อมูลจากเลขทะเบียน รพจ. ขณะนี้เป็น<b>ข้อมูลจำลองเพื่อทดสอบ</b> ยังไม่เชื่อมกับระบบ HIS จริง
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-x-6 gap-y-2 text-slate-400 text-sm font-medium">
                                    <div class="flex items-center gap-2"><span class="material-symbols-outlined text-[14px] text-teal-600">check</span> ค้นหาและกรอง</div>
                                    <div class="flex items-center gap-2"><span class="material-symbols-outlined text-[14px] text-teal-600">check</span> เอกสาร / ไฟล์แนบ</div>
                                    <div class="flex items-center gap-2"><span class="material-symbols-outlined text-[14px] text-teal-600">check</span> ประวัติการแจ้งซ่อม</div>
                                </div>
                            </div>
                            <div class="hidden lg:block bg-gradient-to-br from-white/10 to-transparent p-1 rounded-xl backdrop-blur-sm border border-white/10">
                                <div class="bg-slate-800 rounded-[calc(0.75rem)] p-6 space-y-4">
                                    <div class="text-slate-400 text-sm text-center">สถานะของทรัพย์สิน</div>
                                    <div class="flex items-center gap-3 bg-white/5 rounded-lg p-3">
                                        <span class="material-symbols-outlined text-emerald-400">check_circle</span>
                                        <div><div class="font-bold text-[14px]">ใช้งานปกติ</div><div class="text-[12px] text-slate-400">ไม่มีใบแจ้งซ่อมค้างอยู่</div></div>
                                    </div>
                                    <div class="flex items-center gap-3 bg-white/5 rounded-lg p-3">
                                        <span class="material-symbols-outlined text-amber-400">build_circle</span>
                                        <div><div class="font-bold text-[14px]">กำลังซ่อม</div><div class="text-[12px] text-slate-400">มีใบแจ้งซ่อมที่ยังไม่จบ</div></div>
                                    </div>
                                    <div class="flex items-center gap-3 bg-white/5 rounded-lg p-3">
                                        <span class="material-symbols-outlined text-rose-400">inventory</span>
                                        <div><div class="font-bold text-[14px]">จำหน่ายแล้ว</div><div class="text-[12px] text-slate-400">ออกจากการใช้งานแล้ว</div></div>
                                    </div>
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
