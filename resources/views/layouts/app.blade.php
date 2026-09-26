<!doctype html>
<html lang="th" data-theme="govclean">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    {{-- Turbo prefetches a link's page when the pointer rests on it for 100 ms: a full render of a personal page for every link the mouse
         crosses (17 in the menu), thrown away 10 s later. Off. --}}
    <meta name="turbo-prefetch" content="false">
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
    <title>@hasSection('title')@yield('title') - @endif{{ config('app.title_suffix') }}</title>

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" />

    {{-- The two CDN libraries the layout needs live HERE, not at the end of <body>: Turbo replaces the <body> on every visit and re-creates
         the scripts in it, so they were fetched and run again on each page (and Bootstrap stacked its document listeners again). Scripts
         in <head> are kept by Turbo and added only when a page brings one the document does not have yet. `defer`: run in document
         order once the page is parsed — before the modules below and before DOMContentLoaded — without blocking the first paint. --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js" defer></script>

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
    </style>

    {{-- the rest of the layout's CSS, then the top bar's and the sidebar's (the order their inline styles had in <body>); loaded
         here, after the page stacks, so the cascade order is what it was --}}
    @vite(['resources/css/layout.css', 'resources/css/topbar.css', 'resources/css/sidebar.css'])
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
            <audio id="notifySound" preload="none" src="{{ Auth::user()->notificationSoundUrl() }}"></audio>
        @endif
    @endauth

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
