// resources/js/maintenance/rating/technicians-dashboard.js
import Chart from 'chart.js/auto';
import { initBoard } from './technician-board';

const parseJSON = (str, def = []) => {
    try { return JSON.parse(str); } catch (e) { return def; }
};

let _activeChart = null;
let _rid = 0;
function destroyActiveChart() { if (_activeChart) { _activeChart.destroy(); _activeChart = null; } }
document.addEventListener('turbo:before-cache', destroyActiveChart);


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

function run() {
    destroyActiveChart();
    const rid = ++_rid;
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color       = '#747781';

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

    document.fonts.ready.then(() => {
        if (rid !== _rid) return; // superseded — skip stale init
        /* ─── Chart ─── */
        const ctxEl = document.getElementById('techRatingChart');
        if (ctxEl) {
            const ctx = ctxEl.getContext('2d');
            const dataVals = parseJSON(ctxEl.dataset.values);
            
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels:   parseJSON(ctxEl.dataset.labels),
                    datasets: [{
                        label:              'คะแนนเฉลี่ย',
                        data:               dataVals,
                        backgroundColor:    'rgba(15, 45, 92, 0.85)',
                        borderRadius:       6,
                        borderSkipped:      'bottom',
                        barPercentage:      0.5,
                        categoryPercentage: 0.8,
                    }],
                },
                options: {
                    responsive:          true,
                    maintainAspectRatio: false,
                    interaction:         { mode: 'nearest', intersect: true },
                    
                    animation: {
                        duration : DURATION,
                        easing   : EASING,
                    },
                    animations: {
                        y: {
                            duration : DURATION,
                            easing   : EASING,
                            from     : (c) => {
                                if (c.type !== 'data') return undefined;
                                return c.chart.scales.y ? c.chart.scales.y.getPixelForValue(0) : undefined;
                            },
                        },
                    },
                    datasets: {
                        bar: { animation: { delay: (c) => c.dataIndex * 60 } }
                    },
                    
                    scales: {
                        y: {
                            beginAtZero: true,
                            max:         5,
                            ticks:       { stepSize: 1, font: { size: 11 } },
                            grid:        { color: '#f1f5f9' },
                            grace:       '5%',
                        },
                        x: {
                            ticks: { font: { size: 11, weight: 'bold' } },
                            grid:  { display: false },
                        },
                    },
                    plugins: {
                        legend:  { display: false },
                        tooltip: {
                            ...tooltipDefaults,
                            callbacks: {
                                label: c => ` คะแนนเฉลี่ย: ${c.parsed.y.toFixed(2)}`,
                            },
                        },
                    },
                },
            });
            _activeChart = chart;
            watchAndAnimate(ctxEl, chart);
        }
    });
} // run()

document.addEventListener('turbo:load', run);
document.addEventListener('turbo:load', () => initBoard());

// Fallback: Turbo executes this lazy script AFTER turbo:load fires on first navigation.
// Check for the chart canvas — if it's in DOM, run immediately.
if (document.getElementById('techRatingChart')) {
    run();
}
initBoard(); // same reason; it binds each control once and does nothing on other pages
