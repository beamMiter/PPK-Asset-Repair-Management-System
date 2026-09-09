@php
    use Illuminate\Support\Facades\Route;

    // Helper สำหรับเช็ค Active Route
    $is = fn(...$p) => request()->routeIs($p);

    // Helper สำหรับสร้าง URL (กัน Error ถ้าไม่มีชื่อ Route)
    $rl = fn(string $name, string $fallback = '#') => Route::has($name) ? route($name) : $fallback;

    // Base Classes สำหรับความสวยงาม
    $itemBase = 'menu-item group relative';
    $linkBase = 'flex items-center h-12 px-6 gap-4 text-base font-medium transition-all duration-200 ease-in-out';
    $off = 'text-zinc-600 hover:bg-slate-50 hover:text-[#0F2D5C]';
    $on = 'bg-slate-100/80 text-[#0F2D5C] font-semibold';
    $textBase = 'menu-text truncate py-1 whitespace-nowrap flex-1';

    // เส้นสีน้ำเงินด้านข้างเมื่อ Active
    $strip = fn(
        bool $active,
    ) => 'absolute left-0 top-1/2 -translate-y-1/2 w-[4px] h-8 rounded-r bg-[#0F2D5C] transition-all duration-300 ease-out origin-left ' .
        ($active ? 'opacity-100 scale-y-100' : 'opacity-0 scale-y-50');
@endphp

<style>
    /* ซ่อน Scrollbar แต่ยังเลื่อนได้ */
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }

    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    /* เอฟเฟกต์ตอนกดปุ่มในมือถือ */
    .btn-close-trigger:active {
        transform: scale(0.9);
        transition: 0.1s;
    }

    /* Mobile: จัดการให้เต็มจอ ทับ Navbar และไม่กระทบส่วนอื่น */
    @media (max-width: 1024px) {
        .mobile-sidebar-container {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            height: 100dvh !important;
            width: 280px !important;
            /* กำหนดความกว้างตายตัวให้ออกมาสวยงาม */
            max-width: 85vw !important;
            z-index: 9999 !important;
            /* ทับทุกสิ่งบนจอ */
            background-color: white !important;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.08) !important;
            /* ใส่เงาเบาๆ แทนการทำพื้นหลังจาง */
        }

        .sidebar .menu-item,
        .mobile-sidebar-container .menu-item {
            min-height: 48px;
        }

        .mobile-sidebar-container .sidebar-nav {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
    }
</style>

