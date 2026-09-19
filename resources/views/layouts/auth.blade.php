{{-- resources/views/layouts/auth.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') • PPK Hospital System</title>
    <link rel="icon" type="image/png" href="{{ asset('icon/maintenance.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- ===== Auth Loader (drop-in, no-conflict) ===== -->
    <script>
        // ถ้าเข้าหน้านี้ด้วยการ Reload ให้โชว์ loader ตั้งแต่เฟรมแรก
        (function() {
            try {
                var nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || null;
                var isReload = nav ? nav.type === 'reload' : (performance.navigation && performance.navigation.type ===
                    1);
                if (isReload) document.documentElement.classList.add('authldr-start');
            } catch (_) {}
        })();
    </script>

    <style>
        /* ===== Overlay ทั้งหน้า (ไม่ชนของเดิม ใช้ prefix authldr-) ===== */
        .authldr-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .6);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99990;
            /* เว้นที่ให้ toast ของคุณถ้ามีใช้ z-index สูงกว่า */
            visibility: hidden;
            opacity: 0;
            transition: opacity .2s ease, visibility .2s;
        }

        .authldr-overlay.show {
            visibility: visible;
            opacity: 1;
        }

        .authldr-start #authldrOverlay {
            visibility: visible;
            opacity: 1;
        }

        .authldr-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #0E2B51;
            border-top-color: transparent;
            border-radius: 50%;
            animation: authldr-spin .8s linear infinite;
        }

        @keyframes authldr-spin {
            to {
                transform: rotate(360deg)
            }
        }

        /* ===== Mini spinner บนปุ่ม submit ===== */
        .authldr-btn[aria-busy="true"] {
            pointer-events: none;
            opacity: .92;
        }

        .authldr-mini {
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: authldr-spin .7s linear infinite;
            display: none;
            vertical-align: -2px;
        }

        .authldr-btn[aria-busy="true"] .authldr-mini {
            display: inline-block;
        }
    </style>
</head>

<body class="h-full bg-[#0E2B51] bg-gradient-to-b from-[#0E2B51] to-[#123860] text-slate-800">

    <main class="min-h-screen flex items-center justify-center p-5">
        <div class="w-full max-w-md">

            {{-- Card --}}
            <div
                class="rounded-lg border border-slate-200 -[0_10px_32px_-12px_rgba(2,6,23,0.45)] overflow-hidden">

                {{-- Header (ขาว) --}}
                <div class="px-8 pt-8 pb-5 text-center bg-white">
                    <div
                        class="mx-auto w-20 h-20 rounded-full bg-white ring-1 ring-slate-200 flex items-center justify-center mb-4">
                        <img src="{{ asset('images/logoppk.png') }}" class="w-16 h-16 object-contain" alt="PPK Logo">
                    </div>
                    <h1 class="text-[18px] font-semibold text-slate-800">@yield('title', 'Sign in')</h1>
                    <p class="text-xs text-slate-600 tracking-wide">Hospital Information Service</p>
                </div>

                {{-- Divider --}}
                <div class="h-px bg-slate-200"></div>

                {{-- Content (ขาว) --}}
                <div class="px-8 py-6 bg-white">
                    @yield('content')
                </div>
            </div>

            <p class="text-center text-[11px] text-slate-200 mt-6">
                &copy; {{ date('Y') }} PPK Hospital IT
            </p>
        </div>
    </main>

    <!-- Overlay -->
    <div id="authldrOverlay" class="authldr-overlay" aria-hidden="true">
        <div class="authldr-spinner"></div>
    </div>

    <!-- Logic: โชว์ Loader ตอน submit / enter / และตอนรีเฟรช -->
    <script>
        (function() {
            const overlay = document.getElementById('authldrOverlay');

            // ให้เรียกใช้ได้จากภายนอกถ้าต้องการ
            window.authLoader = {
                show: () => overlay?.classList.add('show'),
                hide: () => overlay?.classList.remove('show'),
            };

            // หา form หลักภายใน <main>
            const form = document.querySelector('main form');
            if (!form) return;

            // หา submit button (รองรับทั้ง button และ input[type=submit])
            let btn = form.querySelector('button[type="submit"], [type="submit"]');
            if (btn && btn.tagName === 'BUTTON' && !btn.getAttribute('type')) {
                btn.setAttribute('type', 'submit');
            }

            // ใส่ mini-spinner ลงบนปุ่ม โดยไม่แตะคลาสเดิมของคุณ
            if (btn) {
                btn.classList.add('authldr-btn');
                if (!btn.querySelector('.authldr-mini')) {
                    const spin = document.createElement('span');
                    spin.className = 'authldr-mini';
                    spin.setAttribute('aria-hidden', 'true');
                    btn.prepend(spin);
                    if (btn.textContent.trim().length) {
                        btn.insertBefore(document.createTextNode(' '), spin.nextSibling);
                    }
                }
            }

            function engage() {
                if (form.hasAttribute('data-no-loader')) return; // ฟอร์มไหนไม่อยากโชว์ ใส่แอตทริบิวต์นี้

                sessionStorage.setItem('ui.sidebarIntro.next', '1');
                if (btn) {
                    btn.setAttribute('aria-busy', 'true');
                    btn.disabled = true;
                }
                requestAnimationFrame(() => overlay.classList.add('show'));
            }

            // 1) คลิกปุ่ม
            btn?.addEventListener('click', () => {
                if (typeof form.checkValidity !== 'function' || form.checkValidity()) engage();
            });

            // 2) กด Enter ในฟอร์ม (ยกเว้น textarea)
            form.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    if (typeof form.checkValidity !== 'function' || form.checkValidity()) engage();
                }
            });

            // 3) จับ submit แบบ capture ครอบทุกเคส
            document.addEventListener('submit', (e) => {
                if (e.target === form) engage();
            }, true);

            // รีเฟรช/ออกหน้า: พยายามโชว์ (บางเบราว์เซอร์ไม่ repaint ก็ไม่เป็นไร)
            window.addEventListener('beforeunload', () => overlay.classList.add('show'));

            // ป้องกันค้างจาก reload-first-frame
            window.addEventListener('DOMContentLoaded', () => {
                document.documentElement.classList.remove('authldr-start');
            });

            // กลับมาจาก bfcache (เช่นกด Back) ให้รีเซ็ตปุ่ม/overlay
            window.addEventListener('pageshow', () => {
                if (btn) {
                    btn.removeAttribute('aria-busy');
                    btn.disabled = false;
                }
                overlay?.classList.remove('show');
            });
        })();
    </script>

    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js" defer></script>

    {{-- Carrier for the session toast, same as layouts/app. <x-toast /> only *consumes*
         session('toast') (it forgets it) — it never renders it — so without this a controller
         flash such as a failed login was silently dropped and the user saw nothing. It must sit
         before <x-toast />, which clears the session key. --}}
    @if (session('toast'))
        <script id="session-toast-data" type="application/json">
            @json(session('toast'))
        </script>
    @endif
    <x-toast />

    {{-- (ถ้าคุณมี <x-toast /> หรือสคริปต์อื่น ๆ อยู่เดิม ให้คงไว้ใต้ตรงนี้ได้เลย) --}}
</body>

</html>
