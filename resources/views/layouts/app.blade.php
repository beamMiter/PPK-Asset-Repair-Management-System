<!doctype html>
<html lang="th" data-theme="govclean">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#0E2B51">

    <link rel="icon" type="image/png" href="{{ asset('icon/maintenance.png') }}">

    <script>
        (function() {
            try {
                if (sessionStorage.getItem('ui.sidebarIntro.next') === '1') {
                    document.documentElement.classList.add('intro-pending');
                }
            } catch (e) {}
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
    <title>@hasSection('title')@yield('title') • @endif{{ config('app.title_suffix') }}</title>

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" />

    @yield('head')

    <script>
        window.__playSidebarIntro = @json(session('play_sidebar_intro', false));
    </script>

    @vite(['resources/css/app.css', 'resources/css/toast.css', 'resources/js/app.js', 'resources/js/layout/boot.js'])

    @stack('styles')
    @stack('head')

    <style>
        /* --- FONT FACE: SARABUN (files live in public/images/fonts) --- */
        @font-face {
            font-family: 'Sarabun';
            font-style: normal;
            font-weight: 400;
            src: url('{{ asset('images/fonts/Sarabun-Regular.woff2') }}') format('woff2'),
                url('{{ asset('images/fonts/Sarabun-Regular.woff') }}') format('woff');
        }

        @font-face {
            font-family: 'Sarabun';
            font-style: normal;
            font-weight: 500;
            src: url('{{ asset('images/fonts/Sarabun-Medium.woff2') }}') format('woff2'),
                url('{{ asset('images/fonts/Sarabun-Medium.woff') }}') format('woff');
        }

        @font-face {
            font-family: 'Sarabun';
            font-style: normal;
            font-weight: 600;
            src: url('{{ asset('images/fonts/Sarabun-SemiBold.woff2') }}') format('woff2');
        }

        @font-face {
            font-family: 'Sarabun';
            font-style: normal;
            font-weight: 700;
            src: url('{{ asset('images/fonts/Sarabun-Bold.woff2') }}') format('woff2'),
                url('{{ asset('images/fonts/Sarabun-Bold.woff') }}') format('woff');
        }

        /* --- GLOBAL BASE --- */
        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Sarabun', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-weight: 400;
            letter-spacing: 0.2px;
        }

        body {
            min-height: 100vh;
            padding-top: 0 !important;
        }

        /* --- DAISYUI TEXTAREA OVERRIDE ---
           DaisyUI sets `min-height: 5rem` on all textarea elements.
           This prevents our rows="2" and auto-expand JS from working.
           We reset this globally so textarea heights are controlled
           only by the `rows` attribute and JS auto-expand logic.
        */
        textarea {
            min-height: unset !important;
        }

        :root {
            color-scheme: light;
            --topbar-h: 80px;
            --side-w: 260px;
            --side-w-compact: 180px;
            --side-w-collapsed: 86px;
        }

        @media (max-width: 992px) {
            :root {
                --topbar-h: 72px;
            }
        }

        /* --- LAYOUT & MAIN CONTENT --- */
        .layout {
            min-height: 0 !important;
            background: #ffffff;
            color: hsl(var(--bc));
            position: relative;
        }

        .content {
            padding: calc(var(--topbar-h) + 1rem) 1rem 0.25rem;
        }

        .sticky-under-topbar {
            position: sticky;
            top: var(--topbar-h);
            z-index: 10;
        }

        .sticky-under-topbar>*:first-child {
            margin-top: 0 !important;
        }

        #main .sticky-under-topbar+* {
            margin-top: 6rem;
        }

        @media (min-width: 1024px) {
            #main {
                margin-left: var(--side-w);
            }

            body.with-compact #main {
                margin-left: var(--side-w-compact);
            }

            body.with-collapsed #main {
                margin-left: var(--side-w-collapsed);
            }

            body.with-expanded #main {
                margin-left: var(--side-w);
            }
        }

        /* --- SIDEBAR CORE --- */
        .sidebar {
            background: #ffffff;
            border-right: 1px solid rgba(15, 45, 92, 0.12);
            width: var(--side-w);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1040;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s ease;
        }

        .sidebar-scroll {
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* --- SIDEBAR STATES (DESKTOP) --- */
        @media (min-width: 1024px) {
            .nav-brand-block {
                display: none !important;
            }

            .navbar-pinwheel {
                left: var(--side-w) !important;
                width: calc(100% - var(--side-w)) !important;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            body.with-collapsed .navbar-pinwheel {
                left: var(--side-w-collapsed) !important;
                width: calc(100% - var(--side-w-collapsed)) !important;
            }

            .sidebar.compact {
                width: var(--side-w-compact) !important;
            }

            .sidebar.collapsed {
                width: var(--side-w-collapsed) !important;
            }

            .sidebar.collapsed .sidebar-brand-text {
                display: none !important;
            }

            .sidebar.collapsed .sidebar-brand-block {
                padding-inline: 0 !important;
                justify-content: center !important;
            }

            /* Logo and Toggle Animation Styles */
            .sidebar.collapsed #sidebarToggleIcon {
                transform: rotate(180deg);
            }

            .sidebar.collapsed .brand-logo {
                transform: scale(0.85);
            }

            /* Profile Card Collapsed States */
            .sidebar.collapsed .profile-info,
            .sidebar.collapsed .profile-label {
                display: none !important;
            }

            /* Sidebar Headings Collapsed State (Shows only first letter) */
            .sidebar.collapsed .sidebar-heading {
                text-align: center !important;
                padding-inline: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                margin-bottom: 0.75rem !important;
            }

            .sidebar.collapsed .sidebar-heading .heading-text {
                display: none !important;
            }

            .sidebar.collapsed .sidebar-heading .heading-char {
                display: inline-block !important;
                font-size: 12px !important;
                font-weight: 800 !important;
                color: #0F2D5C !important;
            }


            .sidebar.collapsed .profile-actions a,
            .sidebar.collapsed .profile-actions button {
                justify-content: center !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                width: 44px;
                margin: 0 auto;
            }

            /* Fix Avatar Squeeze on Collapse */
            .sidebar.collapsed .mt-auto.p-4 {
                padding-inline: 0.5rem !important;
            }

            .sidebar.collapsed .flex.items-center.gap-3.mb-3 {
                justify-content: center !important;
                gap: 0 !important;
                padding-bottom: 0.75rem !important;
            }

            .sidebar.collapsed .profile-actions a span,
            .sidebar.collapsed .profile-actions button span {
                display: none !important;
            }

        }

        /* --- SIDEBAR MOBILE --- */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                width: min(270px, 85vw);
                max-width: 85vw;
                transform: translateX(-100%);
                transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 1050;
                box-shadow: 4px 0 24px rgba(0, 0, 0, .12);
                max-height: 100vh;
                overflow: hidden;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-backdrop {
                background: rgba(0, 0, 0, 0.45);
                backdrop-filter: blur(4px);
                -webkit-backdrop-filter: blur(4px);
            }

            .sidebar-backdrop.show {
                display: block !important;
            }
        }

        /* --- MENU ITEMS --- */
        .sidebar .menu {
            padding: .5rem 0;
        }

        .sidebar .menu-item {
            display: grid;
            grid-template-columns: 48px 1fr;
            align-items: center;
            gap: .75rem;
            height: 44px;
            line-height: 1;
            padding: 0 .75rem;
            white-space: nowrap;
            overflow: hidden;
            transition: grid-template-columns .25s ease, padding .25s ease, background .15s ease;
            color: hsl(var(--bc));
        }

        .sidebar .menu-item:hover {
            background: hsl(var(--b2));
        }

        .sidebar .menu-item .icon-wrap {
            width: 48px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: color-mix(in srgb, hsl(var(--bc)) 60%, transparent);
            position: relative;
        }

        .sidebar .menu-item .menu-text {
            overflow: hidden;
            text-overflow: ellipsis;
            opacity: 1;
            transition: opacity .18s ease;
        }

        /* --- COLLAPSED & COMPACT MENU OVERRIDES --- */
        @media (min-width: 1024px) {
            .sidebar.collapsed .menu-item {
                display: flex !important;
                justify-content: center !important;
                padding-inline: 0 !important;
                gap: 0;
            }

            .sidebar.collapsed .menu-item .menu-text {
                display: none !important;
            }

            .sidebar.compact .menu-item {
                grid-template-columns: 48px 1fr;
                padding-inline: .5rem;
            }

            .sidebar.compact .menu-item .menu-text {
                font-size: .92rem;
            }
        }

        /* --- UTILITIES & COMPONENTS --- */
        .brand-en {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            letter-spacing: 0.08em;
        }




        /* --- NAVBAR & DROPBOWN --- */
        .app-navbar,
        .navbar-hero {
            z-index: 2000;
        }

        .dropdown-menu {
            z-index: 2100;
        }

        /* --- TAB NUDGE ANIMATION --- */
        @keyframes tabNudge {

            0%,
            100% {
                transform: translateX(0);
            }

            50% {
                transform: translateX(-2px);
            }
        }

        #teamTab {
            cursor: pointer;
            right: .8rem;
        }

        #teamTab .tri {
            transition: transform .18s ease, border-color .18s ease;
        }

        #teamTab:hover .tri {
            transform: translateX(-1px);
        }

        #teamTab .tab-nudge {
            animation: tabNudge 1.8s ease-in-out infinite;
        }

        /* --- TOMSELECT CUSTOMIZATION --- */
        .ts-wrapper,
        .ts-wrapper.single {
            position: relative !important;
            width: 100% !important;
        }

        .ts-wrapper.single .ts-control {
            height: 44px !important;
            min-height: 44px !important;
            border-radius: 0.375rem !important;
            border: 1px solid #cbd5e1 !important;
            background: #fff !important;
            box-shadow: none !important;
            padding-left: 2.5rem !important;
            padding-right: 2.25rem !important;
            font-size: .875rem !important;
            line-height: 1.25rem !important;
            display: flex !important;
            align-items: center !important;
        }

        .ts-wrapper.single .ts-control input,
        .ts-wrapper.single .ts-control .item {
            font-size: .875rem !important;
            line-height: 1.25rem !important;
        }

        /* The control is a fixed 44px tall, so the selected item and the search input must stay on ONE line.
           TomSelect's own CSS is flex-wrap:wrap with input min-width:7rem — in a narrow spot (the type select in a
           My Jobs card) the input dropped onto a second line and the field looked like it jumped. A long value now
           truncates with "…" instead. */
        .ts-wrapper.single .ts-control {
            flex-wrap: nowrap !important;
        }

        .ts-wrapper.single .ts-control > .item {
            flex: 0 1 auto !important;
            min-width: 0 !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }

        .ts-wrapper.single .ts-control > input {
            flex: 1 1 2rem !important;
            min-width: 2rem !important;
        }

        .ts-wrapper.single .ts-control:focus-within {
            border-color: #059669 !important;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, .20) !important;
        }

        .ts-wrapper.single::before {
            content: "" !important;
            position: absolute !important;
            left: .75rem !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: 16px !important;
            height: 16px !important;
            pointer-events: none !important;
            z-index: 50 !important;
            opacity: .85 !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            background-size: 16px 16px !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.3-4.3'/%3E%3C/svg%3E") !important;
        }

        .ts-wrapper.single .ts-control::after {
            display: none !important;
        }

        .ts-dropdown {
            border-radius: 0.5rem !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .08) !important;
            z-index: 4000 !important;
        }

        .ts-dropdown .option {
            padding: .5rem .75rem !important;
            font-size: .875rem !important;
        }

        .ts-dropdown .option.active {
            background: #ecfdf5 !important;
            color: #047857 !important;
        }

        /* --- PAGE SPECIFIC & SPACING --- */
        .content.tight-0 {
            padding-top: var(--topbar-h) !important;
        }

        #main .sticky-under-topbar.no-gap+* {
            margin-top: 0rem !important;
        }

        #main .sticky-under-topbar.tight-gap+* {
            margin-top: 2rem !important;
        }

        #main .sticky-under-topbar+* {
            margin-top: 0 !important;
        }

        .layout .content {
            /* padding-top is already handled in .content above */
        }

        .page-create-asset .content {
            padding-top: 0 !important;
            margin-top: 0 !important;
            position: relative;
            z-index: 1;
        }

        /* --- CHAT PAGE --- */

        .chat-page #main {
            padding-top: var(--app-top);
        }

        .chat-page .content {
            padding-top: calc(var(--app-top) + 1rem);
        }

        .chat-page .sticky-under-topbar {
            top: var(--app-top);
        }

        .chat-page .sticky-under-topbar+* {
            margin-top: 0 !important;
        }

        /* --- DIRTY FIELD HIGHLIGHT --- */
        .is-dirty-field {
            border-color: #eab308 !important;
            /* yellow-500 */
        }

        .is-dirty-field:focus {
            --tw-ring-color: #fef08a !important;
            /* yellow-200 */
            border-color: #eab308 !important;
        }

        .ts-wrapper.is-dirty-field .ts-control {
            border-color: #eab308 !important;
        }
    </style>