{{-- ใช้คลาสเฉพาะเจาะจง mobile-sidebar-container เพื่อไม่ให้กระทบส่วนอื่น --}}
<div class="mobile-sidebar-container flex flex-col h-full bg-white border-r border-zinc-100 relative">

    {{-- Brand Block (At the top of the sidebar) --}}
    <div
        class="sidebar-brand-block h-[80px] bg-[#0F2D5C] flex items-center px-4 flex-shrink-0 transition-all duration-300 relative">
        <img id="desktopSidebarLogo" src="{{ asset('images/logoppk.png') }}" alt="Logo"
            class="brand-logo w-auto flex-shrink-0" />
        <div class="sidebar-brand-text flex flex-col leading-tight ml-3 overflow-hidden transition-opacity duration-200">
            <span class="brand-en font-bold text-white tracking-wider text-[15px]">PHRAPOKKLAO</span>
            <span class="text-slate-200 text-[11px] truncate">โรงพยาบาลพระปกเกล้า</span>
        </div>
    </div>

    {{-- Triangle Toggle Button (Desktop Only) --}}
    <button type="button" onclick="toggleSidebarCollapse()"
        class="hidden lg:flex absolute -right-3.5 top-[40px] -translate-y-1/2 w-7 h-7 bg-white border border-zinc-200 rounded-full items-center justify-center text-zinc-500 hover:text-[#0F2D5C] hover:border-[#0F2D5C] transition-all duration-200 z-[1041]"
        aria-label="Toggle Sidebar">
        <svg id="sidebarToggleIcon" class="w-3.5 h-3.5 transition-transform duration-300" fill="none"
            viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    {{-- ปุ่มกากบาท (X) และ Header สำหรับ Mobile เท่านั้น --}}
    <div class="lg:hidden flex items-center justify-between px-6 py-4 border-b border-zinc-100 flex-shrink-0 bg-white"
        style="padding-top: max(1rem, env(safe-area-inset-top));">
        <div class="font-bold text-[#0F2D5C] tracking-wider text-sm flex items-center gap-2">
            เมนูหลัก
        </div>
        <button type="button" onclick="closeSide()"
            class="btn-close-trigger w-10 h-10 min-w-[40px] min-h-[40px] flex items-center justify-center rounded-full bg-slate-50 text-zinc-500 hover:bg-red-50 hover:text-red-500 active:scale-95 transition-all"
            aria-label="ปิดเมนู">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    {{-- Navigation Section --}}
    <nav class="sidebar-nav flex-1 py-4 overflow-y-auto overscroll-contain no-scrollbar">

        <div
            class="sidebar-heading px-6 mb-2 text-[11px] font-bold uppercase tracking-[0.15em] text-zinc-400/80 transition-all duration-300">
            <span class="heading-char">M</span><span class="heading-text">enu (เมนู)</span>
        </div>

        @php $active = $is('repair.dashboard'); @endphp
        <a href="{{ $rl('repair.dashboard') }}"
            class="{{ $itemBase }} {{ $linkBase }} {{ $active ? $on : $off }}">
            <span class="{{ $strip($active) }}"></span>
            <span class="icon-wrap flex-shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 3v18h18" />
                    <rect x="7" y="10" width="3" height="7" rx="1" />
                    <rect x="12" y="6" width="3" height="11" rx="1" />
                    <rect x="17" y="13" width="3" height="4" rx="1" />
                </svg>
            </span>
            <span class="{{ $textBase }}">Dashboard</span>
        </a>

        @can('maintenance-type-manage')
            @php $active = $is('maintenance.sla.*'); @endphp
            <a href="{{ $rl('maintenance.sla.index') }}"
                class="{{ $itemBase }} {{ $linkBase }} {{ $active ? $on : $off }}">
                <span class="{{ $strip($active) }}"></span>
                <span class="icon-wrap flex-shrink-0">
                    <img src="/icon/sla1.webp" class="w-6 h-6 object-contain" alt="SLA">
                </span>
                <span class="{{ $textBase }}">SLA Dashboard</span>
            </a>
        @endcan

        @can('maintenance-type-manage')
            @php $active = $is('maintenance.requests.rating.technicians'); @endphp
            <a href="{{ $rl('maintenance.requests.rating.technicians') }}"
                class="{{ $itemBase }} {{ $linkBase }} {{ $active ? $on : $off }}">
                <span class="{{ $strip($active) }}"></span>
                <span class="icon-wrap flex-shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 21h18" />
                        <rect x="5" y="10" width="3" height="7" rx="1" />
                        <rect x="10.5" y="7" width="3" height="10" rx="1" />
                        <rect x="16" y="4" width="3" height="13" rx="1" />
                    </svg>
                </span>
                <span class="{{ $textBase }}">Technician Rating</span>
            </a>
        @endcan



        @php
            // เฉพาะหน้าหลักของการแจ้งซ่อม (index, show, edit, create) ไม่รวมถึงส่วน Rating/Dashboard อื่นๆ
            $active = $is('maintenance.requests.*') && !$is('maintenance.requests.rating.*');
        @endphp
        <a href="{{ $rl('maintenance.requests.index') }}"
            class="{{ $itemBase }} {{ $linkBase }} {{ $active ? $on : $off }}">
            <span class="{{ $strip($active) }}"></span>
            <span class="icon-wrap flex-shrink-0">
                <img src="/icon/maintenance.webp" class="w-6 h-6 object-contain" alt="Maintenance">
            </span>
            <span class="{{ $textBase }}">แจ้งซ่อมบำรุง</span>
        </a>

        @can('view-my-jobs')
            @php $active = $is('repairs.my_jobs'); @endphp
            <a href="{{ $rl('repairs.my_jobs') }}"
                class="{{ $itemBase }} {{ $linkBase }} {{ $active ? $on : $off }}">
                <span class="{{ $strip($active) }}"></span>
                <span class="icon-wrap flex-shrink-0">
                    <img src="/icon/specifications.webp" class="w-6 h-6 object-contain" alt="Jobs">
                </span>
                <span class="{{ $textBase }}">รายการงานซ่อม</span>
            </a>
        @endcan

        @php
            $active = $is('assets.*');
        @endphp
        <a href="{{ $rl('assets.index') }}"
            class="{{ $itemBase }} {{ $linkBase }} {{ $active ? $on : $off }}">
            <span class="{{ $strip($active) }}"></span>
            <span class="icon-wrap flex-shrink-0">
                <img src="/icon/toolbox.webp" class="w-6 h-6 object-contain" alt="Assets">
            </span>
            <span class="{{ $textBase }}">ทะเบียนทรัพย์สิน</span>
        </a>

        @php $active = $is('chat.*'); @endphp
        <a href="{{ $rl('chat.index') }}"
            class="{{ $itemBase }} {{ $linkBase }} {{ $active ? $on : $off }}">
            <span class="{{ $strip($active) }}"></span>
            <span class="icon-wrap flex-shrink-0">
                <img src="/icon/topic.webp" class="w-6 h-6 object-contain" alt="Chat">
            </span>
            <span class="{{ $textBase }}">Livechat</span>
        </a>

        {{-- Section: Management (Settings & Admin) --}}
        @if (auth()->user()->can('maintenance-type-manage') || auth()->user()->can('manage-users'))
            @php
                $isMgmtActive = $is('settings.maintenance-types.*', 'settings.notifications.*', 'admin.users.*');
            @endphp
            <div x-data="{ open: {{ $isMgmtActive ? 'true' : 'false' }} }" class="relative">
                <button @click="open = !open" type="button"
                    class="{{ $itemBase }} {{ $linkBase }} w-full text-left {{ $isMgmtActive ? $on : $off }}">
                    <span class="{{ $strip($isMgmtActive) }}"></span>
                    <span class="icon-wrap flex-shrink-0">
                        <span class="material-symbols-outlined text-[20px] text-zinc-500">settings_suggest</span>
                    </span>
                    <span class="menu-text truncate py-1 whitespace-nowrap flex-1 flex items-center justify-between">
                        <span>การจัดการระบบ</span>
                        <svg :class="{ 'rotate-180': open }"
                            class="w-4 h-4 text-zinc-400 transition-transform duration-200" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </span>
                </button>
                <div x-show="open" style="{{ $isMgmtActive ? '' : 'display: none;' }}"
                    x-transition:enter="transition-all ease-out duration-200"
                    x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-60"
                    x-transition:leave="transition-all ease-in duration-200"
                    x-transition:leave-start="opacity-100 max-h-60" x-transition:leave-end="opacity-0 max-h-0"
                    class="overflow-hidden bg-slate-50/50 block">
                    <div class="py-2 space-y-1">
                        @can('maintenance-type-manage')
                            @php $typesActive = $is('settings.maintenance-types.*'); @endphp
                            <a href="{{ $rl('settings.maintenance-types.index') }}"
                                class="flex items-center gap-3 h-10 px-6 pl-[4rem] text-[13.5px] font-medium transition-colors hover:bg-slate-100 hover:text-[#0F2D5C] {{ $typesActive ? 'text-[#0F2D5C] font-semibold bg-white ' : 'text-zinc-500' }}">
                                <span class="material-symbols-outlined text-[18px]">build_circle</span>
                                <span>ประเภทใบแจ้งซ่อม</span>
                            </a>

                            @php $notifsActive = $is('settings.notifications.*'); @endphp
                            <a href="{{ $rl('settings.notifications.index') }}"
                                class="flex items-center gap-3 h-10 px-6 pl-[4rem] text-[13.5px] font-medium transition-colors hover:bg-slate-100 hover:text-[#0F2D5C] {{ $notifsActive ? 'text-[#0F2D5C] font-semibold bg-white ' : 'text-zinc-500' }}">
                                <span class="material-symbols-outlined text-[18px]">notifications_active</span>
                                <span>การแจ้งเตือน</span>
                            </a>
                        @endcan

                        @can('manage-users')
                            @php $usersActive = $is('admin.users.*'); @endphp
                            <a href="{{ $rl('admin.users.index') }}"
                                class="flex items-center gap-3 h-10 px-6 pl-[4rem] text-[13.5px] font-medium transition-colors hover:bg-slate-100 hover:text-[#0F2D5C] {{ $usersActive ? 'text-[#0F2D5C] font-semibold bg-white ' : 'text-zinc-500' }}">
                                <span class="material-symbols-outlined text-[18px]">manage_accounts</span>
                                <span>ผู้ใช้งานระบบ</span>
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        @endif

        <div
            class="sidebar-heading px-6 mt-6 mb-2 text-[11px] font-bold uppercase tracking-[0.15em] text-zinc-400/80 transition-all duration-300">
            <span class="heading-char">H</span><span class="heading-text">elp & Support (ช่วยเหลือ)</span>
        </div>

        @php $active = $is('maintenance.requests.rating.evaluate'); @endphp
        <a href="{{ $rl('maintenance.requests.rating.evaluate') }}"
            class="{{ $itemBase }} {{ $linkBase }} {{ $active ? $on : $off }}">
            <span class="{{ $strip($active) }}"></span>
            <span class="icon-wrap flex-shrink-0">
                <img src="/icon/feedback.webp" class="w-6 h-6 object-contain" alt="Feedback">
            </span>
            <span class="{{ $textBase }}">ประเมินความพึงพอใจ</span>
        </a>

        @php $active = $is('help.manual'); @endphp
        <a href="{{ route('help.manual') }}"
            class="{{ $itemBase }} {{ $linkBase }} {{ $active ? $on : $off }}">
            <span class="{{ $strip($active) }}"></span>
            <span class="icon-wrap flex-shrink-0">
                <svg class="w-5 h-5 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                    <path d="M12 17h.01" />
                </svg>
            </span>
            <span class="{{ $textBase }}">คู่มือการใช้งาน</span>
        </a>





        {{-- Administration has been moved to the Management dropdown --}}

    </nav>

    {{-- Section: Account & Help (Fix to bottom) --}}
    @auth
        <div class="mt-auto border-t border-zinc-100 p-4 bg-slate-50/50 flex-shrink-0">
            {{-- Profile Card in Sidebar --}}
            <div class="flex items-center gap-3 mb-3 pb-3 border-b border-zinc-200 overflow-hidden">
                <img src="{{ Auth::user()->avatar_url ?? asset('images/default-avatar.png') }}" alt="Avatar"
                    class="w-10 h-10 rounded-full object-cover border border-white flex-shrink-0 bg-white">
                <div class="profile-info flex-1 min-w-0 transition-all duration-300">
                    <div class="text-[13px] font-bold text-slate-900 truncate">{{ Auth::user()->name }}</div>
                    <div class="text-[11px] text-slate-500 truncate">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="profile-actions flex flex-col gap-1 transition-all duration-300">
                <a href="{{ $rl('profile.show') }}"
                    class="flex items-center gap-2 px-3 py-2 rounded-md text-[13px] font-medium text-zinc-500 hover:text-zinc-900 transition-colors">
                    <svg class="w-4 h-4 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span class="profile-label transition-colors">โปรไฟล์ของฉัน</span>
                </a>



                <form method="POST" action="{{ route('logout') }}" data-turbo="false" class="mt-1">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-[13px] font-medium text-zinc-500 hover:text-zinc-900 transition-colors">
                        <svg class="w-4 h-4 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24"
                            stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="profile-label text-left transition-colors">ออกจากระบบ</span>
                    </button>
                </form>
            </div>
        </div>
    @endauth
</div>
