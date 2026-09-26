import Chart from 'chart.js/auto';

(function () {
    const activeCharts = [];
    function destroyAllCharts() { while (activeCharts.length) activeCharts.pop().destroy(); }
    let _rid = 0; // prevents stale double-init when run() called multiple times
    document.addEventListener('turbo:before-cache', destroyAllCharts);


    const parseJSON = (str, def = []) => {
        try { return JSON.parse(str); } catch (e) { return def; }
    };

    const DURATION = 1000;
    const EASING   = 'easeOutQuart';

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
     * Animates stat-card numbers.
     * Add  data-countup="2.5"  and  data-suffix="h"  to the <span> in Blade.
     */
    function initCountUp() {
        document.querySelectorAll('[data-countup]').forEach(el => {
            const target   = parseFloat(el.dataset.countup) || 0;
            const decimals = (el.dataset.countup.split('.')[1] || '').length;
            const suffix   = el.dataset.suffix || '';
            const dur      = 1200;
            let   started  = false;

            const io = new IntersectionObserver((entries) => {
                if (!entries[0].isIntersecting || started) return;
                started = true;
                io.disconnect();
                const t0 = performance.now();
                function tick(now) {
                    const p     = Math.min((now - t0) / dur, 1);
                    const eased = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);
                    el.textContent = (target * eased).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + suffix;
                    if (p < 1) requestAnimationFrame(tick);
                }
                requestAnimationFrame(tick);
            }, { threshold: 0.5 });
            io.observe(el);
        });
    }

    function run() {
        destroyAllCharts();
        const rid = ++_rid;
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color       = '#747781';

        document.fonts.ready.then(() => {
            if (rid !== _rid) return; // superseded — skip stale init

            // Trend Chart  Mixed Bar (resolution hours) + Line (compliance)
            const trendEl = document.getElementById('slaTrendChart');
            if (trendEl) {
                const ctx       = trendEl.getContext('2d');
                const trendData = parseJSON(trendEl.dataset.resolution);

                const trendChart = new Chart(ctx, {
                    type : 'bar',
                    data : {
                        labels   : parseJSON(trendEl.dataset.labels),
                        datasets : [
                            {
                                label              : 'Avg. Resolution (H)',
                                type               : 'bar',
                                data               : trendData,
                                backgroundColor    : 'rgba(15, 45, 92, 0.85)',
                                borderRadius       : 6,
                                borderSkipped      : 'bottom',
                                barPercentage      : 0.5,
                                categoryPercentage : 0.8,
                                yAxisID            : 'y',
                                order              : 2,
                            },
                            {
                                label                : 'Compliance Rate (%)',
                                type                 : 'line',
                                data                 : parseJSON(trendEl.dataset.compliance),
                                borderColor          : '#006c46',
                                backgroundColor      : '#006c46',
                                fill                 : false,
                                pointBackgroundColor : '#ffffff',
                                pointBorderColor     : '#006c46',
                                pointBorderWidth     : 2.5,
                                pointRadius          : 5,
                                pointHoverRadius     : 7,
                                tension              : 0.4,
                                borderWidth          : 2.5,
                                yAxisID              : 'y1',
                                order                : 1,
                                clip                 : false,
                            },
                        ],
                    },
                    options: {
                        responsive          : true,
                        maintainAspectRatio : false,
                        interaction         : { mode: 'nearest', intersect: true },

                        // Bars grow from baseline, each bar delayed 60ms
                        animation: {
                            duration : DURATION,
                            easing   : EASING,
                        },
                        datasets: {
                            bar  : { animation: { delay : (ctx) => ctx.dataIndex * 60 } },
                            line : { animation: { delay : 250 } },
                        },

                        scales: {
                            x  : { grid: { display: false } },
                            y  : {
                                type        : 'linear',
                                position    : 'left',
                                beginAtZero : true,
                                grid        : { color: '#f1f5f9' },
                                ticks       : { font: { size: 11 } },
                                grace       : '10%',
                            },
                            y1 : {
                                type           : 'linear',
                                position       : 'right',
                                beginAtZero    : true,
                                max            : 100,
                                grid           : { drawOnChartArea: false },
                                ticks          : { callback: v => v + '%' },
                            },
                        },
                        plugins: {
                            legend  : { display: false },
                            tooltip : tooltipDefaults,
                        },
                    },
                });

                activeCharts.push(trendChart);
                watchAndAnimate(trendEl, trendChart);
            }

            // Status Distribution  Doughnut (spin + scale in)
            const distEl = document.getElementById('slaDistChart');
            if (distEl) {
                const distChart = new Chart(distEl, {
                    type : 'doughnut',
                    data : {
                        labels   : parseJSON(distEl.dataset.labels),
                        datasets : [{
                            data            : parseJSON(distEl.dataset.values),
                            backgroundColor : ['#006c46', '#ba1a1a', '#e1a706', '#3e5d9d'],
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
                            legend: {
                                position : 'right',
                                labels   : {
                                    usePointStyle : true,
                                    boxWidth      : 8,
                                    font          : { size: 12, weight: 'bold' },
                                    color         : '#444650',
                                    padding       : 12,
                                },
                            },
                            tooltip : tooltipDefaults,
                        },
                    },

                });

                activeCharts.push(distChart);
                watchAndAnimate(distEl, distChart);
            }

            // Breaches by Department  Horizontal Bar (slide from left)
            const deptEl = document.getElementById('slaDeptChart');
            if (deptEl) {
                const deptCtx  = deptEl.getContext('2d');
                const deptVals = parseJSON(deptEl.dataset.values);

                const deptChart = new Chart(deptCtx, {
                    type : 'bar',
                    data : {
                        labels   : parseJSON(deptEl.dataset.labels),
                        datasets : [{
                            label              : 'Breached Tickets',
                            data               : deptVals,
                            backgroundColor    : 'rgba(225, 29, 72, 0.85)',
                            borderRadius       : 4,
                            borderSkipped      : 'left',
                            barPercentage      : deptVals.length <= 5 ? 0.35 : 0.8,
                            categoryPercentage : 0.8,
                        }],
                    },
                    options: {
                        responsive          : true,
                        maintainAspectRatio : false,
                        indexAxis           : 'y',

                        // Each row bar slides from x=0, staggered by 70ms
                        animation: {
                            duration : DURATION,
                            easing   : EASING,
                        },
                        animations: {
                            x: {
                                duration : DURATION,
                                easing   : EASING,
                                from     : 0,
                            },
                        },
                        datasets: {
                            bar: {
                                animation: {
                                    delay: (ctx) => ctx.dataIndex * 70,
                                },
                            },
                        },

                        scales: {
                            x : {
                                beginAtZero : true,
                                grid        : { color: '#f1f5f9' },
                                ticks       : { stepSize: 1, font: { size: 11, weight: 'bold' } },
                            },
                            y : {
                                grid  : { display: false },
                                ticks : { font: { size: 11, weight: 'bold' } },
                            },
                        },
                        plugins: {
                            legend  : { display: false },
                            tooltip : tooltipDefaults,
                        },
                    },
                });

                activeCharts.push(deptChart);
                watchAndAnimate(deptEl, deptChart);
            }

            // Stat card count-up (optional, see JSDoc above)
            initCountUp();

        }); // fonts.ready
    } // run()

    document.addEventListener('turbo:load', run);

    // Fallback: Turbo executes this lazy script AFTER turbo:load has already fired
    // (first Turbo navigation to this page). If canvases are in DOM, run immediately.
    if (document.getElementById('slaTrendChart') ||
        document.getElementById('slaDistChart')  ||
        document.getElementById('slaDeptChart')) {
        run();
    }
})();
