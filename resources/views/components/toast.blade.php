@php
    $toast = session('toast');
    if ($toast) {
        session()->forget('toast');
    }

    $type = $toast['type'] ?? null;
    $message = $toast['message'] ?? null;
    $position = $toast['position'] ?? 'tr';
    $timeout = (int) ($toast['timeout'] ?? 3800);
    $size = $toast['size'] ?? 'lg';

    $firstError = isset($errors) && method_exists($errors, 'first') && $errors->any() ? $errors->first() : null;
    if (!$message && $firstError) {
        $message = $firstError;
        $type = $type ?: 'warning';
    }
    if (!$message && session('error')) {
        $message = session('error');
        $type = $type ?: 'error';
    }
    if (!$message && session('status')) {
        $message = session('status');
        $type = $type ?: 'success';
    }
@endphp

<style>
    :root {
        --toast-z: 100001;
        --toast-gap: 10px;
        --toast-max-w: min(92vw, 420px);
        --toast-min-w: 340px;
        --toast-radius: 14px;
    }

    .toast-overlay {
        position: fixed;
        inset: 0;
        z-index: var(--toast-z);
        pointer-events: none;
    }

    .toast-pos {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        gap: var(--toast-gap);
        padding: 16px;
    }

    .toast-pos.tr {
        align-items: flex-end;
        justify-content: flex-start;
        padding-top: calc(var(--topbar-h, 64px) + 16px);
    }

    .toast-pos.tl {
        align-items: flex-start;
        justify-content: flex-start;
        padding-top: calc(var(--topbar-h, 64px) + 16px);
    }

    .toast-pos.br {
        align-items: flex-end;
        justify-content: flex-end;
    }

    .toast-pos.bl {
        align-items: flex-start;
        justify-content: flex-end;
    }

    /* ── Card ── */
    .toast-card {
        pointer-events: auto;
        width: min(100%, var(--toast-max-w));
        min-width: var(--toast-min-w);
        border-radius: var(--toast-radius);
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 28px rgba(0, 0, 0, .20), 0 2px 8px rgba(0, 0, 0, .10);

        opacity: 0;
        transform: translateX(20px) scale(.98);
        transition:
            opacity .25s cubic-bezier(.16, 1, .3, 1),
            transform .25s cubic-bezier(.16, 1, .3, 1);
    }

    .toast-card.show {
        opacity: 1;
        transform: translateX(0) scale(1);
    }

    .toast-card.hide {
        opacity: 0;
        transform: translateX(20px) scale(.97);
        transition: opacity .18s ease, transform .18s ease;
    }

    /* ── Background colors ── */
    .toast--success {
        background: #4CAF50;
    }

    .toast--error {
        background: #F44336;
    }

    .toast--warning {
        background: #FFC107;
    }

    .toast--info {
        background: #2196F3;
    }

    /* ── Inner (default = lg) ── */
    .toast-inner {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 16px 15px 18px;
    }

    /* ── Icon — พื้นขาว ไอคอนมีสี ── */
    .toast-ico {
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #fff;
        display: grid;
        place-items: center;
    }

    .toast-ico svg {
        width: 22px;
        height: 22px;
        fill: none;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .toast--success .toast-ico svg {
        stroke: #4CAF50;
    }

    .toast--error .toast-ico svg {
        stroke: #F44336;
    }

    .toast--warning .toast-ico svg {
        stroke: #F59E0B;
    }

    .toast--info .toast-ico svg {
        stroke: #2196F3;
    }

    /* ── Text ── */
    .toast-text {
        flex: 1;
        min-width: 0;
    }

    .toast-title {
        font-size: 15px;
        font-weight: 800;
        color: #fff;
        margin: 0 0 4px 0;
        letter-spacing: .01em;
        line-height: 1.2;
        text-: 0 1px 2px rgba(0, 0, 0, .10);
    }

    .toast-msg {
        font-size: 13px;
        line-height: 1.5;
        color: rgba(255, 255, 255, .92);
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
    }

    /* Warning — text เข้มบน background เหลือง */
    .toast--warning .toast-title {
        color: #3d2e00;
        text-: none;
    }

    .toast--warning .toast-msg {
        color: rgba(40, 28, 0, .80);
    }

    .toast--warning .toast-close {
        color: rgba(40, 28, 0, .55);
    }

    .toast--warning .toast-close:hover {
        color: #3d2e00;
        background: rgba(0, 0, 0, .08);
    }

    /* ── Close ── */
    .toast-close {
        flex-shrink: 0;
        border: 0;
        background: transparent;
        color: rgba(255, 255, 255, .80);
        width: 26px;
        height: 26px;
        border-radius: 6px;
        cursor: pointer;
        display: grid;
        place-items: center;
        font-size: 20px;
        font-weight: 700;
        line-height: 1;
        transition: color .12s, background .12s;
        outline: none !important;
    }

    .toast-close:hover {
        color: #fff;
        background: rgba(0, 0, 0, .12);
    }

    /* ── Progress bar ── */
    .toast-bar {
        height: 5px;
        background: rgba(0, 0, 0, .15);
    }

    .toast-fill {
        height: 100%;
        width: 0;
        transition: width linear;
        background: rgba(255, 255, 255, .45);
    }

    .toast--warning .toast-fill {
        background: rgba(0, 0, 0, .18);
    }

    /* ── Size overrides ── */
    .toast--sm {
        --toast-max-w: min(92vw, 300px);
        --toast-min-w: 220px;
    }

    .toast--sm .toast-inner {
        padding: 11px 11px 10px 13px;
        gap: 10px;
    }

    .toast--sm .toast-title {
        font-size: 13px;
    }

    .toast--sm .toast-msg {
        font-size: 12px;
    }

    .toast--sm .toast-ico {
        width: 32px;
        height: 32px;
    }

    .toast--sm .toast-ico svg {
        width: 16px;
        height: 16px;
    }

    .toast--md {
        --toast-max-w: min(92vw, 360px);
        --toast-min-w: 280px;
    }

    .toast--md .toast-inner {
        padding: 14px 14px 13px 16px;
        gap: 12px;
    }

    .toast--md .toast-title {
        font-size: 14px;
    }

    .toast--md .toast-msg {
        font-size: 12px;
    }

    .toast--md .toast-ico {
        width: 38px;
        height: 38px;
    }

    .toast--md .toast-ico svg {
        width: 19px;
        height: 19px;
    }

    /* lg = default (:root) — ไม่ต้องกำหนดซ้ำ */

    .toast--xl {
        --toast-max-w: min(92vw, 500px);
        --toast-min-w: 400px;
    }

    .toast--xl .toast-inner {
        padding: 18px 18px 17px 20px;
        gap: 16px;
    }

    .toast--xl .toast-title {
        font-size: 16px;
    }

    .toast--xl .toast-msg {
        font-size: 14px;
    }

    .toast--xl .toast-ico {
        width: 50px;
        height: 50px;
    }

    .toast--xl .toast-ico svg {
        width: 25px;
        height: 25px;
    }

    @media (max-width: 420px) {
        .toast-card {
            min-width: calc(100vw - 32px);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .toast-card {
            transition: none;
            transform: none;
        }

        .toast-fill {
            transition: none !important;
        }
    }
</style>

<div class="toast-overlay" aria-live="polite" aria-atomic="true"></div>

<script>
    (function() {
        const DEFAULT_POSITION = 'tr';
        const FORCE_POSITION = 'tr';
        const DEFAULT_SIZE = 'lg';

        function ensurePos(position) {
            const overlay = document.querySelector('.toast-overlay');
            if (!overlay) return null;
            let posEl = overlay.querySelector('.toast-pos');
            if (!posEl || !posEl.classList.contains(position)) {
                overlay.innerHTML = '';
                posEl = document.createElement('div');
                posEl.className = 'toast-pos ' + position;
                overlay.appendChild(posEl);
            }
            return posEl;
        }

        function titleByType(type) {
            return {
                success: 'สำเร็จ',
                error: 'เกิดข้อผิดพลาด',
                warning: 'โปรดตรวจสอบ',
                info: 'แจ้งเตือน',
            } [type] ?? 'แจ้งเตือน';
        }

        function iconSvg(type) {
            if (type === 'success')
                return `<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`;
            if (type === 'error')
                return `<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
            if (type === 'warning')
                return `<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`;
            return `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
        }

        function showToast({
            type = 'info',
            message = '',
            position = DEFAULT_POSITION,
            timeout = 3800,
            size = DEFAULT_SIZE,
            title = null
        } = {}) {
            type = ['success', 'info', 'warning', 'error'].includes(type) ? type : 'info';
            position = FORCE_POSITION || position || DEFAULT_POSITION;
            timeout = Number.isFinite(Number(timeout)) && Number(timeout) >= 800 ? Number(timeout) : 3800;
            size = ['sm', 'md', 'lg', 'xl'].includes(size) ? size : DEFAULT_SIZE;

            const posEl = ensurePos(position);
            if (!posEl) return;

            const card = document.createElement('section');
            card.className = `toast-card toast--${size} toast--${type}`;
            card.setAttribute('role', 'status');

            const inner = document.createElement('div');
            inner.className = 'toast-inner';

            const ico = document.createElement('div');
            ico.className = 'toast-ico';
            ico.innerHTML = iconSvg(type);

            const textWrap = document.createElement('div');
            textWrap.className = 'toast-text';

            const h = document.createElement('div');
            h.className = 'toast-title';
            h.textContent = title ?? titleByType(type);

            const p = document.createElement('p');
            p.className = 'toast-msg';
            p.textContent = message ?? '';

            textWrap.append(h, p);

            const btn = document.createElement('button');
            btn.className = 'toast-close';
            btn.setAttribute('aria-label', 'ปิด');
            btn.innerHTML = '&times;';

            inner.append(ico, textWrap, btn);

            const bar = document.createElement('div');
            bar.className = 'toast-bar';
            const fill = document.createElement('div');
            fill.className = 'toast-fill';
            bar.appendChild(fill);

            card.append(inner, bar);
            posEl.appendChild(card);

            requestAnimationFrame(() => {
                card.classList.add('show');
                requestAnimationFrame(() => {
                    fill.style.transition = `width ${timeout}ms linear`;
                    fill.style.width = '100%';
                });
            });

            let startAt = Date.now();
            let remain = timeout;
            let timer;

            function close() {
                clearTimeout(timer);
                card.classList.remove('show');
                card.classList.add('hide');
                setTimeout(() => card.remove(), 200);
            }

            timer = setTimeout(close, timeout + 60);
            btn.addEventListener('click', close);
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') close();
            }, {
                once: true
            });

            card.addEventListener('mouseenter', () => {
                clearTimeout(timer);
                remain = Math.max(0, remain - (Date.now() - startAt));
                fill.style.transition = 'none';
                fill.style.width = ((1 - remain / timeout) * 100) + '%';
            });

            card.addEventListener('mouseleave', () => {
                startAt = Date.now();
                fill.style.transition = `width ${remain}ms linear`;
                fill.style.width = '100%';
                timer = setTimeout(close, remain + 50);
            });
        }

        window.showToast = showToast;
        if (!window.__toastListenerAdded) {
            window.addEventListener('app:toast', e => showToast(e.detail || {}));
            window.__toastListenerAdded = true;
        }

        function checkSessionToast() {
            const el = document.getElementById('session-toast-data');
            if (!el) return;
            try {
                const data = JSON.parse(el.textContent);
                el.remove();

                // ถ้ากำลังเล่น intro animation อยู่ ให้รอจนกว่าจะเสร็จก่อน
                const html = document.documentElement;
                if (html.classList.contains('intro-pending')) {
                    // Two paths can fire this toast (intro finished / 5s safety fallback). Whichever
                    // wins must cancel the other, otherwise the toast is shown twice — which is what
                    // happened to "Login successful", the only toast that lands mid-intro.
                    let fired = false;
                    let fallbackTimer = null;
                    const fire = (delay) => {
                        if (fired) return;
                        fired = true;
                        observer.disconnect();
                        clearTimeout(fallbackTimer);
                        // หน่วงเพิ่มอีกนิดเพื่อให้ UI settle ก่อน toast โผล่
                        setTimeout(() => showToast(data), delay);
                    };

                    // sidebar-intro.js ยิง introReveal:done หรือลบ class intro-pending
                    // ใช้ MutationObserver เฝ้าดูการลบ class intro-pending
                    const observer = new MutationObserver(() => {
                        if (!html.classList.contains('intro-pending')) fire(300);
                    });
                    observer.observe(html, { attributes: true, attributeFilter: ['class'] });

                    // Safety fallback: ถ้านาน 5 วิแล้วยังไม่เสร็จก็ show ไปเลย
                    fallbackTimer = setTimeout(() => fire(0), 5000);
                } else {
                    showToast(data);
                }
            } catch (e) {
                console.error('Error parsing session toast data:', e);
            }
        }

        // Run immediately in case the script is evaluated by Turbo after turbo:load
        checkSessionToast();
        document.addEventListener('turbo:load', checkSessionToast);
        document.addEventListener('DOMContentLoaded', checkSessionToast);
    })();
</script>
