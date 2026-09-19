@props([
    'heading' => 'PPK Hospital System',
    'sub' => 'Sign in to continue',
    'title' => null, // browser-tab page name (English); the suffix is added below
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title . ' • ' : '' }}{{ config('app.title_suffix') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
    class="font-sans antialiased h-full text-slate-800
             bg-[#0E2B51] bg-gradient-to-b from-[#0E2B51] to-[#123860]">
    <main class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <div
                class="rounded-lg border border-slate-200/80 bg-white/98 -[0_10px_30px_-10px_rgba(2,6,23,0.35)] overflow-hidden">
                <!-- Header -->
                <div class="px-8 pt-8 pb-5 text-center bg-white">
                    <div class="mx-auto w-18 h-18">
                        <div
                            class="mx-auto w-18 h-18 rounded-full ring-1 ring-slate-200/90 flex items-center justify-center bg-white">
                            <img src="{{ asset('images/logoppk.png') }}" alt="PPK Logo"
                                class="w-16 h-16 object-contain">
                        </div>
                    </div>

                    <h1 class="mt-3 text-[20px] font-semibold tracking-tight text-slate-800">
                        {{ $heading }}
                    </h1>
                    <p class="text-sm text-slate-600">{{ $sub }}</p>
                </div>

                <!-- Divider -->
                <div class="h-px bg-slate-100"></div>

                <!-- Content -->
                <div class="px-8 py-6">
                    <div class="space-y-4">
                        {{ $slot }}
                    </div>

                    @isset($actions)
                        <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2">
                            {{ $actions }}
                        </div>
                    @endisset
                </div>
            </div>

            <p class="mt-6 text-center text-[11px] tracking-wide text-slate-200/90">
                © {{ date('Y') }} PPK Hospital IT
            </p>
        </div>
    </main>

    {{-- Style helpers for consistent form look (optional to keep UI tidy) --}}
    <style>
        /* ปรับอินพุต/ปุ่มให้โทนเดียวกันแบบรวมศูนย์ */
        .form-input {
            @apply w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-[#0E2B51] focus:ring-2 focus:ring-[#0E2B51]/30;
        }

        .btn-primary {
            @apply w-full h-11 rounded-md bg-[#0E2B51] text-white font-medium hover:bg-[#0C2241] active:bg-[#0A1C36] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0E2B51] disabled:opacity-50 disabled:cursor-not-allowed;
        }

        .link {
            @apply text-[#0E2B51] hover:underline;
        }

        .label {
            @apply block text-sm font-medium text-slate-700;
        }
    </style>
</body>

</html>
