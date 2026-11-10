/**
 * The traffic page (app/Views/admin_traffic.php): fills each card from admin/traffic/data/*, which forwards to the
 * API's v1/staff/traffic/*. Cards load apart, so a slow or failed one does not hold up the rest.
 * The period and business are kept in the address bar (?from=&to=&business=), so a view can be linked to.
 * Dates are local days of the timezone the API answers with; visit times come in UTC and are shown in it.
 */
(function () {
    'use strict';

    const root = document.getElementById('traffic');
    if (!root) {
        return;
    }

    const SITE = root.dataset.site;
    const IS_MART = SITE === 'mart';
    const URL_BASE = root.dataset.url;
    const PAGE_SIZE = 20;
    const COLOR = '#c97232';
    const SOURCES = { direct: 'Direct', search: 'Search', social: 'Social', referral: 'Other sites', campaign: 'Campaigns' };
    const DEVICES = { desktop: 'Desktop', mobile: 'Mobile', tablet: 'Tablet', bot: 'Bot', '': 'Unknown' };
    const DEVICE_ICONS = { desktop: 'bi-display', mobile: 'bi-phone', tablet: 'bi-tablet', bot: 'bi-robot' };
    const STEPS = { visits: 'Visits', view_item: 'Viewed an item', add_to_cart: 'Added to cart', checkout_start: 'Started checkout', checkout_confirm: 'Ordered' };
    const STAGES = ['', 'Viewed item', 'Added to cart', 'Checkout', 'Ordered'];
    // pages that show a visitor of the website is weighing us up
    const INTEREST = /\/(pricing|plans?|contact|sign-?up|register|demo|get-started)(\/|$)/i;

    const state = {
        from: root.dataset.from || '',
        to: root.dataset.to || '',
        business: root.dataset.business || '',
        timezone: 'Asia/Bangkok',
        currency: null,
        measure: 'visits',
        sourceMode: 'source',
        visitsStart: 0,
        visitsTotal: 0,
        series: [],
        previousSeries: [],
        loadId: 0
    };

    const numberFormat = new Intl.NumberFormat('en-US');
    let countryNames = null;
    try {
        countryNames = new Intl.DisplayNames(['en'], { type: 'region' });
    } catch (e) {
        countryNames = null;
    }

    // ---- helpers ------------------------------------------------------------------------------------------------

    function esc(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function num(value) {
        return numberFormat.format(Math.round(value || 0));
    }

    function pct(ratio, digits) {
        return (Math.round((ratio || 0) * 1000) / 10).toFixed(digits === undefined ? 1 : digits) + '%';
    }

    function money(value) {
        const amount = Math.round((value || 0) * 100) / 100;
        if (!state.currency) {
            return numberFormat.format(amount);
        }
        try {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: state.currency, maximumFractionDigits: 0 }).format(amount);
        } catch (e) {
            return state.currency + ' ' + numberFormat.format(amount);
        }
    }

    function duration(seconds) {
        seconds = Math.max(0, Math.round(seconds || 0));
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) {
            return h + 'h ' + String(m).padStart(2, '0') + 'm';
        }
        if (m > 0) {
            return m + 'm ' + String(s).padStart(2, '0') + 's';
        }
        return s + 's';
    }

    function country(code) {
        if (!code) {
            return 'Unknown';
        }
        try {
            return (countryNames && countryNames.of(code)) || code;
        } catch (e) {
            return code;
        }
    }

    function flag(code) {
        return code ? '<span class="fi fi-' + esc(code.toLowerCase()) + ' me-2"></span>' : '<i class="bi bi-question-circle me-2 text-muted"></i>';
    }

    /** A UTC time from the API ("2026-11-09 14:02:00") in the page's timezone */
    function localTime(utc) {
        const date = new Date(String(utc).replace(' ', 'T') + 'Z');
        const day = new Intl.DateTimeFormat('en-GB', { timeZone: state.timezone, day: 'numeric', month: 'short' }).format(date);
        const time = new Intl.DateTimeFormat('en-GB', { timeZone: state.timezone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(date);
        return { day: day, time: time };
    }

    function shortDay(ymd) {
        const [y, m, d] = ymd.split('-').map(Number);
        return new Intl.DateTimeFormat('en-GB', { timeZone: 'UTC', day: 'numeric', month: 'short' }).format(new Date(Date.UTC(y, m - 1, d)));
    }

    function longDay(ymd) {
        const [y, m, d] = ymd.split('-').map(Number);
        return new Intl.DateTimeFormat('en-GB', { timeZone: 'UTC', day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(Date.UTC(y, m - 1, d)));
    }

    /** YYYY-MM-DD of today in the page's timezone, moved by some days */
    function today(offsetDays) {
        const parts = new Intl.DateTimeFormat('en-CA', { timeZone: state.timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
        const [y, m, d] = parts.split('-').map(Number);
        const date = new Date(Date.UTC(y, m - 1, d + (offsetDays || 0)));
        return date.toISOString().slice(0, 10);
    }

    function cssVar(name, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }

    function isDark() {
        return document.documentElement.getAttribute('data-theme') === 'dark';
    }

    function message(text, icon) {
        return '<div class="tr-empty"><i class="bi ' + (icon || 'bi-moon-stars') + '"></i><span>' + esc(text) + '</span></div>';
    }

    function loading(el) {
        if (el) {
            el.innerHTML = '<div class="tr-loading"><span class="spinner-border spinner-border-sm"></span></div>';
        }
    }

    function failed(el, error) {
        if (el) {
            el.innerHTML = message(error && error.message ? error.message : 'Could not load this. Please try again.', 'bi-exclamation-triangle');
        }
    }

    /** POST to admin/traffic/data/{endpoint}; resolves with the API's data, rejects with its message */
    function post(endpoint, body) {
        // the page's site, period and business, unless the request names its own
        const payload = { site: SITE };
        if (state.from) {
            payload.from = state.from;
        }
        if (state.to) {
            payload.to = state.to;
        }
        if (IS_MART && state.business) {
            payload.business_slug = state.business;
        }
        Object.assign(payload, body || {});
        Object.keys(payload).forEach(function (key) {
            if (payload[key] === undefined) {
                delete payload[key];
            }
        });
        return fetch(URL_BASE + endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (json) {
                if (!response.ok || json.status !== 'success') {
                    throw new Error(json.message || 'Could not load this. Please try again.');
                }
                return json.data || {};
            });
        });
    }

    function syncAddress() {
        const params = new URLSearchParams();
        if (state.from) {
            params.set('from', state.from);
        }
        if (state.to) {
            params.set('to', state.to);
        }
        if (IS_MART && state.business) {
            params.set('business', state.business);
        }
        const query = params.toString();
        history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
    }

    // ---- headline numbers and the chart -------------------------------------------------------------------------

    function trend(el, now, before, kind) {
        el.classList.remove('positive', 'negative');
        if (kind === 'rate') {
            const points = Math.round((now - before) * 1000) / 10;
            if (before === 0 && now === 0) {
                el.innerHTML = '&nbsp;';
                return;
            }
            el.textContent = (points > 0 ? '+' : points < 0 ? '−' : '±') + Math.abs(points).toFixed(1) + ' pts';
            // a lower bounce rate is better
            if (points !== 0) {
                el.classList.add(points < 0 ? 'positive' : 'negative');
            }
            return;
        }
        if (before === 0) {
            el.textContent = now > 0 ? 'new' : '–';
            return;
        }
        const change = Math.round(((now - before) / before) * 1000) / 10;
        el.textContent = (change > 0 ? '+' : change < 0 ? '−' : '±') + Math.abs(change).toFixed(1) + '%';
        if (change !== 0) {
            el.classList.add(change > 0 ? 'positive' : 'negative');
        }
    }

    function renderKpis(data) {
        const totals = data.totals;
        const previous = data.previous;
        const show = {
            visits: num(totals.visits),
            visitors: num(totals.visitors),
            new_visitors: num(totals.new_visitors),
            pageviews: num(totals.pageviews),
            bounce_rate: pct(totals.bounce_rate),
            avg_visit_s: duration(totals.avg_visit_s)
        };
        Object.keys(show).forEach(function (key) {
            const item = root.querySelector('[data-kpi="' + key + '"]');
            item.querySelector('.fx-kpi-value').textContent = show[key];
            trend(item.querySelector('.fx-kpi-trend'), totals[key], previous[key], key === 'bounce_rate' ? 'rate' : 'count');
        });
        document.getElementById('tr-period-note').textContent = longDay(data.from) + ' – ' + longDay(data.to)
            + ', compared with ' + longDay(previous.from) + ' – ' + longDay(previous.to) + ' · ' + state.timezone.replace('_', ' ') + ' time';
    }

    let chart = null;

    function renderChart() {
        const el = document.getElementById('tr-chart');
        const measure = state.measure;
        const current = state.series.map(function (day) {
            return { x: shortDay(day.day), y: day[measure] || 0 };
        });
        const previous = state.previousSeries.map(function (day, i) {
            return { x: current[i] ? current[i].x : shortDay(day.day), y: day[measure] || 0 };
        });
        const isMoney = measure === 'revenue';
        const muted = cssVar('--muted-color', '#5f6e83');
        const options = {
            chart: { type: 'area', height: 280, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', foreColor: muted, animations: { speed: 300 } },
            series: [
                { name: 'This period', data: current },
                { name: 'Period before', data: previous.slice(0, current.length) }
            ],
            colors: [COLOR, muted],
            stroke: { width: [2.5, 1.5], curve: 'smooth', dashArray: [0, 5] },
            fill: { type: 'solid', opacity: [0.12, 0] },
            dataLabels: { enabled: false },
            markers: { size: current.length <= 31 ? 3 : 0, strokeWidth: 0 },
            grid: { borderColor: cssVar('--border-color', '#dde5f1'), strokeDashArray: 3 },
            legend: { position: 'top', horizontalAlign: 'right' },
            xaxis: { tickAmount: Math.min(current.length, 10), labels: { rotate: 0, hideOverlappingLabels: true }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function (v) { return isMoney ? money(v) : num(v); } } },
            tooltip: {
                theme: isDark() ? 'dark' : 'light',
                shared: true,
                x: {
                    formatter: function (value, opts) {
                        const day = state.series[opts.dataPointIndex];
                        const before = state.previousSeries[opts.dataPointIndex];
                        return (day ? longDay(day.day) : value) + (before ? ' vs ' + longDay(before.day) : '');
                    }
                },
                y: { formatter: function (v) { return isMoney ? money(v) : num(v); } }
            }
        };
        if (!current.some(function (p) { return p.y > 0; }) && !previous.some(function (p) { return p.y > 0; })) {
            if (chart) {
                chart.destroy();
                chart = null;
            }
            el.innerHTML = message('No visits in this period yet.');
            return;
        }
        if (chart) {
            chart.updateOptions(options, true, true);
            return;
        }
        el.innerHTML = '';
        chart = new ApexCharts(el, options);
        chart.render();
    }

    function loadOverview(loadId) {
        loading(document.getElementById('tr-chart'));
        if (chart) {
            chart.destroy();
            chart = null;
        }
        return post('overview').then(function (data) {
            if (loadId !== state.loadId) {
                return;
            }
            state.timezone = data.timezone || state.timezone;
            state.currency = data.currency_code || null;
            state.from = data.from;
            state.to = data.to;
            state.series = data.series || [];
            updatePicker();
            renderKpis(data);
            renderMeasures();
            if (IS_MART) {
                renderFunnel(data.funnel);
            }
            // the period before, day by day, for the dashed line
            return post('overview', { from: data.previous.from, to: data.previous.to }).then(function (before) {
                if (loadId !== state.loadId) {
                    return;
                }
                state.previousSeries = before.series || [];
                renderChart();
            }, function () {
                state.previousSeries = [];
                renderChart();
            });
        }).catch(function (error) {
            if (loadId !== state.loadId) {
                return;
            }
            root.querySelectorAll('.fx-kpi-value').forEach(function (el) {
                el.textContent = '–';
            });
            document.getElementById('tr-period-note').textContent = error.message;
            failed(document.getElementById('tr-chart'), error);
            if (IS_MART) {
                failed(document.getElementById('tr-funnel'), error);
            }
        });
    }

    function renderMeasures() {
        // revenue mixes currencies across businesses: only for one business
        const revenue = root.querySelector('[data-tr-measure="revenue"]');
        if (revenue) {
            revenue.classList.toggle('d-none', !state.currency);
            if (!state.currency && state.measure === 'revenue') {
                setMeasure('orders');
            }
        }
    }

    function setMeasure(measure) {
        state.measure = measure;
        root.querySelectorAll('[data-tr-measure]').forEach(function (button) {
            button.classList.toggle('active', button.dataset.trMeasure === measure);
        });
    }

    // ---- marketplace: funnel and businesses ---------------------------------------------------------------------

    function renderFunnel(funnel) {
        const el = document.getElementById('tr-funnel');
        const summary = document.getElementById('tr-funnel-summary');
        if (!funnel || !funnel.steps || funnel.steps[0].visits === 0) {
            summary.textContent = '';
            el.innerHTML = message('No shopping in this period yet.', 'bi-cart');
            return;
        }
        const top = funnel.steps[0].visits;
        summary.innerHTML = 'Conversion <strong>' + pct(funnel.conversion_rate, 2) + '</strong>'
            + (state.currency ? ' · Revenue <strong>' + esc(money(funnel.revenue)) + '</strong>' : ' · ' + num(funnel.orders) + ' orders');
        el.innerHTML = '<div class="tr-funnel">' + funnel.steps.map(function (step) {
            const width = top > 0 ? Math.max(step.visits > 0 ? 1.5 : 0, (step.visits / top) * 100) : 0;
            const rate = step.rate_from_previous === null ? ''
                : '<span class="tr-funnel-rate' + (step.rate_from_previous < 0.25 ? ' text-danger' : '') + '" title="Of the step before">' + pct(step.rate_from_previous, 0) + '</span>';
            return '<div class="tr-funnel-step">'
                + '<div class="tr-funnel-label">' + esc(STEPS[step.step] || step.step) + '</div>'
                + '<div class="tr-funnel-track"><div class="tr-funnel-bar" style="width:' + width.toFixed(2) + '%"></div></div>'
                + '<div class="tr-funnel-count">' + num(step.visits) + rate + '</div>'
                + '</div>';
        }).join('') + '</div>';
    }

    function loadBusinesses(loadId) {
        const col = document.getElementById('tr-businesses-col');
        if (!col) {
            return Promise.resolve();
        }
        col.classList.toggle('d-none', !!state.business);
        if (state.business) {
            return Promise.resolve();
        }
        const el = document.getElementById('tr-businesses');
        loading(el);
        return post('businesses', { site: undefined, limit: 10 }).then(function (data) {
            if (loadId !== state.loadId) {
                return;
            }
            const rows = data.businesses || [];
            if (rows.length === 0) {
                el.innerHTML = message('No business has had visits in this period yet.', 'bi-shop');
                return;
            }
            el.innerHTML = '<div class="table-responsive"><table class="table table-hover align-middle mb-0 tr-table"><thead><tr>'
                + '<th>Business</th><th class="text-end">Visits</th><th class="text-end">Orders</th><th class="text-end">Conversion</th></tr></thead><tbody>'
                + rows.map(function (row) {
                    return '<tr class="tr-clickable" data-tr-business="' + esc(row.business_slug) + '">'
                        + '<td><div class="fw-semibold">' + esc(row.business_name) + '</div><div class="tr-note">' + esc(row.business_slug) + '</div></td>'
                        + '<td class="text-end">' + num(row.visits) + '</td>'
                        + '<td class="text-end">' + num(row.orders) + '</td>'
                        + '<td class="text-end">' + pct(row.conversion_rate) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }).catch(function (error) {
            if (loadId === state.loadId) {
                failed(el, error);
            }
        });
    }

    // ---- breakdowns ---------------------------------------------------------------------------------------------

    function listTable(rows, columns) {
        const max = rows.reduce(function (m, row) {
            return Math.max(m, row[columns.bar] || 0);
        }, 0);
        return '<div class="table-responsive"><table class="table align-middle mb-0 tr-table"><thead><tr>'
            + columns.head.map(function (h, i) {
                return '<th' + (i > 0 ? ' class="text-end"' : '') + '>' + h + '</th>';
            }).join('') + '</tr></thead><tbody>'
            + rows.map(function (row) {
                const share = max > 0 ? ((row[columns.bar] || 0) / max) * 100 : 0;
                return '<tr><td class="tr-bar-cell"><div class="tr-row-bar" style="width:' + share.toFixed(1) + '%"></div><div class="tr-row-label">' + columns.label(row) + '</div></td>'
                    + columns.cells(row).map(function (cell) {
                        return '<td class="text-end text-nowrap">' + cell + '</td>';
                    }).join('') + '</tr>';
            }).join('') + '</tbody></table></div>';
    }

    function loadBreakdown(dimension, el, render, loadId, limit) {
        loading(el);
        return post('breakdown', { dimension: dimension, limit: limit || 10 }).then(function (data) {
            if (loadId !== state.loadId) {
                return;
            }
            const rows = data.rows || [];
            el.innerHTML = rows.length === 0 ? message('No visits in this period yet.') : render(rows);
            if (rows.length > 0 && dimension === 'device') {
                drawDevices(rows);
            }
        }).catch(function (error) {
            if (loadId === state.loadId) {
                failed(el, error);
            }
        });
    }

    function renderPages(rows) {
        return listTable(rows, {
            head: ['Page', 'Views', 'Avg. Time', 'Bounce'],
            bar: 'pageviews',
            label: function (row) {
                const interest = !IS_MART && INTEREST.test(row.value) ? ' <span class="badge badge-soft-warning">interest</span>' : '';
                return '<span class="tr-path" title="' + esc(row.value) + '">' + esc(row.value) + '</span>' + interest;
            },
            cells: function (row) {
                return [num(row.pageviews), duration(row.avg_time_on_page_s), pct(row.bounce_rate, 0)];
            }
        });
    }

    function renderSources(rows) {
        const total = rows.reduce(function (sum, row) {
            return sum + row.visits;
        }, 0);
        const isSite = state.sourceMode === 'referrer';
        return listTable(rows, {
            head: [isSite ? 'Site' : 'Source', 'Visits', 'Share', IS_MART ? 'Orders' : 'Bounce'],
            bar: 'visits',
            label: function (row) {
                return isSite
                    ? '<a href="https://' + esc(row.value) + '" target="_blank" rel="noopener noreferrer">' + esc(row.value) + '</a>'
                    : esc(SOURCES[row.value] || row.value);
            },
            cells: function (row) {
                return [num(row.visits), pct(total > 0 ? row.visits / total : 0, 0), IS_MART ? num(row.orders) : pct(row.bounce_rate, 0)];
            }
        });
    }

    function renderCountries(rows) {
        return listTable(rows, {
            head: ['Country', 'Visits', 'Pageviews', 'Avg. Visit'],
            bar: 'visits',
            label: function (row) {
                return flag(row.value) + esc(country(row.value));
            },
            cells: function (row) {
                return [num(row.visits), num(row.pageviews), duration(row.avg_visit_s)];
            }
        });
    }

    let deviceChart = null;

    function renderDevices(rows) {
        const total = rows.reduce(function (sum, row) {
            return sum + row.visits;
        }, 0);
        return '<div class="tr-devices"><div id="tr-device-chart"></div><ul class="tr-device-list">'
            + rows.map(function (row) {
                return '<li><i class="bi ' + (DEVICE_ICONS[row.value] || 'bi-question-circle') + '"></i><span>' + esc(DEVICES[row.value] || row.value) + '</span>'
                    + '<strong>' + pct(total > 0 ? row.visits / total : 0, 0) + '</strong><span class="tr-note">' + num(row.visits) + '</span></li>';
            }).join('') + '</ul></div>';
    }

    function drawDevices(rows) {
        if (deviceChart) {
            deviceChart.destroy();
        }
        const el = document.getElementById('tr-device-chart');
        if (!el) {
            return;
        }
        deviceChart = new ApexCharts(el, {
            chart: { type: 'donut', height: 190, fontFamily: 'inherit' },
            series: rows.map(function (row) { return row.visits; }),
            labels: rows.map(function (row) { return DEVICES[row.value] || row.value; }),
            colors: [COLOR, '#3b82a0', '#9aa9b9', '#6b5b95'],
            legend: { show: false },
            dataLabels: { enabled: false },
            stroke: { width: 2, colors: [cssVar('--card-bg', '#ffffff')] },
            plotOptions: { pie: { donut: { size: '68%' } } },
            tooltip: { theme: isDark() ? 'dark' : 'light', y: { formatter: function (v) { return num(v) + ' visits'; } } }
        });
        deviceChart.render();
    }

    function loadSources(loadId) {
        return loadBreakdown(state.sourceMode, document.getElementById('tr-sources'), renderSources, loadId, 10);
    }

    // ---- visits -------------------------------------------------------------------------------------------------

    function cameFrom(visit) {
        let text = visit.referrer_host
            ? esc(visit.referrer_host)
            : esc(SOURCES[visit.source_type] || visit.source_type || 'Direct');
        if (visit.utm_campaign || visit.utm_source) {
            text += '<div class="tr-note">' + esc([visit.utm_source, visit.utm_medium, visit.utm_campaign].filter(Boolean).join(' / ')) + '</div>';
        }
        return text;
    }

    function trail(visit) {
        const pages = visit.pages.length > 0 ? visit.pages : [{ path: visit.entry_path }];
        const shown = pages.slice(0, 4).map(function (page) {
            const hot = !IS_MART && INTEREST.test(page.path);
            return '<span class="tr-crumb' + (hot ? ' tr-crumb-hot' : '') + '" title="' + esc(page.path) + '">' + esc(page.path) + '</span>';
        });
        if (pages.length > 4) {
            shown.push('<span class="tr-note">+' + (pages.length - 4) + ' more</span>');
        }
        return '<div class="tr-trail">' + shown.join('<i class="bi bi-chevron-right"></i>') + '</div>';
    }

    function badges(visit) {
        const out = [];
        if (visit.is_returning) {
            out.push('<span class="badge badge-soft-info">returning</span>');
        }
        if (visit.is_bounce) {
            out.push('<span class="badge badge-soft-secondary">bounced</span>');
        }
        if (IS_MART && visit.funnel_stage > 0) {
            const ordered = visit.funnel_stage >= 4;
            out.push('<span class="badge ' + (ordered ? 'badge-soft-success' : 'badge-soft-warning') + '">' + esc(STAGES[visit.funnel_stage])
                + (ordered && visit.order_value !== null ? ' · ' + esc(visit.order_currency || '') + ' ' + num(visit.order_value) : '') + '</span>');
        }
        if (!IS_MART && visit.pages.some(function (page) { return INTEREST.test(page.path); })) {
            out.push('<span class="badge badge-soft-warning">interest</span>');
        }
        return out.join(' ');
    }

    function visitRow(visit, index) {
        const when = localTime(visit.started_at);
        const place = visit.city ? esc(visit.city) + '<div class="tr-note">' + esc(country(visit.country_code)) + '</div>' : esc(country(visit.country_code));
        const detailId = 'tr-visit-' + index;
        const details = [
            ['Pages', visit.pages.map(function (page) {
                return '<div>' + esc(page.path) + ' <span class="tr-note">· ' + page.views + '× · ' + duration(page.visible_s) + '</span></div>';
            }).join('') || '–'],
            ['Device', esc([DEVICES[visit.device_type] || visit.device_type, visit.browser, visit.os].filter(Boolean).join(' · ') || '–')],
            ['Language', esc(visit.language || '–')],
            ['IP address', esc(visit.ip_address || '–')],
            ['Ended', esc(localTime(visit.ended_at).day + ' ' + localTime(visit.ended_at).time)]
        ];
        return '<tr class="tr-clickable" data-bs-toggle="collapse" data-bs-target="#' + detailId + '" aria-expanded="false">'
            + '<td class="text-nowrap"><div class="fw-semibold">' + when.time + '</div><div class="tr-note">' + when.day + '</div></td>'
            + (IS_MART ? '<td>' + esc(visit.business_slug || 'Marketplace') + '</td>' : '')
            + '<td>' + cameFrom(visit) + '</td>'
            + '<td>' + trail(visit) + '<div class="mt-1">' + badges(visit) + '</div></td>'
            + '<td class="text-nowrap">' + flag(visit.country_code) + '<i class="bi ' + (DEVICE_ICONS[visit.device_type] || 'bi-question-circle') + ' me-2 text-muted"></i></td>'
            + '<td class="text-end text-nowrap">' + duration(visit.duration_s) + '<div class="tr-note">' + visit.pageviews + ' page' + (visit.pageviews === 1 ? '' : 's') + '</div></td>'
            + '<td class="text-end"><i class="bi bi-chevron-down tr-chevron"></i></td></tr>'
            + '<tr class="collapse tr-detail" id="' + detailId + '"><td colspan="' + (IS_MART ? 7 : 6) + '"><dl class="tr-detail-grid">'
            + '<dt>Where</dt><dd>' + flag(visit.country_code) + place + '</dd>'
            + details.map(function (item) {
                return '<dt>' + item[0] + '</dt><dd>' + item[1] + '</dd>';
            }).join('') + '</dl></td></tr>';
    }

    function loadVisits(loadId) {
        const body = document.getElementById('tr-visits');
        const columns = IS_MART ? 7 : 6;
        body.innerHTML = '<tr><td colspan="' + columns + '"><div class="tr-loading"><span class="spinner-border spinner-border-sm"></span></div></td></tr>';
        return post('visits', { start: state.visitsStart, length: PAGE_SIZE }).then(function (data) {
            if (loadId !== state.loadId) {
                return;
            }
            state.visitsTotal = data.records_total || 0;
            state.timezone = data.timezone || state.timezone;
            const rows = data.rows || [];
            body.innerHTML = rows.length === 0
                ? '<tr><td colspan="' + columns + '">' + message('No visits in this period yet.') + '</td></tr>'
                : rows.map(visitRow).join('');
            const first = state.visitsTotal === 0 ? 0 : state.visitsStart + 1;
            const last = Math.min(state.visitsStart + PAGE_SIZE, state.visitsTotal);
            document.getElementById('tr-visits-count').textContent = state.visitsTotal === 0 ? '' : first + '–' + last + ' of ' + num(state.visitsTotal);
            document.getElementById('tr-visits-note').textContent = 'Times in ' + state.timezone.replace('_', ' ') + ' time';
            document.getElementById('tr-visits-prev').disabled = state.visitsStart === 0;
            document.getElementById('tr-visits-next').disabled = last >= state.visitsTotal;
        }).catch(function (error) {
            if (loadId === state.loadId) {
                body.innerHTML = '<tr><td colspan="' + columns + '">' + message(error.message, 'bi-exclamation-triangle') + '</td></tr>';
            }
        });
    }

    // ---- filters ------------------------------------------------------------------------------------------------

    let picker = null;

    function updatePicker() {
        if (picker && state.from && state.to) {
            picker.setDate([state.from, state.to], false);
        }
        root.querySelectorAll('[data-tr-days]').forEach(function (button) {
            const days = Number(button.dataset.trDays);
            button.classList.toggle('active', state.to === today(0) && state.from === today(-(days - 1)));
        });
    }

    function initFilters() {
        if (window.flatpickr) {
            picker = flatpickr(document.getElementById('tr-range'), {
                mode: 'range',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j M Y',
                maxDate: 'today',
                onClose: function (dates, text, instance) {
                    if (dates.length === 2) {
                        state.from = instance.formatDate(dates[0], 'Y-m-d');
                        state.to = instance.formatDate(dates[1], 'Y-m-d');
                        reload();
                    } else {
                        updatePicker();
                    }
                }
            });
        }
        root.querySelectorAll('[data-tr-days]').forEach(function (button) {
            button.addEventListener('click', function () {
                const days = Number(button.dataset.trDays);
                state.to = today(0);
                state.from = today(-(days - 1));
                reload();
            });
        });
        root.querySelectorAll('[data-tr-measure]').forEach(function (button) {
            button.addEventListener('click', function () {
                setMeasure(button.dataset.trMeasure);
                renderChart();
            });
        });
        root.querySelectorAll('[data-tr-source]').forEach(function (button) {
            button.addEventListener('click', function () {
                state.sourceMode = button.dataset.trSource;
                root.querySelectorAll('[data-tr-source]').forEach(function (other) {
                    other.classList.toggle('active', other === button);
                });
                loadSources(state.loadId);
            });
        });
        document.getElementById('tr-visits-prev').addEventListener('click', function () {
            state.visitsStart = Math.max(0, state.visitsStart - PAGE_SIZE);
            loadVisits(state.loadId);
        });
        document.getElementById('tr-visits-next').addEventListener('click', function () {
            state.visitsStart += PAGE_SIZE;
            loadVisits(state.loadId);
        });
        root.addEventListener('click', function (event) {
            const row = event.target.closest('[data-tr-business]');
            if (row) {
                setBusiness(row.dataset.trBusiness);
            }
        });
        if (IS_MART) {
            initBusinessSelect();
        }
    }

    let businessSelect = null;

    function initBusinessSelect() {
        const select = document.getElementById('tr-business');
        if (window.Choices) {
            businessSelect = new Choices(select, { searchPlaceholderValue: 'Find a business', itemSelectText: '', shouldSort: false, allowHTML: false });
        }
        select.addEventListener('change', function () {
            setBusiness(select.value, true);
        });
        // the businesses with visits in the last year, busiest first
        post('businesses', { site: undefined, from: today(-365), to: today(0), limit: 100 }).then(function (data) {
            const choices = [{ value: '', label: 'All businesses' }].concat((data.businesses || []).map(function (row) {
                return { value: row.business_slug, label: row.business_name + ' (' + row.business_slug + ')' };
            }));
            if (state.business && !choices.some(function (c) { return c.value === state.business; })) {
                choices.push({ value: state.business, label: state.business });
            }
            choices.forEach(function (choice) {
                choice.selected = choice.value === state.business;
            });
            if (businessSelect) {
                businessSelect.setChoices(choices, 'value', 'label', true);
            } else {
                select.innerHTML = choices.map(function (c) {
                    return '<option value="' + esc(c.value) + '"' + (c.selected ? ' selected' : '') + '>' + esc(c.label) + '</option>';
                }).join('');
            }
        }).catch(function () {
            // the dropdown keeps "All businesses"; the rest of the page still works
        });
    }

    function setBusiness(slug, fromSelect) {
        if (slug === state.business) {
            return;
        }
        state.business = slug;
        if (!fromSelect && businessSelect) {
            businessSelect.setChoiceByValue(slug);
        }
        reload();
    }

    // ---- loading everything -------------------------------------------------------------------------------------

    function reload() {
        state.loadId += 1;
        state.visitsStart = 0;
        const loadId = state.loadId;
        syncAddress();
        root.querySelectorAll('.fx-kpi-value').forEach(function (el) {
            el.innerHTML = '<span class="tr-skeleton"></span>';
        });
        root.querySelectorAll('.fx-kpi-trend').forEach(function (el) {
            el.innerHTML = '&nbsp;';
            el.classList.remove('positive', 'negative');
        });
        if (IS_MART) {
            loading(document.getElementById('tr-funnel'));
        }
        loadOverview(loadId);
        loadBusinesses(loadId);
        loadBreakdown('path', document.getElementById('tr-pages'), renderPages, loadId, 10);
        loadSources(loadId);
        loadBreakdown('country', document.getElementById('tr-countries'), renderCountries, loadId, 10);
        loadBreakdown('device', document.getElementById('tr-devices'), renderDevices, loadId, 5);
        loadVisits(loadId);
    }

    // the chart and donut follow the light/dark theme
    new MutationObserver(function () {
        if (chart) {
            chart.destroy();
            chart = null;
            renderChart();
        }
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    initFilters();
    reload();
})();