</head>

<body class="bg-white text-base-content">
    @if (View::hasSection('topbar'))
        @yield('topbar')
    @else
        <x-topbar :appName="config('app.name', 'Phrapokklao - Information Technology Group')" subtitle="Asset Repair Management" logo="{{ asset('/images/logoppk.png') }}"
            :showLogout="Auth::check()" />
    @endif

    <div id="layout" class="layout">
        <aside id="side" class="sidebar">
            @hasSection('sidebar')
                @yield('sidebar')
            @else
                <x-sidebar />
            @endif
        </aside>

        <div id="backdrop"
            class="sidebar-backdrop fixed inset-0 z-[1040] hidden lg:hidden transition-opacity duration-200 ease-out"
            onclick="closeSide()" aria-hidden="true"></div>

        <main id="main" class="content @yield('main-class')">
            @hasSection('page-header')
                <div class="sticky-under-topbar @yield('header-wrap-class')">@yield('page-header')</div>
            @endif


            @yield('content')


            {{-- Carrier for Turbo to detect session toasts --}}
            @if (session('toast'))
                <script id="session-toast-data" type="application/json">
                    @json(session('toast'))
                </script>
            @endif

            @yield('after-content')

        </main>
    </div>



    @auth
        @if (Auth::user()->role !== 'member')
            {{-- the sound this user picked on the notification-sound page (falls back to the default) --}}
            <audio id="notifySound" preload="auto" src="{{ Auth::user()->notificationSoundUrl() }}"></audio>
        @endif
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    @yield('scripts')
    @stack('scripts')

    <div id="loaderOverlay"
        class="fixed inset-0 z-[99999] flex items-center justify-center bg-white/60 backdrop-blur-[2px] invisible opacity-0 transition-all duration-200 [&.show]:visible [&.show]:opacity-100"
        aria-hidden="true">
        <div class="w-[38px] h-[38px] border-4 border-[#0E2B51] border-t-transparent rounded-full animate-spin"
            role="status" aria-label="กำลังโหลด"></div>
    </div>

    <x-toast />

    @includeWhen(Auth::check(), 'partials.chat-fab')
    <x-confirm-dialog />
</body>

</html>
