/**
 * Gráficos Chart.js — solo dashboard OSINT Deck.
 */
(function () {
    'use strict';

    function showBanner(wrapSelector, message) {
        var wrap = document.querySelector(wrapSelector);
        if (!wrap) {
            return;
        }
        wrap.textContent = message;
        wrap.style.color = '#646970';
        wrap.style.fontSize = '13px';
        wrap.style.padding = '12px';
    }

    function initCharts() {
        var cfg = typeof window.osintDeckDashboardCharts !== 'undefined' ? window.osintDeckDashboardCharts : null;

        if (!cfg) {
            showBanner('.osint-deck-charts-grid', 'No se cargaron los datos de los gráficos. Revisá la consola del navegador o recargá la página.');
            return;
        }

        if (typeof Chart === 'undefined') {
            showBanner(
                '.osint-deck-charts-grid',
                'No se pudo cargar Chart.js (biblioteca de gráficos). Comprobá que exista assets/vendor/chart.js/chart.umd.min.js o la conexión al CDN.'
            );
            return;
        }

        var palette = [
            '#2271b1',
            '#826eb4',
            '#00a32a',
            '#dba617',
            '#d63638',
            '#135e96',
            '#72aee6',
            '#9b51e0',
            '#50575e',
            '#1d2327',
            '#c3c4c7',
            '#8c8f94'
        ];

        function colorsFor(n) {
            var out = [];
            for (var i = 0; i < n; i++) {
                out.push(palette[i % palette.length]);
            }
            return out;
        }

        var baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 12
                    }
                }
            }
        };

        function showEmptyMessage(containerId, message) {
            var el = document.getElementById(containerId);
            if (!el || !el.parentNode) {
                return;
            }
            var p = document.createElement('p');
            p.className = 'osint-chart-empty';
            p.textContent = message;
            el.parentNode.insertBefore(p, el.nextSibling);
            el.style.display = 'none';
        }

        var catCanvas = document.getElementById('osint-chart-categories');
        if (catCanvas && cfg.categories && cfg.categories.labels && cfg.categories.labels.length) {
            if (!cfg.hasCategoryData) {
                showEmptyMessage('osint-chart-categories', cfg.i18n.empty);
            } else {
                new Chart(catCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: cfg.categories.labels,
                        datasets: [{
                            data: cfg.categories.counts,
                            backgroundColor: colorsFor(cfg.categories.labels.length),
                            borderWidth: 1,
                            borderColor: '#fff'
                        }]
                    },
                    options: Object.assign({}, baseOptions, {
                        cutout: '52%',
                        layout: {
                            padding: { top: 6, right: 8, bottom: 8, left: 6 }
                        },
                        plugins: Object.assign({}, baseOptions.plugins, {
                            title: {
                                display: false
                            },
                            legend: {
                                display: true,
                                position: 'right',
                                align: 'center',
                                labels: {
                                    boxWidth: 12,
                                    boxHeight: 12,
                                    padding: 10,
                                    font: { size: 11 },
                                    color: '#1d2327'
                                }
                            }
                        })
                    })
                });
            }
        }

        var clicksCanvas = document.getElementById('osint-chart-clicks');
        if (clicksCanvas && cfg.topClicks && cfg.topClicks.labels && cfg.topClicks.labels.length) {
            if (!cfg.hasClicksData) {
                showEmptyMessage('osint-chart-clicks', cfg.i18n.empty);
            } else {
                new Chart(clicksCanvas, {
                    type: 'bar',
                    data: {
                        labels: cfg.topClicks.labels,
                        datasets: [{
                            label: cfg.i18n.labelClicks,
                            data: cfg.topClicks.counts,
                            backgroundColor: palette[0],
                            borderRadius: 4
                        }]
                    },
                    options: Object.assign({}, baseOptions, {
                        indexAxis: 'y',
                        plugins: Object.assign({}, baseOptions.plugins, {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var v = ctx.parsed && typeof ctx.parsed.x === 'number' ? ctx.parsed.x : ctx.raw;
                                        return cfg.i18n.labelClicks + ': ' + v;
                                    }
                                }
                            }
                        }),
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            },
                            y: {
                                ticks: {
                                    autoSkip: false,
                                    font: { size: 11 }
                                }
                            }
                        }
                    })
                });
            }
        }

        var softCanvas = document.getElementById('osint-chart-engagement-soft');
        if (softCanvas && cfg.engagementComparable && cfg.engagementComparable.labels) {
            if (!cfg.hasEngagementComparable) {
                showEmptyMessage('osint-chart-engagement-soft', cfg.i18n.empty);
            } else {
                var ec = cfg.engagementComparable;
                var ecColors = [palette[2], palette[1], palette[4]];
                new Chart(softCanvas, {
                    type: 'bar',
                    data: {
                        labels: ec.labels,
                        datasets: [{
                            label: cfg.i18n.titleEngagementSoft || 'Interacciones',
                            data: ec.values,
                            backgroundColor: ecColors.slice(0, ec.labels.length),
                            borderRadius: 6
                        }]
                    },
                    options: Object.assign({}, baseOptions, {
                        indexAxis: 'y',
                        plugins: Object.assign({}, baseOptions.plugins, {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var v = ctx.parsed && typeof ctx.parsed.x === 'number' ? ctx.parsed.x : ctx.raw;
                                        return (ctx.label || '') + ': ' + v;
                                    }
                                }
                            }
                        }),
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.06)'
                                }
                            },
                            y: {
                                ticks: {
                                    font: { size: 12 }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    })
                });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }
})();
