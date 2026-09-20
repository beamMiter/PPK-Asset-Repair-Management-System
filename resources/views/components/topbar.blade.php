@props([
    'logo' => asset('images/logoppk.png'),
    'bannerText' => null,
    'bannerAction' => null,
    'bannerLabel' => null,
    'showLogout' => Auth::check(),
])

@php
    $user = Auth::user();
    $breadcrumbs = \App\Support\Breadcrumb::generate();
@endphp

{{-- เติม flex-nowrap เพื่อบังคับไม่ให้ Topbar แตกเป็น 2 บรรทัด --}}
<nav class="navbar navbar-expand-lg navbar-pinwheel shadow-sm fixed-top flex-nowrap">
    {{-- Brand Block --}}
    <div class="nav-brand-block d-flex align-items-center justify-content-between px-3">
        <button type="button" onclick="openSide()"
            class="d-lg-none btn btn-no-bg text-white p-0 me-2 sidebar-hamburger d-flex align-items-center justify-content-center"
            style="font-size: 1.8rem; line-height: 1; min-width: 44px; min-height: 44px;" aria-label="เปิดเมนู">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div
            class="d-flex align-items-center gap-2 flex-grow-1 overflow-hidden justify-content-center justify-content-lg-start">
            <img id="sidebarLogo" src="{{ $logo }}" alt="Logo"
                class="brand-logo w-auto flex-shrink-0" />
            <div class="d-flex flex-column leading-tight">
                <span class="brand-en brand-title text-white">PHRAPOKKLAO</span>
                <span class="brand-sub text-slate-200 text-truncate">โรงพยาบาลพระปกเกล้า</span>
            </div>
        </div>

        {{-- Mobile Right Toggle --}}
        <div class="d-lg-none d-flex align-items-center gap-3 ms-auto" style="min-width: 44px;">
            @auth
                {{-- กระดิ่งแจ้งเตือน (Mobile) --}}
                @if ($user->role !== 'member')
                    <button type="button" id="notifyToggleBtnMobileTop"
                        class="btn btn-no-bg p-0 text-white position-relative d-flex align-items-center justify-content-center"
                        style="width: 32px; height: 32px;" title="เปิด/ปิดเสียงแจ้งเตือน">
                        <i id="notifyIconMobileTop" class="bi bi-bell" style="font-size: 1.3rem;"></i>
                        <span id="notifyStatusDotMobileTop" class="nav-dot d-none">
                            <span class="nav-dot__ping" style="background: rgba(239, 68, 68, 0.6);"></span>
                            <span class="nav-dot__core" style="border-color: var(--ppk-blue);"></span>
                        </span>
                    </button>
                @endif

                <button class="btn btn-link p-0 border-0 btn-no-bg" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#mobileOffcanvas">
                    <img src="{{ $user->avatar_url ?? asset('images/default-avatar.png') }}" class="avatar-img border-white"
                        style="width: 32px; height: 32px;" alt="Avatar">
                </button>
            @endauth
        </div>
    </div>

    {{-- Main Content Section (Desktop Only) --}}
    {{-- เติม min-width: 0 เพื่อป้องกัน Flex ทะลักกรอบ --}}
    <div class="container-fluid px-4 h-100 d-none d-lg-flex align-items-center nav-main" style="min-width: 0;">
        <div class="nav-left d-flex align-items-center gap-2 flex-shrink-0">
            <div class="brand-en nav-system-title">
                Asset Repair Management System
            </div>
        </div>

        {{-- Breadcrumbs & Banner --}}
        <div class="nav-center d-flex align-items-center flex-grow-1 px-4 overflow-hidden">
            @if (empty($bannerText) && count($breadcrumbs) > 0)
                <nav aria-label="breadcrumb" class="text-truncate">
                    <ol class="breadcrumb breadcrumb-custom m-0 ff-sarabun align-items-center flex-nowrap">
                        @foreach ($breadcrumbs as $item)
                            <li class="breadcrumb-item text-truncate {{ $item['active'] ? 'active' : '' }}">
                                @if ($item['url'] && !$item['active'])
                                    <a href="{{ $item['url'] }}" class="text-decoration-none nav-breadcrumb-link">
                                        {{ $item['label'] }}
                                    </a>
                                @else
                                    <span class="nav-breadcrumb-active">
                                        {{ $item['label'] }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            @if ($bannerText)
                <span class="nav-banner-text me-3 text-truncate">{{ $bannerText }}</span>
                @if ($bannerAction && $bannerLabel)
                    <a href="{{ $bannerAction }}" class="btn btn-sm nav-banner-btn flex-shrink-0">
                        {{ $bannerLabel }}
                    </a>
                @endif
            @endif
        </div>

        {{-- Right Section: Sound Toggle & Profile --}}
        {{-- เติม flex-shrink-0 เพื่อป้องกันไม่ให้ปุ่ม Profile โดนเบียด --}}
        <div class="nav-right ms-auto d-flex align-items-center gap-3 flex-shrink-0">
            @auth
                @if ($user->role !== 'member')
                    <button type="button" id="notifyToggleBtn"
                        class="nav-icon-btn d-flex align-items-center justify-content-center border-0"
                        title="เปิด/ปิดเสียงแจ้งเตือน" aria-label="แจ้งเตือน">
                        <i id="notifyIcon" class="bi bi-bell" aria-hidden="true" style="font-size: 1.2rem;"></i>
                        <span id="notifyStatusDot" class="nav-dot d-none">
                            <span class="nav-dot__ping"></span>
                            <span class="nav-dot__core"></span>
                        </span>
                    </button>
                @endif

            @endauth

            @guest
                <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm ff-sarabun px-3">
                    เข้าสู่ระบบ
                </a>
            @endguest
        </div>
    </div>
</nav>

{{-- Mobile Menu Offcanvas --}}
<div class="offcanvas offcanvas-end d-lg-none" tabindex="-1" id="mobileOffcanvas"
    aria-labelledby="mobileOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title ff-sarabun fw-bold" id="mobileOffcanvasLabel">เมนูการใช้งาน</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3">
        @auth
            <div class="mobile-profile-card">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <img src="{{ $user->avatar_url ?? asset('images/default-avatar.png') }}" width="45" height="45"
                        class="rounded-circle border" alt="Avatar">
                    <div class="ff-sarabun">
                        <div class="fw-bold" style="font-size: 1rem;">{{ $user->name }}</div>
                        <div class="text-muted small">{{ $user->email }}</div>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <a href="{{ route('profile.show') }}" class="btn btn-light btn-sm ff-sarabun text-start px-3">
                        <i class="bi bi-person me-2"></i> โปรไฟล์ของฉัน
                    </a>

                    {{-- Mobile Sound Toggle --}}
                    @if ($user->role !== 'member')
                        <button type="button" id="notifyToggleBtnMobile"
                            class="btn btn-light btn-sm ff-sarabun text-start px-3">
                            <i class="bi bi-bell me-2"></i> ตั้งค่าเสียงแจ้งเตือน
                        </button>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" data-turbo="false" class="mt-2">
                        @csrf
                        <button class="btn btn-danger btn-sm ff-sarabun w-100 py-2">
                            <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
                        </button>
                    </form>
                </div>
            </div>
        @endauth

        @guest
            <div class="d-grid gap-2">
                <a href="{{ route('login') }}" class="btn btn-primary btn-sm ff-sarabun py-2">เข้าสู่ระบบ</a>
            </div>
        @endguest
    </div>
</div>

<style>
    :root {
        --topbar-h: 80px;
        --ppk-blue: #0F2D5C;
        --ppk-blue-2: #133A73;
        --ppk-border: #e2e8f0;
        --ppk-text: #0f172a;
        --ppk-muted: #64748b;
        --ppk-soft: rgba(15, 45, 92, .08);
        --side-w: 260px;
    }

    .ff-sarabun {
        font-family: 'Sarabun', sans-serif !important;
    }

    .brand-en {
        font-family: 'Sarabun', sans-serif;
    }

    /* คลาสใหม่สำหรับเคลียร์ปุ่มให้โปร่งใส 100% ทุกสถานะ */
    .btn-no-bg,
    .btn-no-bg:hover,
    .btn-no-bg:focus,
    .btn-no-bg:active {
        background-color: transparent !important;
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
    }

    .navbar-pinwheel {
        height: var(--topbar-h);
        background: #ffffff;
        padding: 0;
        border-bottom: 1px solid var(--ppk-border);
        z-index: 1030;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02) !important;
        display: flex;
        flex-wrap: nowrap !important;
        /* ล็อคไม่ให้ร่วงเป็น 2 บรรทัด */
    }

    .nav-brand-block {
        width: var(--side-w);
        height: var(--topbar-h);
        background: var(--ppk-blue);
        flex: 0 0 var(--side-w);
        /* ฟิกซ์ความกว้างไม่ให้ยืดหดมั่วซั่ว */
        transition: all 0.3s ease;
    }

    .brand-logo {
        height: 48px;
        width: auto;
    }

    .brand-title {
        font-size: 16px;
        font-weight: 600;
        letter-spacing: 0.12em;
        line-height: 1.2;
    }

    .brand-sub {
        font-size: 12px;
        opacity: 0.9;
        color: #cbd5e1;
    }

    .nav-main {
        height: var(--topbar-h);
        flex: 1 1 auto;
    }

    .nav-system-title {
        font-size: .85rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--ppk-text);
        white-space: nowrap;
    }

    .breadcrumb-custom {
        --bs-breadcrumb-divider: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%2394a3b8'/%3E%3C/svg%3E");
        --bs-breadcrumb-item-padding-x: 0.6rem;
    }

    .nav-breadcrumb-link {
        color: var(--ppk-muted);
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .nav-breadcrumb-link:hover {
        color: var(--ppk-blue);
        text-decoration: none;
    }

    .nav-breadcrumb-active {
        color: var(--ppk-blue);
        font-size: 0.9rem;
        font-weight: 700;
    }

    .avatar-img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #f1f5f9;
    }

    .profile-dropdown-menu {
        min-width: 260px;
        border-radius: 12px;
        padding: 0.5rem;
        border: 1px solid rgba(15, 45, 92, .08);
    }

    .nav-icon-btn {
        position: relative;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: transparent !important;
        /* เปลี่ยนจากสีเทาเป็นโปร่งใส */
        color: var(--ppk-muted);
        transition: all 0.2s ease;
    }

    .nav-icon-btn:hover {
        background: transparent !important;
        color: var(--ppk-blue);
        transform: scale(1.1);
        /* ใช้การขยายแทนการแสดงพื้นหลังเวลานำเมาส์ไปชี้ */
    }

    /* Notification Dot */
    .nav-dot {
        position: absolute;
        right: 2px;
        top: 2px;
        width: 10px;
        height: 10px;
    }

    .nav-dot__core {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: #ef4444;
        border: 1px solid #ffffff;
    }

    .nav-dot__ping {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.4);
        animation: navPing 1.5s infinite;
    }

    @keyframes navPing {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        100% {
            transform: scale(2.5);
            opacity: 0;
        }
    }

    @media (max-width: 991.98px) {
        :root {
            --topbar-h: 70px;
        }

        .nav-brand-block {
            width: 100% !important;
            flex: 0 0 100%;
            border-right: none;
        }

        .nav-main {
            display: none !important;
        }

        .brand-logo {
            height: 38px;
        }

        .brand-title {
            font-size: 14px;
        }

        .brand-sub {
            font-size: 11px;
        }
    }
</style>

@auth
    <script>
        // Note: Notification toggle logic is now handled globally in resources/js/repair/my-jobs.js
        // to ensure sync between Desktop and Mobile views and persistence in localStorage.
    </script>
@endauth
