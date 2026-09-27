// resources/js/repair/dashboard.js

// Chart.js is ~200 kB, and this module is part of the main bundle (app.js imports it so the turbo:load listener below
// exists on every page). So the library is fetched the first time a page that really has a chart asks for it — not with
// every page, as a static import did.
let chartLib;
const loadChart = () => (chartLib ??= import('chart.js/auto')
  .then(({ default: Chart }) => {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color       = '#747781';
    return Chart;
  })
  .catch((error) => { chartLib = undefined; throw error; })); // never cache a failed load: the next visit asks again

const CHART_CANVASES = ['trendBar', 'typeDonut', 'statusDonut', 'deptBar'];

(function () {
  // Track active Chart.js instances so we can destroy them before Turbo caches the page
  const activeCharts = [];

  function destroyAllCharts() {
    while (activeCharts.length) activeCharts.pop().destroy();
  }

  // Clean up before Turbo snapshots the page (prevents canvas-already-in-use errors on back-nav)
  document.addEventListener('turbo:before-cache', destroyAllCharts);

  const parseJSON = (str, def = []) => {
    try { return JSON.parse(str); } catch (e) { return def; }
  };

  const showToast = ({ type = 'info', message = '', timeout = 3200 } = {}) => {
    const overlay = document.querySelector('.ui-toast-overlay');
    if (!overlay || !message) return;

    const el = document.createElement('div');
    const typeClass = ({
      success: 'ui-success',
      error: 'ui-error',
      warning: 'ui-warning',
      info: 'ui-info',
    }[type] || 'ui-info');

    el.className = `ui-toast-card ${typeClass}`;
    el.innerHTML = `
      <div class="ui-toast-msg"></div>
      <button class="ui-toast-x" type="button" aria-label="Close">&times;</button>
    `;
    el.querySelector('.ui-toast-msg').textContent = message;

    const close = () => {
      el.classList.add('ui-toast-out');
      setTimeout(() => el.remove(), 250);
    };

    el.querySelector('.ui-toast-x')?.addEventListener('click', close);
    if (timeout > 0) setTimeout(close, timeout);

    overlay.appendChild(el);
  };

  const safeGet = (id) => document.getElementById(id);

  const DURATION = 1000;
  const EASING   = 'easeOutQuart';

  function watchAndAnimate(canvas, chart) {
      if (!('IntersectionObserver' in window)) return;
      const io = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
              if (entry.isIntersecting) {
                  io.unobserve(entry.target);
                  chart.reset();
                  chart.update();
              }
          });
      }, { threshold: 0.25 });
      io.observe(canvas);
  }

  /**
   * Horizontal rows (Technician Workload): a mouse cannot scroll them (the wheel is vertical and the
   * scrollbar is thin), so add what a mouse needs — drag the row, or use the arrows. Touch and trackpads
   * still scroll natively.
   *   <div data-hscroll> [data-hscroll-prev] [data-hscroll-next] <div data-hscroll-track class="overflow-x-auto">…
   */
  function initHScrollers() {
    document.querySelectorAll('[data-hscroll]').forEach((root) => {
      const track = root.querySelector('[data-hscroll-track]');
      if (!track) return;
      const prev = root.querySelector('[data-hscroll-prev]');
      const next = root.querySelector('[data-hscroll-next]');

      const update = () => {
        const max = track.scrollWidth - track.clientWidth;
        prev?.classList.toggle('hidden', track.scrollLeft <= 1);
        next?.classList.toggle('hidden', track.scrollLeft >= max - 1);
        track.classList.toggle('cursor-grab', max > 1);
      };
      const page = (dir) => track.scrollBy({ left: dir * Math.round(track.clientWidth * 0.8), behavior: 'smooth' });
      prev?.addEventListener('click', () => page(-1));
      next?.addEventListener('click', () => page(1));
      track.addEventListener('scroll', update, { passive: true });
      new ResizeObserver(update).observe(track);
      update();

      // drag with the mouse; only captured once the pointer really moves, so a plain click still opens the link
      let armed = false, dragging = false, justDragged = false, startX = 0, startLeft = 0;
      track.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse' || e.button !== 0) return;
        armed = true; dragging = false; startX = e.clientX; startLeft = track.scrollLeft;
      });
      track.addEventListener('pointermove', (e) => {
        if (!armed) return;
        const dx = e.clientX - startX;
        if (!dragging && Math.abs(dx) > 5) {
          dragging = true;
          track.setPointerCapture(e.pointerId);
          track.classList.replace('cursor-grab', 'cursor-grabbing');
        }
        if (dragging) track.scrollLeft = startLeft - dx;
      });
      const stop = (e) => {
        if (!armed) return;
        armed = false;
        if (dragging) {
          dragging = false;
          justDragged = true;
          setTimeout(() => { justDragged = false; }, 0);
          if (track.hasPointerCapture(e.pointerId)) track.releasePointerCapture(e.pointerId);
          track.classList.remove('cursor-grabbing');
          update();
        }
      };
      track.addEventListener('pointerup', stop);
      track.addEventListener('pointercancel', stop);
      // a drag must not open the technician under the cursor; images / links must not start a native drag
      track.addEventListener('click', (e) => { if (justDragged) { e.preventDefault(); e.stopPropagation(); } }, true);
      track.addEventListener('dragstart', (e) => e.preventDefault());
    });
  }

  // turbo:load fires on first load AND every Turbo navigation
  let visit = 0;
  document.addEventListener('turbo:load', () => {
    const thisVisit = ++visit;
    destroyAllCharts(); // reset before re-initialising

    /**
     * Animates stat-card numbers.
     * Add  data-countup="25"  to any element.
     */
    const initCountUp = () => {
      document.querySelectorAll('[data-countup]').forEach(el => {
        const target = parseFloat(el.dataset.countup) || 0;
        const decimals = (el.dataset.countup.split('.')[1] || '').length;
        const suffix = el.dataset.suffix || '';
        const dur = 1200;
        let started = false;

        const io = new IntersectionObserver((entries) => {
          if (!entries[0].isIntersecting || started) return;
          started = true;
          io.disconnect();
          const t0 = performance.now();
          function tick(now) {
            const p = Math.min((now - t0) / dur, 1);
            const eased = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);
            el.textContent = (target * eased).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + suffix;
            if (p < 1) requestAnimationFrame(tick);
          }
          requestAnimationFrame(tick);
        }, { threshold: 0.5 });
        io.observe(el);
      });
    };

    initCountUp();
    initHScrollers();

    const tooltipDefaults = {
        backgroundColor : '#0F2D5C',
        titleColor      : '#ffffff',
        bodyColor       : '#e2e8f0',
        borderColor     : '#0F2D5C',
        borderWidth     : 1,
        padding         : 12,
        cornerRadius    : 8,
        position        : 'nearest',
        titleFont : { family: 'Inter', size: 13, weight: 'bold' },
        bodyFont  : { family: 'Inter', size: 12 },
    };

    // (charts tracked in module-level activeCharts array)

    // AssetCentral Theme Colors
    const PRIMARY_NAVY = '#00275f';
    const SECONDARY_GREEN = '#006c46';
    const ACCENT_BLUE = '#00609b';
    const DANGER_RED = '#ba1a1a';
    
    const BLUE_SET = [PRIMARY_NAVY, ACCENT_BLUE, SECONDARY_GREEN, DANGER_RED, '#6b7280', '#94a3b8'];

    const commonOptions = {
        responsive          : true,
        maintainAspectRatio : false,
        interaction         : { mode: 'nearest', intersect: true },

        // Bars grow from baseline, each bar delayed 60ms
        animation: {
            duration : DURATION,
            easing   : EASING,
        },
        animations: {
            y: {
                duration : DURATION,
                easing   : EASING,
                from     : (ctx) => {
                    if (ctx.type !== 'data') return undefined;
                    const chart = ctx.chart;
                    return chart.scales.y
                        ? chart.scales.y.getPixelForValue(0)
                        : undefined;
                },
            },
        },
        datasets: {
            bar  : { animation: { delay : (ctx) => ctx.dataIndex * 60 } },
        },

        scales: {
            x  : { grid: { display: false } },
            y  : {
                type        : 'linear',
                position    : 'left',
                beginAtZero : true,
                grid        : { color: '#f1f5f9' },
                ticks       : { font: { size: 11 }, stepSize: 1 },
            },
        },
        plugins: {
            legend  : { display: false },
            tooltip : tooltipDefaults,
        },
    };

    // donut factory
    const donutConfig = (labels, data, colors) => ({
      type : 'doughnut',
      data : {
          labels   : labels,
          datasets : [{
              data            : data,
              backgroundColor : colors,
              borderWidth     : 3,
              borderColor     : '#ffffff',
              hoverOffset     : 10,
          }],
      },
      options: {
          responsive          : true,
          maintainAspectRatio : false,
          cutout              : '70%',

          // Spin in from 0, scale up, segments staggered
          animation: {
              animateRotate : true,
              animateScale  : true,
              duration      : DURATION + 100,
              easing        : EASING,
          },
          animations: {
              delay: {
                  fn   : (ctx) => ctx.dataIndex * 80,
                  from : 0,
              },
          },

          plugins: {
              legend: { display: false },
              tooltip : tooltipDefaults,
          },
      },
    });

    document.fonts.ready.then(async () => {
      if (!CHART_CANVASES.some(safeGet)) return; // no chart on this page: do not fetch the library

      let Chart;
      try {
        Chart = await loadChart();
      } catch (error) {
        console.error('[dashboard] Chart.js could not be loaded', error);
        return;
      }
      if (thisVisit !== visit) return; // a newer visit took over while the library was loading

      // 1. Monthly Trend Bar
      const trendEl = safeGet('trendBar');
      if (trendEl) {
        const trendCtx = trendEl.getContext('2d');
        const vals = parseJSON(trendEl.dataset.values);
        
        const trendChart = new Chart(trendCtx, {
          type: 'bar',
          data: {
            labels: parseJSON(trendEl.dataset.labels),
            datasets: [{
              label: 'จำนวนแจ้งซ่อม',
              data: vals,
              backgroundColor: 'rgba(15, 45, 92, 0.85)',
              borderRadius: 6,
              borderSkipped: 'bottom',
              barPercentage: 0.45,
              categoryPercentage: 0.9,
            }],
          },
          options: commonOptions,
        });
        activeCharts.push(trendChart);
        watchAndAnimate(trendEl, trendChart);
      }

      // 2. Asset Type donut
      const typeEl = safeGet('typeDonut');
      if (typeEl) {
        const typeChart = new Chart(typeEl.getContext('2d'), donutConfig(
          parseJSON(typeEl.dataset.labels),
          parseJSON(typeEl.dataset.values),
          BLUE_SET
        ));
        activeCharts.push(typeChart);
        watchAndAnimate(typeEl, typeChart);
      }

      // 3. Status donut
      const statusEl = safeGet('statusDonut');
      if (statusEl) {
        const statusChart = new Chart(statusEl.getContext('2d'), donutConfig(
          ['รอดำเนินการ', 'กำลังดำเนินการ', 'ซ่อมบำรุงเสร็จสิ้น', 'อนุมัติแล้ว', 'ยกเลิกการซ่อมบำรุง/ไม่รับเรื่อง'],
          [
            Number(statusEl.dataset.pending || 0),
            Number(statusEl.dataset.progress || 0),
            Number(statusEl.dataset.completed || 0),
            Number(statusEl.dataset.cancelled || 0),
          ],
          [ACCENT_BLUE, PRIMARY_NAVY, SECONDARY_GREEN, DANGER_RED]
        ));
        activeCharts.push(statusChart);
        watchAndAnimate(statusEl, statusChart);
      }

      // 4. Department Bar
      const deptEl = safeGet('deptBar');
      if (deptEl) {
        const deptCtx = deptEl.getContext('2d');
        const vals = parseJSON(deptEl.dataset.values);

        const deptChart = new Chart(deptCtx, {
          type: 'bar',
          data: {
            labels: parseJSON(deptEl.dataset.labels),
            datasets: [{
              label: 'จำนวนงาน',
              data: vals,
              backgroundColor: SECONDARY_GREEN,
              borderRadius: 6,
              borderSkipped: 'bottom',
              barPercentage: 0.5,
              categoryPercentage: 0.8,
            }],
          },
          options: commonOptions,
        });
        activeCharts.push(deptChart);
        watchAndAnimate(deptEl, deptChart);
      }
    });

    const safeResizeAll = () => requestAnimationFrame(() => activeCharts.forEach((c) => c.resize()));

    // filter toggle
    const btn = safeGet('filterToggle');
    const panel = safeGet('filtersPanel');
    if (btn && panel) {
      btn.addEventListener('click', () => {
        panel.classList.toggle('hidden');
        safeResizeAll();
      });
    }

    // toast
    const payload = window.__DASH__ || {};
    if (payload?.message) {
      showToast({
        message: payload.message,
        type: payload.type || 'info',
        timeout: Number(payload.timeout || 3200),
      });
    }
  });
})();
