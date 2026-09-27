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
