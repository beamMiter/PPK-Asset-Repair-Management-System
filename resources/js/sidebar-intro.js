// resources/js/sidebar-intro.js
(() => {
  const NEXT = 'ui.sidebarIntro.next';
  if (sessionStorage.getItem(NEXT) !== '1') return;
  sessionStorage.removeItem(NEXT);

  const HOLD_MS = 1600;
  const FLY_MS  = 900;
  const START_SCALE = 3.0; // ปรับลงมาเป็น 3 เท่า เพื่อให้ดูพอดี ไม่ใหญ่จนล้นจอ
  const PERSPECTIVE = 1000;
  const TILT_X = 6;
  
  // ซ่อน loader ของระบบถ้ามี
  const hideGenericLoader = () => {
      const loader = document.getElementById('loaderOverlay');
      if (loader) loader.classList.remove('show');
  };

  const forceRevealAll = (reason = '') => {
    const html = document.documentElement;
    html.classList.remove('intro-pending', 'intro-reveal');
    html.classList.add('intro-show-nav', 'intro-show-side', 'intro-show-main', 'intro-show-footer');
    if (reason) console.warn('[intro] forceRevealAll:', reason);
    hideGenericLoader();
  };

  // Skip controller (คลิกหรือกดคีย์บอร์ดแล้วเข้าไปเลย)
  const setupSkip = (cancelCallback) => {
    let done = false;
    const cancel = () => {
      if (done) return;
      done = true;
      cleanup();
      cancelCallback();
    };
    const onPointer = () => cancel();
    const onKey = (e) => {
      if (['Escape', ' ', 'Enter'].includes(e.key)) cancel();
    };
    const cleanup = () => {
      window.removeEventListener('pointerdown', onPointer);
      window.removeEventListener('keydown', onKey);
    };
    window.addEventListener('pointerdown', onPointer);
    window.addEventListener('keydown', onKey);
    return { done: () => done, cleanup };
  };

  const runIntro = () => {
    hideGenericLoader(); // หน้าหลักรอ intro อยู่
    
    // พยายามหา Logo ใน Sidebar หรือ Navbar เป็นจุดหมายปลายทาง
    const logo = 
      document.getElementById('desktopSidebarLogo') || 
      document.getElementById('sidebarLogo') || 
      document.querySelector('.sidebar-brand-block img') ||
      document.querySelector('.brand-logo');

    if (!logo) return forceRevealAll('logo-not-found');

    let rect = logo.getBoundingClientRect();
    let imgW = rect.width || 40; // ถ้าภาพยังไม่โหลดให้คว้าค่า default เบื้องต้นไว้เลย
    let imgH = rect.height || 40; 
    
    const prevVis = logo.style.visibility;
    logo.style.visibility = 'hidden';

    const startX = (window.innerWidth / 2) - (imgW / 2);
    const startY = (window.innerHeight / 2) - (imgH / 2);

    // สร้างฉากเบลอสีขาว
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:999999;background:rgba(255,255,255,1);transition:opacity 0.4s ease;opacity:1;';
    document.body.appendChild(overlay);

    // กล่องสำหรับ 3D Transform
    const wrap = document.createElement('div');
    wrap.style.cssText = `position:fixed;left:0;top:0;width:${imgW}px;height:${imgH}px;transform:translate3d(${startX}px, ${startY}px, 0);perspective:${PERSPECTIVE}px;z-index:1000000;pointer-events:none;`;
    document.body.appendChild(wrap);

    const spinner = document.createElement('div');
    spinner.style.cssText = `width:${imgW}px;height:${imgH}px;position:relative;transform-style:preserve-3d;transform:scale(${START_SCALE}) rotateX(${TILT_X}deg) rotateY(0deg);filter:drop-shadow(0 15px 35px rgba(0,0,0,0.25));`;
    wrap.appendChild(spinner);

    const makeFace = (rotateYdeg) => {
      const face = document.createElement('img');
      face.src = logo.currentSrc || logo.src || '/images/logoppk.png'; // ใช้โลโก้โรงพยาบาลโดยตรง
      face.style.cssText = `position:absolute;inset:0;width:100%;height:100%;object-fit:contain;backface-visibility:hidden;transform:rotateY(${rotateYdeg}deg);`;
      return face;
    };

    spinner.appendChild(makeFace(0));
    spinner.appendChild(makeFace(180));

    const cleanup = () => {
      wrap.remove();
      overlay.remove();
      logo.style.visibility = prevVis || 'visible';
      forceRevealAll();
    };

    // ควบคุมการข้าม
    const skipper = setupSkip(() => cleanup());

    // 1. นำเสนอหมุนๆ (Spin)
    const spin = spinner.animate([
        { transform: `scale(${START_SCALE}) rotateX(${TILT_X}deg) rotateY(0deg)` },
        { transform: `scale(${START_SCALE}) rotateX(${TILT_X}deg) rotateY(720deg)` },
    ], { duration: HOLD_MS, easing: 'cubic-bezier(0.4, 0, 0.2, 1)', fill: 'forwards' });

    spin.finished.then(() => {
        if (skipper.done()) return;

        // อัพเดตตำแหน่งจุดที่จะบินไปเผื่อ Sidebar เลื่อน
        rect = logo.getBoundingClientRect();
        const destX = rect.left || 20;
        const destY = rect.top || 20;

        // 2. บินกลับเข้าที่ (Fly)
        wrap.animate([
            { transform: `translate3d(${startX}px, ${startY}px, 0)` },
            { transform: `translate3d(${destX}px, ${destY}px, 0)` }
        ], { duration: FLY_MS, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });

        const settle = spinner.animate([
            { transform: `scale(${START_SCALE}) rotateX(${TILT_X}deg) rotateY(720deg)` },
            { transform: 'scale(1) rotateX(0deg) rotateY(360deg)' },
        ], { duration: FLY_MS, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });

        settle.onfinish = () => {
            if (skipper.done()) return;
            overlay.style.opacity = '0';
            setTimeout(cleanup, 400); // รอฉากขาว Fade out ค่อยเผยหน้า UI แท้จริง
        };
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runIntro, { once: true });
  } else {
    runIntro();
  }
})();
