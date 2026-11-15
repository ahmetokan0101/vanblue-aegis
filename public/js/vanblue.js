/* ==========================================================================
   VanBlue AegisHealth OS — client engine
   ========================================================================== */
(() => {
    'use strict';

    const BOOT = window.VB_BOOT || { crew: {}, mission: {} };
    const $ = (id) => document.getElementById(id);
    const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const C = { green: '#00FF9D', cyan: '#00E5FF', gold: '#FBBF24', red: '#FF0055', violet: '#A78BFA', muted: '#64748B', grid: '#131A29', border: '#1A2235' };
    const PANELS = ['overview', 'crew', 'radiation', 'triage', 'detective', 'assistant', 'conditions', 'countermeasures', 'pharmacy', 'data', 'report', 'story'];

    const state = {
        member: 'astronot1',
        mission: BOOT.mission,
        emergency: !!BOOT.mission.emergency,
        shelterRemaining: BOOT.mission.shelter_seconds_remaining,
        telemetry: null,
        triage: null,
        lastStatus: null,
        crewSnapshot: [],
        hrHistory: [],
        spo2History: [],
        sparks: {},
        lastEventId: null,
        cognitiveOverride: {},
        countermeasureCompliance: 0.6,
        spaceWeather: null,
        currentPanel: null,
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const fmtDelay = (seconds) => {
        const s = Math.max(0, Math.round(seconds || 0));
        return s >= 60 ? `${Math.floor(s / 60)} dk ${String(s % 60).padStart(2, '0')} sn` : `${s} sn`;
    };
    const fmtNumber = (value, decimals = 0) => Number(value).toLocaleString('tr-TR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    const statusMeta = (status) => ({
        CRITICAL: { label: 'KRİTİK', chip: 'chip-red', color: C.red },
        CAUTION: { label: 'DİKKAT', chip: 'chip-gold', color: C.gold },
        NOMINAL: { label: 'NOMİNAL', chip: 'chip-green', color: C.green },
    }[status] || { label: status, chip: 'chip-green', color: C.green });
    const setChip = (el, chipClass, html) => {
        if (!el) return;
        el.className = el.className.replace(/chip-(green|cyan|gold|red|violet)/g, '').trim() + ' ' + chipClass;
        el.innerHTML = html;
    };
    const setText = (id, value) => { const el = $(id); if (el) el.textContent = value; };

    function animateNumber(el, to, decimals = 0, duration = 900) {
        if (!el) return;
        const from = parseFloat(el.dataset.value ?? el.textContent.replace(/[^\d.,-]/g, '').replace(',', '.')) || 0;
        el.dataset.value = to;
        const start = performance.now();
        const step = (now) => {
            const p = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = fmtNumber(from + (to - from) * eased, decimals);
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    async function api(path, options = {}) {
        const response = await fetch(path, {
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            ...options,
        });
        if (!response.ok) throw new Error(`${path} → ${response.status}`);
        return response.json();
    }

    function setLinkStatus(isOnline) {
        const chip = $('link-chip');
        if (!chip) return;
        setChip(chip, isOnline ? 'chip-green' : 'chip-gold', isOnline ? '<span class="dot dot-live"></span>CANLI' : '<span class="dot"></span>YEREL MOD');
    }

    /* ---------------------------------------------------------------- Toasts */
    const toast = (title, message = '', tone = 'green') => {
        const colors = { green: C.green, cyan: C.cyan, gold: C.gold, red: C.red, violet: C.violet };
        const el = document.createElement('div');
        el.className = 'toast';
        el.style.borderLeftColor = colors[tone] || C.green;
        el.innerHTML = `<div class="font-bold text-white text-[13px]">${escapeHtml(title)}</div>${message ? `<div class="text-slate-400 mt-0.5">${escapeHtml(message)}</div>` : ''}`;
        $('toasts').appendChild(el);
        setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 400); }, 4800);
    };

    /* ---------------------------------------------------------------- Voice */
    const voice = {
        enabled: true,
        speak(text, { onstart, onend } = {}) {
            if (!this.enabled || !('speechSynthesis' in window)) { onend?.(); return; }
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'tr-TR';
            utterance.rate = 1.05;
            const trVoice = window.speechSynthesis.getVoices().find((v) => v.lang?.toLowerCase().startsWith('tr'));
            if (trVoice) utterance.voice = trVoice;
            if (onstart) utterance.onstart = onstart;
            if (onend) { utterance.onend = onend; utterance.onerror = onend; }
            window.speechSynthesis.speak(utterance);
        },
        toggle() {
            this.enabled = !this.enabled;
            $('voice-on').classList.toggle('hidden', !this.enabled);
            $('voice-off').classList.toggle('hidden', this.enabled);
            if (!this.enabled && 'speechSynthesis' in window) window.speechSynthesis.cancel();
            toast(this.enabled ? 'Sesli asistan açık' : 'Sesli asistan kapalı', '', 'cyan');
        },
    };

    /* ---------------------------------------------------------------- Starfield */
    const starfield = (() => {
        const canvas = $('starfield');
        const ctx = canvas.getContext('2d');
        let stars = [];
        let warp = 0;
        const resize = () => {
            canvas.width = innerWidth * devicePixelRatio;
            canvas.height = innerHeight * devicePixelRatio;
            stars = Array.from({ length: Math.min(380, Math.round(innerWidth / 4)) }, () => ({
                x: Math.random() * canvas.width - canvas.width / 2,
                y: Math.random() * canvas.height - canvas.height / 2,
                z: Math.random() * canvas.width,
            }));
        };
        const frame = () => {
            const w = canvas.width, h = canvas.height;
            ctx.fillStyle = `rgba(5,7,13,${warp > 0.5 ? 0.35 : 1})`;
            ctx.fillRect(0, 0, w, h);
            const speed = 0.6 + warp * 28;
            const tint = state.emergency ? '255,90,130' : '200,235,255';
            for (const s of stars) {
                s.z -= speed;
                if (s.z <= 1) { s.z = w; s.x = Math.random() * w - w / 2; s.y = Math.random() * h - h / 2; }
                const k = 128 / s.z * devicePixelRatio * 2.2;
                const px = s.x * k / devicePixelRatio + w / 2, py = s.y * k / devicePixelRatio + h / 2;
                if (px < 0 || px > w || py < 0 || py > h) continue;
                const size = Math.max(0.3, (1 - s.z / w) * 2.2 * devicePixelRatio);
                ctx.fillStyle = `rgba(${tint},${Math.min(1, 1.2 - s.z / w)})`;
                if (warp > 0.08) {
                    const k2 = 128 / (s.z + speed * 6) * devicePixelRatio * 2.2;
                    ctx.strokeStyle = ctx.fillStyle;
                    ctx.lineWidth = size;
                    ctx.beginPath();
                    ctx.moveTo(px, py);
                    ctx.lineTo(s.x * k2 / devicePixelRatio + w / 2, s.y * k2 / devicePixelRatio + h / 2);
                    ctx.stroke();
                } else {
                    ctx.fillRect(px, py, size, size);
                }
            }
            warp *= 0.93;
            requestAnimationFrame(frame);
        };
        addEventListener('resize', resize);
        resize();
        requestAnimationFrame(frame);
        return { boost: (amount = 0.6) => { warp = Math.max(warp, amount); } };
    })();

    /* ---------------------------------------------------------------- Boot */
    const boot = {
        done: false,
        run() {
            let skip = false;
            try { skip = sessionStorage.getItem('vb-booted') === '1'; } catch (e) {}
            if (skip) return this.finish();
            const lines = [
                ['Biyosensör veri yolu', 'OK'],
                ['NASA-STD-3001 eşik tablosu', 'YÜKLENDİ'],
                ['MSL RAD dozimetri modeli', 'YÜKLENDİ'],
                ['NASA OSDR veri bağlantısı', 'HAZIR'],
                ['Aegis otonom triage motoru', 'AKTİF'],
                ['Mürettebat telemetrisi (4/4)', 'CANLI'],
            ];
            lines.forEach(([label, status], i) => {
                setTimeout(() => {
                    const row = document.createElement('div');
                    row.className = 'boot-line flex justify-between';
                    row.innerHTML = `<span>› ${label}</span><span class="text-cyberGreen">${status}</span>`;
                    $('boot-log').appendChild(row);
                    $('boot-progress').style.width = `${((i + 1) / lines.length) * 100}%`;
                    if (i === lines.length - 1) setTimeout(() => this.finish(), 450);
                }, 260 * (i + 1));
            });
        },
        finish() {
            if (this.done) return;
            this.done = true;
            try { sessionStorage.setItem('vb-booted', '1'); } catch (e) {}
            $('boot').classList.add('done');
            starfield.boost(1);
        },
    };

    /* ---------------------------------------------------------------- Charts */
    const charts = {};
    const axisStyle = {
        axisLine: { lineStyle: { color: C.border } },
        axisLabel: { color: C.muted, fontSize: 10, fontFamily: 'JetBrains Mono' },
        splitLine: { lineStyle: { color: C.grid } },
    };
    const tooltip = { trigger: 'axis', backgroundColor: 'rgba(10,14,24,0.95)', borderColor: '#26334D', textStyle: { color: '#E2E8F0', fontSize: 11, fontFamily: 'JetBrains Mono' } };
    const gradient = (color, alpha = 0.3) => new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: hexA(color, alpha) }, { offset: 1, color: hexA(color, 0) }]);
    function hexA(hex, alpha) {
        const n = parseInt(hex.slice(1), 16);
        return `rgba(${n >> 16},${(n >> 8) & 255},${n & 255},${alpha})`;
    }
    function chart(id) {
        if (!window.echarts) return null;
        const el = $(id);
        if (!el || el.offsetWidth === 0) return charts[id] || null;
        if (!charts[id]) charts[id] = echarts.init(el, null, { renderer: 'canvas' });
        return charts[id];
    }
    addEventListener('resize', () => Object.values(charts).forEach((c) => c.resize()));

    const chartBuilders = {
        radar() {
            const c = chart('chart-radar');
            if (!c) return;
            const values = state.telemetry?.radar || BOOT.crew[state.member].radar;
            const color = state.emergency ? C.red : C.green;
            c.setOption({
                radar: {
                    indicator: ['Kardiyo', 'Oksijen', 'Radyasyon', 'Kemik/Kas', 'Bilişsel'].map((name) => ({ name, max: 100 })),
                    radius: '58%', center: ['50%', '55%'], splitNumber: 4, shape: 'polygon',
                    axisName: { color: '#94A3B8', fontSize: 10, fontFamily: 'JetBrains Mono' },
                    splitLine: { lineStyle: { color: C.border } }, splitArea: { show: false }, axisLine: { lineStyle: { color: C.border } },
                },
                series: [{
                    type: 'radar', symbolSize: 5,
                    data: [{ value: values, name: BOOT.crew[state.member].name }],
                    itemStyle: { color }, lineStyle: { width: 2, color, shadowColor: color, shadowBlur: 10 },
                    areaStyle: { color: hexA(color, 0.18) },
                }],
                animationDurationUpdate: 800,
            });
        },
        trend() {
            const c = chart('chart-trend');
            if (!c) return;
            const labels = state.hrHistory.map((_, i) => i);
            c.setOption({
                grid: { top: 18, right: 36, bottom: 18, left: 32 },
                tooltip,
                legend: { show: false },
                xAxis: { type: 'category', data: labels, show: false, boundaryGap: false },
                yAxis: [
                    { type: 'value', min: 50, max: 140, ...axisStyle, axisLabel: { ...axisStyle.axisLabel, color: C.green } },
                    { type: 'value', min: 85, max: 100, ...axisStyle, splitLine: { show: false }, axisLabel: { ...axisStyle.axisLabel, color: C.cyan } },
                ],
                series: [
                    { name: 'Nabız', type: 'line', data: state.hrHistory, smooth: true, symbol: 'none', lineStyle: { color: state.emergency ? C.red : C.green, width: 2 }, areaStyle: { color: gradient(state.emergency ? C.red : C.green, 0.25) } },
                    { name: 'SpO₂', type: 'line', yAxisIndex: 1, data: state.spo2History, smooth: true, symbol: 'none', lineStyle: { color: C.cyan, width: 1.5, type: 'dashed' } },
                ],
                animation: false,
            });
        },
        gauge() {
            const c = chart('chart-gauge');
            if (!c) return;
            const value = state.telemetry?.radiation_msv ?? BOOT.crew[state.member].daily_dose_msv;
            const color = value >= 5 ? C.red : value >= 3 ? C.gold : C.green;
            c.setOption({
                series: [{
                    type: 'gauge', startAngle: 200, endAngle: -20, min: 0, max: 10, splitNumber: 5, radius: '95%', center: ['50%', '58%'],
                    progress: { show: true, roundCap: true, width: 14, itemStyle: { color, shadowColor: color, shadowBlur: 14 } },
                    axisLine: { roundCap: true, lineStyle: { width: 14, color: [[0.3, 'rgba(0,255,157,0.12)'], [0.5, 'rgba(251,191,36,0.15)'], [1, 'rgba(255,0,85,0.15)']] } },
                    pointer: { show: false },
                    axisTick: { show: false },
                    splitLine: { distance: -26, length: 6, lineStyle: { width: 2, color: '#334155' } },
                    axisLabel: { distance: -12, color: C.muted, fontSize: 10, fontFamily: 'JetBrains Mono' },
                    anchor: { show: false },
                    title: { offsetCenter: [0, '30%'], color: C.muted, fontSize: 11, fontFamily: 'JetBrains Mono' },
                    detail: { valueAnimation: true, offsetCenter: [0, '-2%'], fontSize: 34, fontWeight: 700, fontFamily: 'Space Grotesk', color, formatter: (v) => v.toFixed(2) },
                    data: [{ value, name: 'mSv / gün' }],
                }],
            });
        },
        doseProjection() {
            const c = chart('chart-dose-projection');
            if (!c) return;
            const crew = Object.values(BOOT.crew);
            const avgRate = crew.reduce((sum, m) => sum + m.daily_dose_msv, 0) / crew.length;
            const msl = state.mission.msl_gcr_daily_msv || 1.84;
            const days = [], shielded = [], unshielded = [];
            for (let d = 0; d <= 920; d += 10) {
                const transit = Math.min(d, 210), surface = Math.max(0, Math.min(d - 210, 500)), back = Math.max(0, d - 710);
                days.push(d);
                shielded.push(+(transit * avgRate + surface * 0.64 * 0.7 + back * avgRate).toFixed(1));
                unshielded.push(+(transit * msl + surface * 0.64 + back * msl).toFixed(1));
            }
            c.setOption({
                grid: { top: 30, right: 20, bottom: 30, left: 48 },
                tooltip: { ...tooltip, valueFormatter: (v) => `${v} mSv` },
                legend: { top: 0, right: 0, textStyle: { color: '#94A3B8', fontSize: 11 }, itemWidth: 14 },
                xAxis: { type: 'category', data: days, boundaryGap: false, ...axisStyle, axisLabel: { ...axisStyle.axisLabel, formatter: (v) => `G${v}` } },
                yAxis: { type: 'value', ...axisStyle, name: 'mSv', nameTextStyle: { color: C.muted } },
                series: [
                    {
                        name: 'Korumasız (MSL RAD)', type: 'line', data: unshielded, smooth: true, symbol: 'none', lineStyle: { color: C.red, width: 2, type: 'dashed' },
                        markLine: { symbol: 'none', label: { color: C.gold, fontFamily: 'JetBrains Mono', formatter: 'NASA kariyer limiti 600 mSv' }, lineStyle: { color: C.gold, type: 'solid', width: 1.5 }, data: [{ yAxis: 600 }] },
                    },
                    {
                        name: 'VanBlue korumalı', type: 'line', data: shielded, smooth: true, symbol: 'none', lineStyle: { color: C.green, width: 2.5, shadowColor: C.green, shadowBlur: 10 }, areaStyle: { color: gradient(C.green, 0.2) },
                        markLine: { symbol: 'none', label: { color: C.cyan, fontFamily: 'JetBrains Mono', formatter: 'BUGÜN' }, lineStyle: { color: C.cyan, type: 'dotted' }, data: [{ xAxis: String(Math.round(state.mission.mission_day / 10) * 10) }] },
                        markArea: { silent: true, itemStyle: { color: 'rgba(251,191,36,0.04)' }, data: [[{ xAxis: '210' }, { xAxis: '710' }]] },
                    },
                ],
            });
        },
        flareClasses() {
            const c = chart('chart-flare-classes');
            const sw = state.spaceWeather;
            if (!c || !sw) return;
            const classes = ['A', 'B', 'C', 'M', 'X'];
            const colors = ['#334155', '#64748B', C.cyan, C.gold, C.red];
            c.setOption({
                grid: { top: 10, right: 10, bottom: 24, left: 30 },
                tooltip,
                xAxis: { type: 'category', data: classes.map((k) => `${k} sınıfı`), ...axisStyle },
                yAxis: { type: 'value', minInterval: 1, ...axisStyle },
                series: [{ type: 'bar', barWidth: '55%', data: classes.map((k, i) => ({ value: sw.class_counts[k] || 0, itemStyle: { color: colors[i], borderRadius: [6, 6, 0, 0] } })) }],
            });
        },
        bone() {
            const c = chart('chart-bone');
            if (!c) return;
            const months = Array.from({ length: 31 }, (_, i) => i);
            const rate = 0.015 - 0.013 * state.countermeasureCompliance;
            c.setOption({
                grid: { top: 34, right: 20, bottom: 30, left: 44 },
                tooltip: { ...tooltip, valueFormatter: (v) => `%${v}` },
                legend: { top: 0, right: 0, textStyle: { color: '#94A3B8', fontSize: 11 } },
                xAxis: { type: 'category', data: months.map((m) => `${m}. ay`), boundaryGap: false, ...axisStyle },
                yAxis: { type: 'value', min: 60, max: 100, ...axisStyle },
                series: [
                    { name: 'Karşı-tedbirsiz', type: 'line', symbol: 'none', smooth: true, data: months.map((m) => +(100 * Math.pow(1 - 0.015, m)).toFixed(1)), lineStyle: { color: C.red, type: 'dashed', width: 2 } },
                    { name: `Mevcut uyum (%${Math.round(state.countermeasureCompliance * 100)})`, type: 'line', symbol: 'none', smooth: true, data: months.map((m) => +(100 * Math.pow(1 - rate, m)).toFixed(1)), lineStyle: { color: C.cyan, width: 2.5 }, areaStyle: { color: gradient(C.cyan, 0.15) } },
                    {
                        name: 'Tam ARED protokolü', type: 'line', symbol: 'none', smooth: true, data: months.map((m) => +(100 * Math.pow(1 - 0.002, m)).toFixed(1)), lineStyle: { color: C.green, width: 2 },
                        markLine: { symbol: 'none', label: { color: C.gold, formatter: 'Osteopeni riski', fontFamily: 'JetBrains Mono' }, lineStyle: { color: C.gold }, data: [{ yAxis: 85 }] },
                    },
                ],
            });
        },
        decades(stats) {
            const c = chart('chart-decades');
            if (!c || !stats) return;
            c.setOption({
                grid: { top: 16, right: 10, bottom: 28, left: 40 },
                tooltip,
                xAxis: { type: 'category', data: stats.by_decade.map((d) => d.decade), ...axisStyle },
                yAxis: { type: 'value', ...axisStyle },
                series: [{ type: 'bar', data: stats.by_decade.map((d) => d.missions), barWidth: '55%', itemStyle: { borderRadius: [6, 6, 0, 0], color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: C.green }, { offset: 1, color: hexA(C.cyan, 0.25) }]) }, label: { show: true, position: 'top', color: '#94A3B8', fontSize: 10 } }],
            });
        },
        nations(stats) {
            const c = chart('chart-nations');
            if (!c || !stats) return;
            c.setOption({
                tooltip: { trigger: 'item', backgroundColor: 'rgba(10,14,24,0.95)', borderColor: '#26334D', textStyle: { color: '#E2E8F0' } },
                color: [C.cyan, C.green, C.gold, C.violet, '#F472B6', '#60A5FA', '#34D399', '#475569'],
                series: [{
                    type: 'pie', radius: ['48%', '74%'], center: ['50%', '52%'], padAngle: 2, itemStyle: { borderRadius: 6 },
                    label: { color: '#94A3B8', fontSize: 10, formatter: '{b}\n{c}' },
                    data: stats.by_nationality,
                }],
            });
        },
    };

    function refreshPanelCharts(panel) {
        const map = {
            crew: ['radar', 'trend'],
            radiation: ['gauge', 'doseProjection', 'flareClasses'],
            countermeasures: ['bone'],
            data: [],
        };
        (map[panel] || []).forEach((name) => chartBuilders[name]());
        if (panel === 'data' && data.stats) { chartBuilders.decades(data.stats); chartBuilders.nations(data.stats); }
        if (panel === 'conditions') conditions.renderChart();
        if (panel === 'detective' && typeof detective !== 'undefined' && detective.data) detective.render(detective.data);
        Object.values(charts).forEach((c) => c.resize());
    }

    /* ---------------------------------------------------------------- Navigation */
    const nav = {
        go(panel, { silent = false } = {}) {
            if (!PANELS.includes(panel)) panel = 'overview';
            if (state.currentPanel === panel) return;
            state.currentPanel = panel;
            qsa('.panel-view').forEach((el) => el.classList.toggle('active', el.id === `panel-${panel}`));
            qsa('.nav-item').forEach((el) => el.classList.toggle('active', el.dataset.panel === panel));
            document.body.classList.toggle('on-assistant', panel === 'assistant');
            if (panel === 'assistant' && typeof chat !== 'undefined' && chat.isOpen) chat.close();
            const section = $(`panel-${panel}`);
            setText('page-title', section?.dataset.title || '');
            if (!silent) { try { history.replaceState(null, '', `#${panel}`); } catch (e) {} }
            starfield.boost(0.45);
            this.closeMobile();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            requestAnimationFrame(() => refreshPanelCharts(panel));
            setTimeout(() => refreshPanelCharts(panel), 360);
            if (panel === 'data') data.load();
            if (panel === 'conditions') conditions.enter();
            if (panel === 'pharmacy') pharmacy.load();
            if (panel === 'detective') detective.load();
            if (panel === 'report') report.render();
            if (panel === 'triage') triagePanel.animateComparison();
            if (panel === 'crew') ecg.resize();
        },
        toggleCollapse() {
            document.body.classList.toggle('sidebar-collapsed');
            setTimeout(() => Object.values(charts).forEach((c) => c.resize()), 380);
        },
        openMobile() { document.body.classList.add('sidebar-open'); },
        closeMobile() { document.body.classList.remove('sidebar-open'); },
    };

    /* ---------------------------------------------------------------- ECG */
    const ecg = (() => {
        const canvas = $('ecg-canvas');
        const ctx = canvas?.getContext('2d');
        let x = 0, phase = 0, lastY = null, last = performance.now();
        const gauss = (t, mu, sigma, amp) => amp * Math.exp(-((t - mu) ** 2) / (2 * sigma ** 2));
        const waveform = (t) => gauss(t, 0.12, 0.025, 0.12) + gauss(t, 0.215, 0.008, -0.12) + gauss(t, 0.24, 0.011, 1) + gauss(t, 0.265, 0.01, -0.28) + gauss(t, 0.47, 0.045, 0.3);
        const resize = () => {
            if (!canvas || canvas.offsetWidth === 0) return;
            canvas.width = canvas.offsetWidth * devicePixelRatio;
            canvas.height = canvas.offsetHeight * devicePixelRatio;
            ctx.fillStyle = '#03050a';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            x = 0; lastY = null;
        };
        const draw = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000);
            last = now;
            if (canvas && canvas.offsetWidth > 0 && canvas.width > 0) {
                const hr = state.telemetry?.heart_rate || 72;
                const pxPerSec = 110 * devicePixelRatio;
                const steps = Math.max(1, Math.round(pxPerSec * dt));
                const h = canvas.height, color = state.emergency ? C.red : C.green;
                for (let i = 0; i < steps; i++) {
                    phase += (hr / 60) / pxPerSec;
                    if (phase >= 1) phase -= 1;
                    const y = h * 0.62 - waveform(phase) * h * 0.5 + (Math.random() - 0.5) * 1.2 * devicePixelRatio;
                    ctx.fillStyle = '#03050a';
                    ctx.fillRect(x, 0, 14 * devicePixelRatio, h);
                    ctx.strokeStyle = 'rgba(0,229,255,0.06)';
                    ctx.lineWidth = 1;
                    if (Math.round(x) % Math.round(20 * devicePixelRatio) === 0) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, h); ctx.stroke(); }
                    if (lastY !== null) {
                        ctx.strokeStyle = color;
                        ctx.lineWidth = 2 * devicePixelRatio;
                        ctx.shadowColor = color;
                        ctx.shadowBlur = 8 * devicePixelRatio;
                        ctx.beginPath();
                        ctx.moveTo(x - 1, lastY);
                        ctx.lineTo(x, y);
                        ctx.stroke();
                        ctx.shadowBlur = 0;
                    }
                    lastY = y;
                    x += 1;
                    if (x >= canvas.width) { x = 0; lastY = null; }
                }
            }
            requestAnimationFrame(draw);
        };
        addEventListener('resize', resize);
        requestAnimationFrame(draw);
        return { resize: () => setTimeout(resize, 50) };
    })();

    /* ---------------------------------------------------------------- Trajectory */
    const trajectory = {
        render(mission) {
            const path = $('transfer-path');
            if (!path) return;
            const p = Math.min(1, (mission.progress_pct || 0) / 100);
            const length = path.getTotalLength();
            const ship = path.getPointAtLength(length * p);
            const earthAngle = Math.PI + p * (210 / 365) * 2 * Math.PI;
            const marsAngle = -(1 - p) * (210 / 687) * 2 * Math.PI;
            const earth = { x: 300 + 120 * Math.cos(earthAngle), y: 170 - 98 * Math.sin(earthAngle) };
            const mars = { x: 300 + 250 * Math.cos(marsAngle), y: 170 - 150 * Math.sin(marsAngle) };
            $('ship-g').setAttribute('transform', `translate(${ship.x} ${ship.y})`);
            $('earth-g').setAttribute('transform', `translate(${earth.x} ${earth.y})`);
            $('mars-g').setAttribute('transform', `translate(${mars.x} ${mars.y})`);
            const line = $('signal-line');
            line.setAttribute('x1', earth.x); line.setAttribute('y1', earth.y);
            line.setAttribute('x2', ship.x); line.setAttribute('y2', ship.y);
            this.earth = earth; this.ship = ship;
        },
        animateSignal() {
            const pulse = $('signal-pulse');
            const tick = (now) => {
                if (this.earth && this.ship && pulse) {
                    const t = (now % 3000) / 3000;
                    pulse.setAttribute('cx', this.earth.x + (this.ship.x - this.earth.x) * t);
                    pulse.setAttribute('cy', this.earth.y + (this.ship.y - this.earth.y) * t);
                    pulse.setAttribute('opacity', String(1 - t * 0.6));
                }
                requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        },
    };

    /* ---------------------------------------------------------------- Mission */
    let metBase = { seconds: BOOT.mission.met_seconds || 0, at: performance.now() };
    function renderMission(mission) {
        state.mission = mission;
        metBase = { seconds: mission.met_seconds, at: performance.now() };
        const delay = fmtDelay(mission.comm_delay_seconds);
        setText('top-delay', `+${delay}`);
        setText('ov-delay-inline', delay);
        setText('kpi-delay', delay);
        setText('flow-delay', `+${delay}`);
        setText('traj-rtt', fmtDelay(mission.round_trip_seconds));
        setText('ov-day', mission.mission_day);
        setText('ov-days-left', mission.days_to_mars);
        setText('ov-progress-pct', mission.progress_pct);
        setText('traj-chip', `GÜN ${mission.mission_day}`);
        setText('page-eyebrow', `${mission.name} · Gün ${mission.mission_day}`);
        $('ov-progress').style.width = `${mission.progress_pct}%`;
        animateNumber($('kpi-distance'), mission.earth_distance_km / 1e6, 1);
        animateNumber($('traj-mars'), mission.mars_distance_km / 1e6, 1);
        setText('kpi-velocity', mission.velocity_km_s);
        trajectory.render(mission);
        emergency.apply(!!mission.emergency, mission.shelter_seconds_remaining, true);
    }
    setInterval(() => {
        const s = Math.floor(metBase.seconds + (performance.now() - metBase.at) / 1000);
        const day = Math.floor(s / 86400) + 1, rem = s % 86400;
        const hh = String(Math.floor(rem / 3600)).padStart(2, '0'), mm = String(Math.floor((rem % 3600) / 60)).padStart(2, '0'), ss = String(rem % 60).padStart(2, '0');
        setText('met-clock', `GÜN ${day} · ${hh}:${mm}:${ss}`);
        if (state.emergency && state.shelterRemaining != null) {
            state.shelterRemaining = Math.max(0, state.shelterRemaining - 1);
            setText('shelter-countdown', `${String(Math.floor(state.shelterRemaining / 60)).padStart(2, '0')}:${String(state.shelterRemaining % 60).padStart(2, '0')}`);
        }
    }, 1000);

    /* ---------------------------------------------------------------- Selected member telemetry */
    async function pollTelemetry() {
        let payload;
        try {
            payload = await api(`/api/telemetry/live?member=${state.member}`);
            setLinkStatus(true);
        } catch (e) {
            setLinkStatus(false);
            return;
        }
        state.telemetry = payload.telemetry;
        if (state.cognitiveOverride[state.member] != null) state.telemetry.radar[4] = state.cognitiveOverride[state.member];
        state.triage = payload.autonomous_ai;
        state.hrHistory.push(payload.telemetry.heart_rate);
        state.spo2History.push(payload.telemetry.spo2);
        if (state.hrHistory.length > 60) { state.hrHistory.shift(); state.spo2History.shift(); }
        renderCrewPanel(payload);
        triagePanel.render(payload);
        assistant.updateContext(payload);
        chat.updateContext(payload);
        if (state.currentPanel === 'crew') { chartBuilders.trend(); chartBuilders.radar(); }
        if (state.currentPanel === 'radiation') chartBuilders.gauge();
        if (state.currentPanel === 'report') report.render();

        const status = payload.autonomous_ai.status;
        if (state.lastStatus && status !== state.lastStatus && status === 'CRITICAL') {
            voice.speak(`Dikkat! ${payload.member.name} için kritik durum. ${payload.autonomous_ai.recommended_action}`);
            chat.proactive(payload.member.name, payload.autonomous_ai);
        }
        state.lastStatus = status;
        $('nav-alert-badge')?.classList.toggle('hidden', status !== 'CRITICAL');
    }

    function renderCrewPanel({ member, telemetry: t, autonomous_ai: ai }) {
        const meta = statusMeta(ai.status);
        setText('crew-name', member.name);
        setText('crew-role', member.role);
        setText('crew-blood', member.blood_type);
        setText('crew-age', member.age);
        setText('crew-health', ai.health_index);
        $('crew-health').style.color = meta.color;
        setChip($('crew-status-chip'), meta.chip, meta.label);
        setText('m-bp', t.blood_pressure);
        setText('m-temp', t.body_temp_c.toFixed(1));
        setText('m-rr', t.respiratory_rate);
        setText('m-spo2', t.spo2.toFixed(1));
        setText('m-hrv', t.hrv_ms);
        setText('m-hyd', t.hydration_pct);
        setText('m-hr', t.heart_rate);
        $('m-hr').style.color = meta.color;
        setText('m-fatigue', t.fatigue_risk_pct);
        setText('pin-cog', `${t.cognitive_index}/100`);
        setText('pin-hr', t.heart_rate);
        setText('pin-bp', t.blood_pressure);
        setText('pin-dose', t.cumulative_dose_msv);
        setText('pin-rate', t.radiation_msv);
        setText('pin-bone', t.radar[3]);
        setText('cab-p', t.cabin_pressure_kpa);
        setText('cab-co2', t.cabin_co2);
        setText('cab-o2', t.cabin_o2_pct);
        setText('radar-label', `${member.name.toUpperCase()} BİLEŞKE`);
    }

    /* ---------------------------------------------------------------- Triage panel */
    const triagePanel = {
        render({ member, autonomous_ai: ai, timestamp }) {
            const meta = statusMeta(ai.status);
            setText('tr-name', member.name);
            setChip($('tr-status'), meta.chip, `<span class="dot dot-live"></span>${meta.label}`);
            setText('tr-index', ai.health_index);
            setText('tr-risk', `${ai.risk_score} / 100`);
            setText('tr-latency', `${ai.decision_latency_ms} ms`);
            setText('tr-time', `${timestamp} · ${ai.engine}`);
            setText('tr-message', ai.message);
            const ring = $('tr-ring');
            ring.style.strokeDashoffset = String(326.7 * (1 - ai.health_index / 100));
            ring.style.stroke = meta.color;
            setText('traj-latency', `${ai.decision_latency_ms} ms`);
            setText('cmp-earth', `${fmtDelay(ai.earth_delay_seconds * 2 + 300)}`);
            setText('cmp-onboard', `${ai.decision_latency_ms} ms`);
            setText('cmp-saved', fmtDelay(ai.time_saved_seconds + 300));

            const levelStyle = { critical: ['chip-red', 'KRİTİK'], warning: ['chip-gold', 'UYARI'], nominal: ['chip-green', 'NORMAL'] };
            $('tr-findings').innerHTML = ai.findings.map((f) => `
                <div class="flex items-start gap-3 p-3 rounded-xl bg-[#06080E] border border-[#1A2235]">
                    <span class="chip ${levelStyle[f.level][0]} shrink-0">${levelStyle[f.level][1]}</span>
                    <div class="min-w-0"><div class="text-[13px] text-slate-200">${escapeHtml(f.text)}</div><div class="text-[10px] font-mono text-slate-500 mt-0.5">📎 ${escapeHtml(f.citation)}</div></div>
                </div>`).join('');
            $('tr-actions').innerHTML = ai.actions.map((a, i) => `
                <li class="flex gap-3 p-3 rounded-xl ${ai.status === 'CRITICAL' && i === 0 ? 'bg-rose-950/40 border border-rose-500/40' : 'bg-[#06080E] border border-[#1A2235]'}">
                    <span class="w-6 h-6 shrink-0 rounded-lg grid place-items-center text-[11px] font-mono font-bold ${ai.status === 'CRITICAL' && i === 0 ? 'bg-cyberRed text-white' : 'bg-[#142620] text-cyberGreen'}">${i + 1}</span>
                    <span class="text-[13px] text-slate-200">${escapeHtml(a)}</span>
                </li>`).join('');
        },
        animateComparison() {
            const bar = $('cmp-earth-bar');
            if (!bar) return;
            bar.style.transition = 'none';
            bar.style.width = '0';
            requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.transition = ''; bar.style.width = '100%'; }));
        },
    };

    /* ---------------------------------------------------------------- Crew overview */
    async function pollCrew() {
        let payload;
        try { payload = await api('/api/crew'); } catch (e) { return; }
        state.crewSnapshot = payload.crew;
        renderMission(payload.mission);
        animateNumber($('kpi-health'), payload.crew_health_index);
        setText('sb-crew-index', `${payload.crew_health_index}/100`);
        const avgDose = payload.crew.reduce((sum, m) => sum + m.telemetry.cumulative_dose_msv, 0) / payload.crew.length;
        animateNumber($('kpi-dose'), avgDose, 1);

        const anyCritical = payload.crew.some((m) => m.status === 'CRITICAL');
        setChip($('ov-status-chip'), anyCritical ? 'chip-red' : 'chip-green', `<span class="dot dot-live"></span>${anyCritical ? 'KRİTİK OLAY — OTONOM PROTOKOL AKTİF' : 'TÜM SİSTEMLER NOMİNAL'}`);

        payload.crew.forEach((m) => {
            const meta = statusMeta(m.status);
            const card = document.querySelector(`[data-crew-card="${m.id}"]`);
            if (card) {
                qsa('[data-k]', card).forEach((el) => { el.textContent = m.telemetry[el.dataset.k]; });
                setChip(card.querySelector('[data-crew-chip]'), meta.chip, meta.label);
                const ring = card.querySelector('[data-ring]');
                ring.style.strokeDashoffset = String(150.8 * (1 - m.health_index / 100));
                ring.style.stroke = meta.color;
                card.classList.toggle('ring-1', m.id === state.member);
                card.classList.toggle('ring-cyberGreen/40', m.id === state.member);
                const spark = (state.sparks[m.id] ||= []);
                spark.push(m.telemetry.heart_rate);
                if (spark.length > 30) spark.shift();
                renderSpark(card.querySelector('[data-spark]'), spark, meta.color);
            }
            const dot = document.querySelector(`[data-crew-dot="${m.id}"] [data-crew-status]`);
            if (dot) dot.style.background = meta.color;
        });
        renderDoseBudget(payload.crew);
    }

    function renderSpark(el, values, color) {
        if (!el || values.length < 2) return;
        const min = Math.min(...values) - 2, max = Math.max(...values) + 2;
        const points = values.map((v, i) => `${(i / (values.length - 1)) * 100},${36 - ((v - min) / (max - min)) * 32}`).join(' ');
        el.innerHTML = `<svg viewBox="0 0 100 40" preserveAspectRatio="none" class="w-full h-full"><defs><linearGradient id="sg-${color.slice(1)}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="${color}" stop-opacity="0.35"/><stop offset="1" stop-color="${color}" stop-opacity="0"/></linearGradient></defs><polygon points="0,40 ${points} 100,40" fill="url(#sg-${color.slice(1)})"/><polyline points="${points}" fill="none" stroke="${color}" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg>`;
    }

    function renderDoseBudget(crew) {
        const el = $('dose-budget');
        if (!el) return;
        el.innerHTML = crew.map((m) => {
            const pct = (m.telemetry.cumulative_dose_msv / 600) * 100;
            const color = pct >= 80 ? 'from-rose-500 to-rose-400' : pct >= 50 ? 'from-amber-500 to-amber-300' : 'from-emerald-400 to-cyan-400';
            return `<div><div class="flex justify-between text-[12px] mb-1"><span class="text-white font-semibold">${escapeHtml(m.name)}</span><span class="font-mono text-slate-400">${m.telemetry.cumulative_dose_msv} / 600 mSv · <span class="text-white">%${pct.toFixed(1)}</span></span></div>
                <div class="h-2.5 rounded-full bg-[#0b1120] border border-[#1A2235] overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r ${color} transition-all duration-700" style="width:${Math.min(100, pct)}%"></div></div></div>`;
        }).join('');
    }

    /* ---------------------------------------------------------------- Events */
    const eventIcon = { alert: '🚨', cognitive: '🎯', countermeasure: '🏋️', triage: '🤖', system: '🛰️' };
    const severityColor = { critical: 'text-rose-400', warning: 'text-amber-300', success: 'text-cyberGreen', info: 'text-cyberCyan' };
    const timeAgo = (iso) => {
        const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
        if (s < 60) return 'şimdi';
        if (s < 3600) return `${Math.floor(s / 60)} dk önce`;
        if (s < 86400) return `${Math.floor(s / 3600)} sa önce`;
        return `${Math.floor(s / 86400)} gün önce`;
    };
    async function pollEvents() {
        let payload;
        try { payload = await api('/api/events?limit=25'); } catch (e) { return; }
        const events = payload.events;
        const html = events.length ? events.map((e) => `
            <div class="feed-item">
                <div class="w-8 h-8 shrink-0 rounded-lg bg-[#06080E] border border-[#1A2235] grid place-items-center">${eventIcon[e.category] || '•'}</div>
                <div class="min-w-0 flex-1">
                    <div class="flex justify-between gap-2"><span class="text-[13px] font-semibold ${severityColor[e.severity] || 'text-white'} truncate">${escapeHtml(e.title)}</span><span class="text-[10px] font-mono text-slate-500 shrink-0">${timeAgo(e.created_at)}</span></div>
                    <div class="text-[11px] text-slate-400 truncate">${e.member_id ? `<span class="text-slate-300">${escapeHtml(BOOT.crew[e.member_id]?.name || e.member_id)}</span> · ` : ''}${escapeHtml(e.message || '')}</div>
                </div>
            </div>`).join('') : '<div class="text-[12px] text-slate-500 p-3">Henüz kayıt yok. Bir PVT testi yapın veya SPE alarmını tetikleyin.</div>';
        $('ov-feed').innerHTML = html;
        $('tr-feed').innerHTML = html;
        const newest = events[0]?.id ?? 0;
        if (state.lastEventId !== null && newest > state.lastEventId) {
            events.filter((e) => e.id > state.lastEventId && e.severity === 'critical').slice(0, 2).forEach((e) => toast(e.title, e.message, 'red'));
        }
        state.lastEventId = newest;
    }
    const logEvent = (body) => api('/api/events', { method: 'POST', body: JSON.stringify(body) }).then(pollEvents).catch(() => {});

    /* ---------------------------------------------------------------- Space weather */
    async function loadSpaceWeather() {
        let payload;
        try { payload = await api('/api/space-weather'); } catch (e) { return; }
        const sw = (state.spaceWeather = payload.space_weather);
        const threatMeta = { HIGH: ['chip-red', 'YÜKSEK RİSK'], ELEVATED: ['chip-gold', 'YÜKSELMİŞ'], LOW: ['chip-green', 'DÜŞÜK'] }[sw.threat_level];
        setChip($('donki-threat'), threatMeta[0], threatMeta[1]);
        setText('donki-source', `${sw.source} · ${new Date(sw.fetched_at).toLocaleString('tr-TR')}`);
        setText('donki-flares', sw.flare_count);
        setText('donki-cme', sw.cme_count ?? '—');
        setText('kpi-flare', sw.strongest ? sw.strongest.class : '—');
        setText('kpi-flare-sub', `${sw.flare_count} patlama · ${sw.is_live ? 'DONKI canlı' : 'arşiv'}`);
        const top = $('top-weather');
        top.textContent = `${threatMeta[1]}${sw.strongest ? ' · ' + sw.strongest.class : ''}`;
        top.className = `font-bold ${sw.threat_level === 'HIGH' ? 'text-rose-400' : sw.threat_level === 'ELEVATED' ? 'text-amber-300' : 'text-cyberGreen'}`;
        const classColor = (cls) => (cls.startsWith('X') ? 'text-rose-400' : cls.startsWith('M') ? 'text-amber-300' : 'text-cyberCyan');
        $('donki-table').innerHTML = sw.flares.length ? sw.flares.map((f) => `
            <tr><td class="font-mono font-bold ${classColor(f.class)}">${escapeHtml(f.class)}</td><td class="font-mono text-slate-300">${escapeHtml((f.peak_time || '').replace('T', ' ').replace('Z', ''))}</td><td class="text-slate-400">${escapeHtml(f.source)}${f.region ? ` · AR${escapeHtml(f.region)}` : ''}</td></tr>`).join('')
            : '<tr><td colspan="3" class="text-slate-500">Son 30 günde patlama kaydı yok.</td></tr>';
        if (state.currentPanel === 'radiation') chartBuilders.flareClasses();
    }

    /* ---------------------------------------------------------------- NASA data */
    const data = {
        loaded: false,
        stats: null,
        async load() {
            if (this.loaded) return;
            this.loaded = true;
            try {
                const [datasets, stats] = await Promise.all([api('/api/nasa/datasets'), api('/api/nasa/astronauts/stats')]);
                this.stats = stats.stats;
                this.render(datasets, stats.stats);
            } catch (e) { this.loaded = false; }
        },
        render(datasets, stats) {
            animateNumber($('ds-missions'), stats.total_missions);
            animateNumber($('ds-astronauts'), stats.unique_astronauts);
            animateNumber($('ds-hours'), stats.total_flight_hours);
            animateNumber($('ds-eva'), stats.total_eva_hours, 0);
            const row = (r, i, unitFn) => `<tr><td class="font-mono text-slate-500">${i + 1}</td><td class="text-white">${escapeHtml(r.name)}</td><td class="text-slate-400">${escapeHtml(r.nationality)}</td><td class="text-right font-mono text-cyberGreen">${unitFn(r.hours)}</td></tr>`;
            $('ds-top-hours').innerHTML = stats.top_flight_hours.map((r, i) => row(r, i, (h) => fmtNumber(h / 24, 0))).join('');
            $('ds-top-eva').innerHTML = stats.top_eva_hours.map((r, i) => row(r, i, (h) => fmtNumber(h, 1))).join('');

            const hrp = datasets.hrp_clinical;
            setText('ds-hrp-version', `NASA-STD-3001 · ${hrp.standard_version || ''}`);
            const s = hrp.biometric_reference_standards || {};
            const rows = [
                [s.heart_rate_bpm?.parameter, `${s.heart_rate_bpm?.normal_range_resting?.join('–')} BPM · taşikardi ≥${s.heart_rate_bpm?.alert_tachycardia}`, s.heart_rate_bpm?.citation],
                [s.oxygen_saturation_spo2?.parameter, `${s.oxygen_saturation_spo2?.nominal_target?.join('–')}% · kritik ≤${s.oxygen_saturation_spo2?.critical_hypoxia}%`, s.oxygen_saturation_spo2?.citation],
                [s.core_body_temperature?.parameter, `Baz ${s.core_body_temperature?.normal_space_baseline_c} °C · ateş ≥${s.core_body_temperature?.space_fever_onset_c} °C`, s.core_body_temperature?.citation],
                [s.intracranial_pressure_sans?.parameter, `${s.intracranial_pressure_sans?.risk_score_scale} · ${s.intracranial_pressure_sans?.countermeasure}`, s.intracranial_pressure_sans?.citation],
                [s.bone_mineral_density?.parameter, `${s.bone_mineral_density?.zero_g_decay_without_countermeasure}`, s.bone_mineral_density?.citation],
                [s.permissible_exposure_limit_pel?.parameter, `Kariyer < ${s.permissible_exposure_limit_pel?.career_limit_msv} mSv · SPE olayı ${s.permissible_exposure_limit_pel?.spe_event_limit_msv} mSv`, s.permissible_exposure_limit_pel?.standard_source],
            ].filter((r) => r[0]);
            $('ds-hrp').innerHTML = rows.map(([p, v, c]) => `<tr><td class="text-white">${escapeHtml(p)}</td><td class="font-mono text-cyberGreen text-[11px]">${escapeHtml(v)}</td><td class="text-slate-500 text-[11px]">${escapeHtml(c)}</td></tr>`).join('');

            const osdr = datasets.osdr_studies || {};
            setChip($('ds-osdr-status'), osdr.is_live ? 'chip-green' : 'chip-gold', osdr.is_live ? '<span class="dot dot-live"></span>CANLI · NASA API' : 'YEREL DOĞRULANMIŞ KOPYA');
            $('ds-osdr').innerHTML = (osdr.studies || []).map((st) => `
                <a href="${escapeHtml(st.link)}" target="_blank" rel="noopener" class="block stat-tile hover:!border-cyan-500/50">
                    <div class="flex justify-between gap-2"><span class="chip chip-cyan">${escapeHtml(st.accession)}</span><span class="text-[10px] font-mono text-slate-500">${escapeHtml(st.mission || '')} · ${escapeHtml(st.organism || '')}</span></div>
                    <div class="text-[13px] text-white font-semibold mt-1.5">${escapeHtml(st.title)}</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">${escapeHtml(st.flight_program || '')}${st.publication ? ` · 📄 ${escapeHtml(st.publication)}` : ''}</div>
                </a>`).join('');

            const env = datasets.msl_radiation.radiation_environment || {};
            $('ds-msl').innerHTML = [
                ['Derin uzay GCR', `${env.galactic_cosmic_rays_gcr?.average_daily_dose_equivalent_msv_d} mSv/g`, 'text-cyberGold'],
                ['Mars yüzeyi', `${env.mars_surface_gcr_msv_d} mSv/g`, 'text-cyberGreen'],
                ['SPE zirvesi', `${env.solar_particle_events_spe?.[0]?.peak_flux_msv_hr} mSv/sa`, 'text-rose-400'],
            ].map(([l, v, c]) => `<div class="stat-tile !p-2"><div class="stat-label">${l}</div><div class="font-bold ${c} text-sm">${escapeHtml(v)}</div></div>`).join('');
            chartBuilders.decades(stats);
            chartBuilders.nations(stats);
        },
    };

    /* ---------------------------------------------------------------- Emergency */
    const emergency = {
        busy: false,
        async toggle(force) {
            if (this.busy) return;
            this.busy = true;
            const next = typeof force === 'boolean' ? force : !state.emergency;
            try {
                const payload = await api('/api/emergency', { method: 'POST', body: JSON.stringify({ active: next }) });
                this.apply(payload.mission.emergency, payload.mission.shelter_seconds_remaining);
            } catch (e) {
                this.apply(next, next ? 480 : null);
            } finally {
                this.busy = false;
            }
            pollTelemetry();
            pollCrew();
            pollEvents();
        },
        apply(isActive, remaining, fromPoll = false) {
            const changed = isActive !== state.emergency;
            state.emergency = isActive;
            if (isActive && (changed || state.shelterRemaining == null)) state.shelterRemaining = remaining ?? 480;
            document.body.classList.toggle('red-alert', isActive);
            setText('sb-emergency-label', isActive ? 'ALARMI KAPAT' : 'SPE SİMÜLE ET');
            setText('rad-emergency-btn', isActive ? '✓ ALARMI SONLANDIR' : '🚨 GÜNEŞ FIRTINASI SİMÜLE ET');
            if (!changed) return;
            starfield.boost(1);
            if (isActive) {
                toast('KRİTİK: Güneş parçacık olayı', 'Otonom triage sığınak protokolünü başlattı.', 'red');
                voice.speak('Dikkat! Kritik güneş fırtınası algılandı. İyonlaştırıcı radyasyon akısı tehlikeli seviyede. Tüm mürettebat sekiz dakika içinde fırtına sığınağına geçmeli.');
            } else {
                toast('SPE alarmı sonlandırıldı', 'Telemetri nominal seviyeye dönüyor.', 'green');
                if (!fromPoll) voice.speak('Güneş fırtınası alarmı kapatıldı. Biyometrik telemetri nominal seviyelere dönüyor.');
            }
            Object.keys(chartBuilders).forEach((k) => { if (!['decades', 'nations'].includes(k)) chartBuilders[k](); });
            if (conditions.data) conditions.load();
        },
    };

    /* ---------------------------------------------------------------- Countermeasures */
    const countermeasures = {
        sync() {
            const boxes = qsa('[data-cm]');
            const done = boxes.filter((b) => b.checked).length;
            state.countermeasureCompliance = done / boxes.length;
            setText('cm-badge', `${done}/${boxes.length} · %${Math.round(state.countermeasureCompliance * 100)}`);
            $('cm-bar').style.width = `${state.countermeasureCompliance * 100}%`;
            chartBuilders.bone();
            conditions.setCompliance(Math.round(state.countermeasureCompliance * 100), false);
        },
        toggle(box) {
            this.sync();
            if (box.checked) {
                const label = box.closest('label').querySelector('span').textContent.trim();
                logEvent({ member_id: state.member, category: 'countermeasure', severity: 'success', title: 'Karşı-tedbir tamamlandı', message: label });
                toast('Karşı-tedbir kaydedildi', label, 'green');
            }
        },
    };

    /* ---------------------------------------------------------------- PVT */
    const pvt = {
        count: 0, startedAt: 0, times: [], timer: null,
        start() {
            clearTimeout(this.timer);
            this.count = 0; this.times = [];
            ['pvt-avg', 'pvt-best', 'pvt-score'].forEach((id) => setText(id, '—'));
            setText('pvt-status', 'Hazır olun…');
            this.next();
        },
        next() {
            const target = $('target'), arena = $('arena');
            target.classList.add('hidden');
            if (this.count >= 5) return this.finish();
            this.timer = setTimeout(() => {
                target.style.left = `${Math.random() * (arena.clientWidth - 56) + 4}px`;
                target.style.top = `${Math.random() * (arena.clientHeight - 56) + 4}px`;
                target.classList.remove('hidden');
                target.style.animation = 'none'; void target.offsetWidth; target.style.animation = '';
                this.startedAt = performance.now();
                setText('pvt-status', '');
            }, 600 + Math.random() * 1400);
        },
        hit() {
            const ms = Math.round(performance.now() - this.startedAt);
            this.times.push(ms);
            this.count++;
            setText('pvt-status', `${ms} ms · ${this.count}/5`);
            this.next();
        },
        finish() {
            const avg = Math.round(this.times.reduce((a, b) => a + b, 0) / this.times.length);
            const best = Math.min(...this.times);
            const score = Math.max(40, Math.min(100, Math.round(100 - (avg - 250) / 6)));
            setText('pvt-avg', `${avg} ms`);
            setText('pvt-best', `${best} ms`);
            setText('pvt-score', `%${score}`);
            setText('pvt-status', score >= 80 ? '✓ Bilişsel performans görev için uygun' : '⚠ Dinlenme önerilir — kritik görevlerde çift onay');
            state.cognitiveOverride[state.member] = score;
            logEvent({ member_id: state.member, category: 'cognitive', severity: score >= 80 ? 'success' : 'warning', title: `PVT testi: %${score}`, message: `Ortalama ${avg} ms, en hızlı ${best} ms`, payload: { avg_ms: avg, best_ms: best, score } });
            voice.speak(`Bilişsel test tamamlandı. Ortalama tepki süresi ${avg} milisaniye. Skor yüzde ${score}.`);
        },
    };

    /* ---------------------------------------------------------------- Report */
    const report = {
        render() {
            const t = state.telemetry, ai = state.triage;
            if (!t || !ai) return;
            const m = BOOT.crew[state.member];
            const verdict = ai.status === 'NOMINAL' ? ['chip-green', '✓ UÇUŞA UYGUN'] : ai.status === 'CAUTION' ? ['chip-gold', '⚠ YAKIN İZLEM'] : ['chip-red', '✕ ACİL MÜDAHALE'];
            setChip($('rp-verdict'), verdict[0], verdict[1]);
            setText('rp-doc', `VB-MED-${state.mission.mission_day}-${state.member.toUpperCase()}`);
            setText('rp-time', new Date().toLocaleString('tr-TR'));
            setText('rp-name', m.name);
            setText('rp-role', m.role);
            setText('rp-blood', m.blood_type);
            setText('rp-day', `Gün ${state.mission.mission_day} / ${state.mission.transit_days}`);
            const level = (metric) => ai.findings.find((f) => f.metric === metric)?.level || 'nominal';
            const badge = (l) => `<span class="chip ${l === 'critical' ? 'chip-red' : l === 'warning' ? 'chip-gold' : 'chip-green'}">${l === 'critical' ? 'KRİTİK' : l === 'warning' ? 'UYARI' : 'NORMAL'}</span>`;
            const rows = [
                ['Kalp hızı', `${t.heart_rate} BPM`, '60–85 BPM', level('heart_rate')],
                ['SpO₂', `%${t.spo2}`, '%95–99.5', level('spo2')],
                ['Vücut ısısı', `${t.body_temp_c} °C`, '< 37.8 °C', level('body_temp_c')],
                ['Kan basıncı', `${t.blood_pressure} mmHg`, '< 130 / 85', 'nominal'],
                ['Solunum', `${t.respiratory_rate} rpm`, '12–20 rpm', 'nominal'],
                ['HRV (RMSSD)', `${t.hrv_ms} ms`, '> 30 ms', t.hrv_ms < 30 ? 'warning' : 'nominal'],
                ['Bilişsel indeks', `${t.cognitive_index}/100`, '≥ 80', t.cognitive_index < 80 ? 'warning' : 'nominal'],
            ];
            $('rp-vitals').innerHTML = rows.map(([p, v, r, l]) => `<tr><td class="text-white">${p}</td><td class="font-mono">${v}</td><td class="font-mono text-slate-500">${r}</td><td>${badge(l)}</td></tr>`).join('');
            setText('rp-rate', `${t.radiation_msv} mSv/gün`);
            setText('rp-cum', `${t.cumulative_dose_msv} mSv`);
            setText('rp-career', `%${((t.cumulative_dose_msv / 600) * 100).toFixed(1)}`);
            setText('rp-message', ai.message);
            $('rp-actions').innerHTML = ai.actions.map((a) => `<li>${escapeHtml(a)}</li>`).join('');
        },
    };

    /* ---------------------------------------------------------------- Cinematic jury tour */
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const waitFor = (condition, timeout = 30000) => new Promise((resolve) => {
        const started = performance.now();
        const tick = () => (condition() || performance.now() - started > timeout ? resolve() : setTimeout(tick, 200));
        tick();
    });
    const speakAsync = (text) => Promise.race([
        new Promise((resolve) => voice.speak(text, { onend: resolve })),
        sleep(text.length * 85 + 2500),
    ]);
    async function typeInto(inputId, text) {
        const el = $(inputId);
        if (!el) return;
        el.value = '';
        for (const ch of text) { el.value += ch; await sleep(55); }
        await sleep(350);
    }

    const tour = {
        chapters: ['Problem', 'Görev', 'Vücut', 'Kriz', 'AI Doktor', 'Önleme', 'Bilim'],
        steps: [
            { chapter: 0, panel: 'story', target: '#panel-story .card', title: 'Mars\'ta en yakın hastane 225 milyon km uzakta', text: 'Derin uzayda Dünya ile iletişim yirmi iki dakikaya kadar gecikiyor. Gemide doktor yok. VanBlue AegisHealth, mürettebatın sağlığını gemide, NASA verileriyle ve otonom olarak yönetiyor.' },
            { chapter: 1, panel: 'overview', target: '#trajectory', title: 'Görev Kontrol — gerçek zamanlı yörünge', text: 'Gemi şu an Mars yolunda. Mesafe, hız ve ışık hızıyla hesaplanan iletişim gecikmesi her saniye güncelleniyor. Cyan sinyal, Dünya\'dan gelen mesajın ne kadar yavaş ilerlediğini gösteriyor.' },
            { chapter: 1, panel: 'overview', target: '#ov-crew', title: 'Dört astronot, tek bakışta', text: 'Her mürettebat üyesinin sağlık indeksi, nabzı ve radyasyon dozu canlı akıyor. Kart rengi, otonom triage motorunun anlık kararını gösteriyor.' },
            { chapter: 2, panel: 'crew', target: '#ecg-canvas', title: 'Canlı biyotelemetri', text: 'Gerçek dalga formlu EKG, oksijen, tansiyon, kalp hızı değişkenliği ve NASA\'nın beş stresör radarı. Hologram üzerindeki noktalar giyilebilir sensörlerin yerini gösteriyor.' },
            { chapter: 2, panel: 'conditions', target: '#cond-slider', title: 'Zaman makinesi: 920 günlük Mars görevi', text: 'Uzayda kemikler Dünya\'dakinden on iki kat hızlı eriyor. Görevi ileri sararak on iki NASA sağlık riskinin gün gün nasıl değiştiğini öngörüyoruz.', after: async () => { if (!conditions.playing) conditions.togglePlay(); await waitFor(() => !conditions.playing, 26000); } },
            { chapter: 2, panel: 'conditions', target: '#cond-detail', title: 'Uzay hastalıkları atlası', text: 'Her risk için uzay ile Dünya karşılaştırması, belirtiler, sensörler ve karşı tedbir var. Ve en önemlisi: aynı çözüm Dünya\'da osteoporoz hastalarına ve yatağa bağlı insanlara da fayda sağlıyor.', before: () => conditions.select('bone_loss') },
            { chapter: 3, panel: 'radiation', target: '#donki-table', title: 'Gerçek NASA uzay havası verisi', text: 'NASA DONKI servisinden güneş patlamaları ve Curiosity MSL RAD ölçümleriyle doz projeksiyonu. Kariyer limiti altı yüz milisievert.' },
            { chapter: 3, panel: 'radiation', target: '#chart-gauge', title: '🚨 Senaryo: X sınıfı güneş fırtınası', text: 'Şimdi bir güneş fırtınası simüle ediyoruz. Doz hızı kritik eşiği aşıyor, alarm çalıyor ve sekiz dakikalık sığınak sayacı başlıyor.', before: () => emergency.toggle(true), minMs: 9000 },
            { chapter: 3, panel: 'triage', target: '#cmp-earth-bar', title: 'Dünya\'yı beklemeden, milisaniyede karar', text: 'Kural motoru NASA eşiklerini aşan değerleri bulgularıyla birlikte tespit etti ve sığınak protokolünü başlattı. Dünya\'dan cevap beklemek yarım saat sürerdi.' },
            { chapter: 3, panel: 'detective', target: '#dt-station', title: '🔎 Sağlık Dedektifi: alarm değil, neden', text: 'İki astronotta baş ağrısı ve yorgunluk başladı. Sistem sadece alarm vermiyor: ikisinin de egzersiz bölmesinde zaman geçirdiğini, oradaki CO₂ sensörünün yükseldiğini ve diğer modüllerdeki arkadaşlarının sağlıklı olduğunu birleştirerek ortak kabin nedenini öne çıkarıyor.', before: () => { emergency.toggle(false); return detective.setScenario('cabin', false, true); }, minMs: 6000 },
            { chapter: 3, panel: 'detective', target: '#dt-verdict-card', title: '⚡ Yeni kanıt: sensörler uyuşmuyor', text: 'Şimdi yedek sensörün farklı bir değer okuduğunu simüle ediyoruz. Dedektif değerlendirmesini anında güncelliyor: artık emin değil ve önce sensörün doğrulanması gerektiğini söylüyor.', before: () => detective.toggleFault(true, true), minMs: 6000 },
            { chapter: 3, panel: 'detective', target: '#dt-next-card', title: '🧭 Sıradaki en bilgilendirici kontrol', text: 'Sistem iki olasılığı en iyi ayıracak kontrolü seçiyor: bağımsız el tipi CO₂ ölçümü. Ölçüm yüksek çıkıyor; kanıt zinciri güncelleniyor ve kabin ortamı yeniden güçlü şekilde öne geçiyor. Prosedür Dünya bağlantısı olmadan uygulanabiliyor.', after: async () => { await detective.runNext(true); await sleep(4500); } },
            { chapter: 4, panel: 'assistant', target: '#ai-transcript', title: 'Sesli AI uçuş doktoru', text: 'Şimdi astronot şikâyetini sesli anlatıyor. Yapay zekâ canlı vitalleri, görev bağlamını ve gemi protokollerini görerek çözüm planı veriyor.', before: () => emergency.toggle(false), after: async () => {
                assistant.setOrb('listening', 'Dinliyorum… şikâyetini anlat.');
                $('ai-mic')?.classList.add('recording');
                await typeInto('ai-input', 'Kulaklarımda ağrı ve basınç var');
                $('ai-mic')?.classList.remove('recording');
                assistant.send();
                await waitFor(() => !assistant.busy, 45000);
                await sleep(1200);
                await waitFor(() => !('speechSynthesis' in window) || !speechSynthesis.speaking, 30000);
                await sleep(1200);
            } },
            { chapter: 4, panel: 'assistant', target: '#ai-context', title: 'Bağlamı gören, kırmızı bayrağı kaçırmayan AI', text: 'Her soru canlı vitaller ve NASA risk atlasıyla gönderiliyor. Göğüs ağrısı gibi kırmızı bayraklarda model ne derse desin durum acil işaretleniyor. İnternet yoksa gemideki protokol motoru devreye giriyor.' },
            { chapter: 4, panel: 'crew', target: '#cw-window', title: 'Her ekranda: canlı sohbet', text: 'Astronot hangi ekranda olursa olsun sağ alttaki baloncuktan Aegis\'e ulaşabiliyor. Kritik bir durum olduğunda Aegis ilk mesajı kendisi atıyor.', before: () => chat.open(), after: async () => {
                await typeInto('cw-input', 'Belim çok ağrıyor');
                chat.send();
                await waitFor(() => !chat.busy, 45000);
                await sleep(1200);
                await waitFor(() => !('speechSynthesis' in window) || !speechSynthesis.speaking, 30000);
                await sleep(1000);
            } },
            { chapter: 4, panel: 'overview', target: null, title: 'AI günlük mürettebat brifingi', text: 'Her sabah Aegis tüm mürettebatın vitallerini, risklerini, son yirmi dört saatini, uzay havasını ve eczaneyi analiz edip bir uçuş cerrahı gibi sesli brifing veriyor.', minMs: 2000, after: async () => { briefing.open(); await waitFor(() => briefing.segmentIndex >= 2 || !briefing.isOpen, 60000); await sleep(4000); briefing.close(); } },
            { chapter: 5, panel: 'pharmacy', target: '#ph-grid', title: 'Gemi eczanesi: ikmal yok', text: 'Mars yolunda tükenen ilaç geri gelmez. Aegis yalnızca stokta olan ilacı öneriyor, tek tıkla stoktan düşüyor ve kritik seviyede uyarıyor.' },
            { chapter: 5, panel: 'countermeasures', target: '#chart-bone', title: 'Önleyici sağlık', text: 'Egzersiz uyumuna göre kemik kaybı projeksiyonu ve astronotun reaksiyon süresini ölçen bilişsel test. Sonuçlar tıbbi kayda yazılıyor ve riskleri güncelliyor.', before: () => chat.close() },
            { chapter: 6, panel: 'data', target: '#panel-data .card', title: 'Veri şeffaflığı', text: 'Hangi verinin canlı NASA servisinden, hangisinin hakemli makaleden, hangisinin simülasyondan geldiğini açıkça gösteriyoruz. NASA OSDR çalışmaları canlı API\'den çekiliyor.' },
            { chapter: 6, panel: 'report', target: '#print-report', title: 'Tek tıkla uçuş tıbbı raporu', text: 'Canlı veriden oluşan, yazdırılabilir tıbbi rapor. Dünya\'ya gecikmeli senkronize ediliyor.' },
        ],
        index: -1, runId: 0, paused: false, active: false,
        /** Presenter mode: no voice-over, no intro countdown, steps advance only on › / → clicks. */
        manual: true,

        async start() {
            if (this.active) return;
            this.active = true;
            this.paused = false;
            this.renderDots();
            this.setPauseIcon();
            chat.close();
            if (!this.manual) await this.cover('intro');
            if (!this.active) return;
            $('tour-caption').classList.add('show');
            this.play(0);
        },
        stop() {
            this.runId++;
            this.active = false;
            this.index = -1;
            $('tour-caption').classList.remove('show');
            $('tour-spot').classList.remove('show');
            $('tour-cover').classList.remove('show');
            if (conditions.playing) conditions.togglePlay();
            if ('speechSynthesis' in window) speechSynthesis.cancel();
        },
        next() { if (this.active) this.play(this.index + 1); },
        prev() { if (this.active && this.index > 0) this.play(this.index - 1); },
        togglePause() {
            if (!this.active) return;
            this.paused = !this.paused;
            this.setPauseIcon();
            if ('speechSynthesis' in window) this.paused ? speechSynthesis.pause() : speechSynthesis.resume();
        },
        setPauseIcon() { setText('tour-pause', this.paused ? '▶' : '⏸'); },
        renderDots() {
            $('tour-dots').innerHTML = this.chapters.map((name, i) => `<span class="tour-dot" data-chapter="${i}" title="${name}"><i></i><b>${name}</b></span>`).join('');
        },
        async play(index) {
            const runId = ++this.runId;
            if (index >= this.steps.length) { await this.finish(runId); return; }
            this.index = index;
            const step = this.steps[index];
            if ('speechSynthesis' in window) speechSynthesis.cancel();

            nav.go(step.panel);
            setText('tour-step', `${index + 1}/${this.steps.length}`);
            setText('tour-chapter', this.chapters[step.chapter]);
            setText('tour-title', step.title);
            setText('tour-text', step.text);
            $('tour-bar').style.width = `${((index + 1) / this.steps.length) * 100}%`;
            qsa('.tour-dot').forEach((dot) => {
                const chapter = Number(dot.dataset.chapter);
                dot.classList.toggle('done', chapter < step.chapter);
                dot.classList.toggle('current', chapter === step.chapter);
            });

            await sleep(500);
            if (runId !== this.runId) return;
            await step.before?.();
            await sleep(250);
            this.spotlight(step.target);

            if (this.manual) {
                await step.after?.();
                return;
            }

            await Promise.all([speakAsync(step.text), sleep(step.minMs || 4500)]);
            if (runId !== this.runId) return;
            await step.after?.();
            if (runId !== this.runId) return;
            await waitFor(() => !this.paused, Infinity);
            if (runId !== this.runId) return;
            await sleep(600);
            if (runId === this.runId) this.play(index + 1);
        },
        async finish(runId) {
            $('tour-caption').classList.remove('show');
            $('tour-spot').classList.remove('show');
            if (state.emergency) emergency.toggle(false);
            nav.go('story');
            await this.cover('outro');
            if (runId === this.runId) this.active = false;
        },
        spotlight(selector) {
            const spot = $('tour-spot');
            const el = selector ? document.querySelector(selector) : null;
            if (!el) { spot.classList.remove('show'); return; }
            const target = el.closest('.card') || el;
            this.currentTarget = target;
            if (getComputedStyle(target).position !== 'fixed' && !target.closest('#chat-widget')) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            setTimeout(() => this.placeSpot(), 450);
        },
        placeSpot() {
            const target = this.currentTarget;
            if (!target || !this.active) return;
            const r = target.getBoundingClientRect();
            const pad = 10;
            const spot = $('tour-spot');
            Object.assign(spot.style, {
                top: `${r.top - pad}px`,
                left: `${r.left - pad}px`,
                width: `${r.width + pad * 2}px`,
                height: `${Math.min(r.height + pad * 2, innerHeight - 20)}px`,
            });
            spot.classList.add('show');
            const caption = $('tour-caption');
            const lowerHalf = r.top + r.height / 2 > innerHeight * 0.55;
            caption.classList.toggle('top', lowerHalf);
        },
        cover(kind) {
            const cover = $('tour-cover');
            $('tour-cover-intro').classList.toggle('hidden', kind !== 'intro');
            $('tour-cover-outro').classList.toggle('hidden', kind !== 'outro');
            cover.classList.add('show');
            if (kind === 'intro') {
                voice.speak('VanBlue AegisHealth jüri turuna hoş geldiniz. Üç dakikada, Mars yolundaki bir doktoru tanıyacaksınız.');
                return new Promise((resolve) => {
                    let count = 5;
                    setText('tour-countdown', count);
                    const timer = setInterval(() => {
                        count--;
                        setText('tour-countdown', count);
                        if (count <= 0 || !this.active) { clearInterval(timer); cover.classList.remove('show'); resolve(); }
                    }, 1000);
                    this.skipIntro = () => { clearInterval(timer); cover.classList.remove('show'); resolve(); };
                });
            }
            voice.speak('Uzayda hayat kurtaran bu sistem, Dünya\'da da doktora uzak her yere sağlık götürebilir. Dinlediğiniz için teşekkürler. Takım VanBlue.');
            return Promise.resolve();
        },
    };
    addEventListener('resize', () => tour.active && tour.placeSpot());
    addEventListener('scroll', () => tour.active && tour.placeSpot(), { passive: true });

    /* ---------------------------------------------------------------- Space medicine atlas */
    const conditions = {
        data: null,
        day: null,
        compliance: 60,
        selected: 'bone_loss',
        playing: false,
        playTimer: null,
        fetchTimer: null,
        requestId: 0,
        heroAnimated: false,
        levelMeta: { critical: ['chip-red', 'YÜKSEK RİSK', C.red], warning: ['chip-gold', 'ORTA RİSK', C.gold], nominal: ['chip-green', 'DÜŞÜK RİSK', C.green] },
        palette: [C.red, C.gold, C.violet, C.cyan, C.green, '#F472B6', '#60A5FA', '#FB923C', '#34D399', '#E879F9', '#94A3B8', '#FDE68A'],
        enter() {
            if (this.day === null) {
                this.day = state.mission.mission_day;
                $('cond-slider').value = this.day;
            }
            if (!this.heroAnimated) {
                this.heroAnimated = true;
                qsa('[data-hero-count]').forEach((el) => animateNumber(el, Number(el.dataset.heroCount), 0, 1600));
            }
            this.load();
        },
        setDay(value) {
            this.day = Number(value);
            setText('cond-day', this.day);
            this.scheduleLoad();
        },
        setCompliance(value, reload = true) {
            this.compliance = Number(value);
            setText('cond-compliance-val', this.compliance);
            const slider = $('cond-compliance');
            if (slider && Number(slider.value) !== this.compliance) slider.value = this.compliance;
            if (reload && this.data) this.scheduleLoad();
            else if (this.data) this.scheduleLoad();
        },
        scheduleLoad() {
            clearTimeout(this.fetchTimer);
            this.fetchTimer = setTimeout(() => this.load(), 90);
        },
        async load() {
            if (this.day === null) return;
            const requestId = ++this.requestId;
            let payload;
            try {
                payload = await api(`/api/conditions?member=${state.member}&day=${this.day}&compliance=${(this.compliance / 100).toFixed(2)}`);
            } catch (e) { return; }
            if (requestId !== this.requestId) return;
            this.data = payload.assessment;
            this.render();
        },
        render() {
            const a = this.data;
            if (!a) return;
            setText('cond-day', a.day);
            setText('cond-phase', a.phase.label);
            setText('cond-member', BOOT.crew[state.member].name);
            setText('cond-high', a.high_risk_count);
            const overall = $('cond-overall');
            overall.textContent = `${a.overall_risk}/100`;
            overall.style.color = a.overall_risk >= 50 ? C.red : a.overall_risk >= 30 ? C.gold : C.green;

            $('cond-grid').innerHTML = a.conditions.map((c) => {
                const [chip, label, color] = this.levelMeta[c.projection.level];
                return `<button onclick="VB.conditions.select('${c.id}')" class="card card-lift p-4 text-left ${c.id === this.selected ? '!border-cyberGreen/60' : ''}">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">${c.icon}</span>
                        <div class="min-w-0 flex-1"><div class="font-semibold text-white text-[13px] truncate">${escapeHtml(c.name)}</div><div class="text-[10px] font-mono text-slate-500 truncate">${escapeHtml(c.projection.metric)}</div></div>
                        <span class="chip ${chip} !text-[9px]">${label}</span>
                    </div>
                    <div class="flex items-end justify-between mt-3"><span class="font-space font-bold text-xl" style="color:${color}">${escapeHtml(c.projection.value)}</span><span class="font-mono text-[11px] text-slate-400">risk ${c.projection.risk}</span></div>
                    <div class="h-1.5 rounded-full bg-[#0b1120] mt-2 overflow-hidden"><div class="h-full rounded-full transition-all duration-500" style="width:${c.projection.risk}%;background:${color};box-shadow:0 0 10px ${color}"></div></div>
                </button>`;
            }).join('');

            const zoneRisk = {};
            a.conditions.forEach((c) => { zoneRisk[c.zone] = Math.max(zoneRisk[c.zone] || 0, c.projection.risk); });
            qsa('[data-zone]').forEach((el) => {
                const risk = zoneRisk[el.dataset.zone] || 0;
                el.querySelector('[data-zone-color]').style.color = risk >= 65 ? C.red : risk >= 35 ? C.gold : C.green;
            });

            this.renderDetail();
            this.renderChart();
        },
        renderDetail() {
            const c = this.data?.conditions.find((item) => item.id === this.selected);
            if (!c) return;
            const [chip, label, color] = this.levelMeta[c.projection.level];
            setText('cd-icon', c.icon);
            setText('cd-system', c.system);
            setChip($('cd-level'), chip, label);
            setText('cd-onset', `⏱ ${c.onset}`);
            setText('cd-name', c.name);
            setText('cd-name-en', c.name_en);
            setText('cd-metric', c.projection.metric);
            setText('cd-value', c.projection.value);
            $('cd-value').style.color = color;
            setText('cd-note', c.projection.note);
            setText('cd-fact', c.fact);
            setText('cd-multiplier', c.multiplier_text);
            setText('cd-space-label', c.space_label);
            setText('cd-earth-label', c.earth_label);
            setText('cd-citation', c.citation);
            setText('cd-aegis', c.aegis_action);
            setText('cd-earth-analog', c.earth_analog);
            const list = (items) => items.map((item) => `<li class="flex gap-2"><span class="text-cyberGreen">›</span>${escapeHtml(item)}</li>`).join('');
            $('cd-symptoms').innerHTML = list(c.symptoms);
            $('cd-sensors').innerHTML = list(c.sensors);
            $('cd-countermeasures').innerHTML = list(c.countermeasures);
            const max = Math.max(c.space_rate, c.earth_rate) || 1;
            const spaceBar = $('cd-space-bar'), earthBar = $('cd-earth-bar');
            if (this.lastAnimated !== c.id) {
                this.lastAnimated = c.id;
                spaceBar.style.width = '0'; earthBar.style.width = '0';
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    spaceBar.style.width = `${Math.max(2, (c.space_rate / max) * 100)}%`;
                    earthBar.style.width = `${Math.max(2, (c.earth_rate / max) * 100)}%`;
                }));
            }
        },
        renderChart() {
            const ch = chart('chart-conditions');
            const a = this.data;
            if (!ch || !a) return;
            const ids = a.conditions.map((c) => c.id);
            const names = Object.fromEntries(a.conditions.map((c) => [c.id, c.name]));
            const nearest = String(Math.round(a.day / 20) * 20);
            ch.setOption({
                grid: { top: 40, right: 20, bottom: 30, left: 40 },
                tooltip: { ...tooltip, valueFormatter: (v) => `risk ${v}` },
                legend: { type: 'scroll', top: 0, textStyle: { color: '#94A3B8', fontSize: 10 }, itemWidth: 12, selected: Object.fromEntries(ids.map((id, i) => [names[id], i < 6 || id === this.selected])) },
                xAxis: { type: 'category', boundaryGap: false, data: a.timeline.days.map(String), ...axisStyle, axisLabel: { ...axisStyle.axisLabel, formatter: (v) => `G${v}` } },
                yAxis: { type: 'value', min: 0, max: 100, ...axisStyle },
                series: ids.map((id, i) => ({
                    name: names[id], type: 'line', smooth: true, symbol: 'none',
                    data: a.timeline.series[id],
                    lineStyle: { width: id === this.selected ? 3.5 : 1.6, color: this.palette[i % this.palette.length], shadowBlur: id === this.selected ? 12 : 0, shadowColor: this.palette[i % this.palette.length] },
                    itemStyle: { color: this.palette[i % this.palette.length] },
                    emphasis: { focus: 'series' },
                    ...(i === 0 ? {
                        markLine: { symbol: 'none', silent: true, data: [{ xAxis: nearest, label: { formatter: `GÜN ${a.day}`, color: C.cyan, fontFamily: 'JetBrains Mono' }, lineStyle: { color: C.cyan, type: 'solid', width: 1.5 } }] },
                        markArea: { silent: true, itemStyle: { color: 'rgba(251,146,60,0.05)' }, label: { show: true, color: '#FB923C', fontSize: 10, position: 'insideTop', formatter: 'MARS YÜZEYİ' }, data: [[{ xAxis: '220' }, { xAxis: '700' }]] },
                    } : {}),
                })),
            }, { replaceMerge: ['series'] });
        },
        select(id) {
            this.selected = id;
            this.render();
            document.getElementById('cond-detail')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },
        selectZone(zone) {
            const match = this.data?.conditions.filter((c) => c.zone === zone);
            if (!match?.length) return;
            const currentIndex = match.findIndex((c) => c.id === this.selected);
            this.select(match[(currentIndex + 1) % match.length].id);
        },
        togglePlay() {
            this.playing = !this.playing;
            $('cond-play-icon').innerHTML = this.playing ? '<path d="M6 5h4v14H6zM14 5h4v14h-4z"/>' : '<path d="M8 5v14l11-7z"/>';
            clearInterval(this.playTimer);
            if (!this.playing) return;
            if (this.day >= 920) this.day = 0;
            voice.speak('Görev zaman makinesi başlatıldı. Dokuz yüz yirmi günlük Mars görevinde vücuttaki değişimi izleyin.');
            this.playTimer = setInterval(() => {
                const next = Math.min(920, this.day + 10);
                $('cond-slider').value = next;
                this.setDay(next);
                if (next === 210) toast('Mars inişi · Gün 210', 'Kalp-damar zayıflaması nedeniyle inişte bayılma riski en yüksek seviyede.', 'gold');
                if (next === 920) { toast('Dünya dönüşü · Gün 920', 'Kemik ve kas kaybı rehabilitasyon gerektiriyor.', 'cyan'); this.togglePlay(); }
            }, 220);
        },
    };

    /* ---------------------------------------------------------------- Solution plan block */
    function solutionHtml(a, compact) {
        const plan = a.treatment_plan || {};
        const now = (plan.now && plan.now.length ? plan.now : (a.immediate_steps || []).slice(0, 3));
        const items = (list, icon) => (list || []).map((s) => `<div class="flex gap-2"><span class="shrink-0">${icon}</span><span>${escapeHtml(s)}</span></div>`).join('');
        const meds = (a.medications || []).map((m) => `<div class="flex gap-2 items-start"><span class="shrink-0">💊</span><span class="flex-1"><strong class="text-white">${escapeHtml(m.name)}</strong> — <span class="text-cyberGreen font-mono">${escapeHtml(m.dose || '')}</span>${m.note ? ` <span class="text-slate-500">(${escapeHtml(m.note)})</span>` : ''}${m.inventory_id ? `<span class="block mt-1"><button class="chip ${m.stock_status === 'ok' ? 'chip-green' : m.stock_status === 'low' ? 'chip-gold' : 'chip-red'} !py-0.5 hover:brightness-125" data-dispense="${m.inventory_id}" data-name="${escapeHtml(m.name)}" ${m.in_stock ? '' : 'disabled'}>${m.in_stock ? `Stoktan ver · ${m.in_stock} ${escapeHtml(m.unit || '')} kaldı` : 'Stokta yok'}</button></span>` : ''}</span></div>`).join('');
        const section = (title, body, tone = 'text-slate-400') => body ? `<div class="mt-2.5"><div class="text-[10px] font-mono font-bold tracking-wider ${tone} mb-1">${title}</div><div class="space-y-1 text-[12.5px] text-slate-200">${body}</div></div>` : '';
        const html = [
            section('💊 TEDAVİ / İLAÇ', meds, 'text-cyberGreen'),
            section('⚡ ŞİMDİ YAP', items(now, '✅'), 'text-cyberGreen'),
            section('🕐 SONRAKİ 24 SAAT', items(plan.next_24h, '•'), 'text-cyan-300'),
            section('🚫 KAÇIN', items(plan.avoid, '✕'), 'text-rose-300'),
            plan.expected_course ? section('📈 BEKLENEN SEYİR', `<div>${escapeHtml(plan.expected_course)}</div>`, 'text-violet-300') : '',
            a.escalate_when ? section('⚠️ HEMEN HABER VER', `<div class="text-amber-200">${escapeHtml(a.escalate_when)}</div>`, 'text-amber-300') : '',
        ].join('');
        if (!html) return '';
        return compact
            ? `<div class="cw-steps !gap-0">${html}</div>`
            : `<div class="mt-4 p-4 rounded-2xl border border-cyberGreen/30 bg-gradient-to-br from-emerald-500/10 to-transparent"><div class="text-[11px] font-mono font-bold text-cyberGreen tracking-widest">🩺 ÇÖZÜM PLANI</div>${html}</div>`;
    }

    /* ---------------------------------------------------------------- AI flight surgeon */
    const assistant = {
        history: [],
        busy: false,
        listening: false,
        recognition: null,
        audio: null,
        urgencyMeta: {
            emergency: ['chip-red', '🚨 ACİL'],
            urgent: ['chip-red', 'ÖNCELİKLİ'],
            monitor: ['chip-gold', 'İZLEM'],
            routine: ['chip-green', 'RUTİN'],
        },
        setOrb(stateName, label) {
            $('ai-orb')?.setAttribute('data-state', stateName);
            if (label) setText('ai-state-label', label);
        },
        updateContext({ member, telemetry: t }) {
            if (!t) return;
            setText('ai-ctx-name', member.name);
            qsa('[data-ctx]').forEach((el) => { el.textContent = t[el.dataset.ctx] ?? '—'; });
        },
        bubble(html, who = 'bot') {
            const wrap = document.createElement('div');
            wrap.className = `ai-msg ai-msg-${who}`;
            wrap.innerHTML = `<div class="ai-avatar">${who === 'bot' ? 'AI' : escapeHtml(BOOT.crew[state.member].initials)}</div><div class="ai-bubble">${html}</div>`;
            const box = $('ai-transcript');
            box.appendChild(wrap);
            box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
            return wrap;
        },
        async send(preset) {
            const input = $('ai-input');
            const message = (preset ?? input.value).trim();
            if (!message || this.busy || chat.busy) return;
            input.value = '';
            this.stopMic();
            this.busy = true;
            if ('speechSynthesis' in window) speechSynthesis.cancel();
            const image = attachments.take('panel');
            this.bubble(`${image ? `<img src="${image}" class="w-40 rounded-xl mb-2" alt="Ek">` : ''}${escapeHtml(message)}`, 'user');
            const typing = this.bubble('<div class="ai-typing"><span></span><span></span><span></span></div><div class="text-[10px] font-mono text-slate-500 mt-1">Vitaller, görev bağlamı ve NASA risk atlası analiz ediliyor…</div>');
            this.setOrb('thinking', 'Düşünüyorum… canlı vitallerini ve görev bağlamını değerlendiriyorum.');
            let payload;
            try {
                payload = await api('/api/assistant/consult', { method: 'POST', body: JSON.stringify({ member_id: state.member, message, history: this.history, image }) });
            } catch (e) {
                typing.remove();
                this.bubble('<span class="text-rose-300">Bağlantı hatası. Lütfen tekrar dene.</span>');
                this.setOrb('idle', 'Bağlantı hatası.');
                this.busy = false;
                return;
            }
            typing.remove();
            const answer = payload.answer;
            this.history.push({ role: 'user', content: message }, { role: 'assistant', content: `${answer.spoken_reply} ${answer.summary}`.trim() });
            this.history = this.history.slice(-12);
            this.renderAnswer(answer);
            pollEvents();
            this.busy = false;
        },
        renderAnswer(a, { speak = true } = {}) {
            const [chip, label] = this.urgencyMeta[a.urgency] || this.urgencyMeta.monitor;
            const causes = (a.possible_causes || []).map((c) => {
                const pct = Math.max(0, Math.min(100, Number(c.likelihood) || 0));
                return `<div class="mb-2"><div class="flex justify-between text-[12px]"><span class="text-white">${escapeHtml(c.name)} ${c.space_related ? '<span class="chip chip-violet !text-[8px] !py-0 ml-1">UZAY</span>' : ''}</span><span class="font-mono text-slate-400">%${pct}</span></div>
                    <div class="h-1.5 rounded-full bg-[#0b1120] overflow-hidden mt-1"><div class="h-full rounded-full bg-gradient-to-r from-violet-400 to-cyan-400 transition-all duration-1000" style="width:0" data-w="${pct}"></div></div>
                    ${c.reason ? `<div class="text-[11px] text-slate-500 mt-0.5">${escapeHtml(c.reason)}</div>` : ''}</div>`;
            }).join('');
            const list = (items, icon) => (items || []).map((s) => `<li class="flex gap-2 text-[13px] text-slate-200"><span>${icon}</span><span>${escapeHtml(s)}</span></li>`).join('');
            const steps = (a.immediate_steps || []).map((s) => `<label class="flex gap-2 items-start text-[13px] text-slate-200 cursor-pointer"><input type="checkbox" class="accent-emerald-400 mt-1"><span>${escapeHtml(s)}</span></label>`).join('');
            const meds = (a.medications || []).map((m) => `<div class="stat-tile !p-2"><div class="text-[12px] text-white font-semibold">💊 ${escapeHtml(m.name)}</div><div class="text-[11px] font-mono text-cyberGreen">${escapeHtml(m.dose || '')}</div>${m.note ? `<div class="text-[10px] text-slate-500">${escapeHtml(m.note)}</div>` : ''}</div>`).join('');
            const questions = (a.follow_up_questions || []).map((q) => `<button class="chip chip-cyan !py-1 hover:!bg-cyan-500/20" data-followup="${escapeHtml(q)}">↳ ${escapeHtml(q)}</button>`).join('');
            const related = a.related_condition ? `<button class="btn btn-ghost !py-1 !px-2.5 !text-[10px]" data-related="${escapeHtml(a.related_condition.id)}">${escapeHtml(a.related_condition.icon)} Atlas'ta aç: ${escapeHtml(a.related_condition.name)} ›</button>` : '';
            const protocolChip = a.protocol ? `<span class="chip chip-cyan !text-[9px]">📋 Protokol: ${escapeHtml(a.protocol.title)}</span>` : '';
            const flags = (a.red_flags || []).length ? `<div class="p-3 rounded-xl bg-rose-950/50 border border-rose-500/50 text-[12px] text-rose-200 mb-3">🚩 <strong>Kırmızı bayrak:</strong> ${a.red_flags.map(escapeHtml).join(', ')}</div>` : '';

            const el = this.bubble(`
                <div class="flex flex-wrap items-center gap-2 mb-2"><span class="chip ${chip}">${label}</span>${protocolChip}${related}</div>
                ${flags}
                ${insightHtml(a)}
                <div class="text-[15px] text-white leading-relaxed ai-caret" data-typewriter></div>
                <div class="ai-details opacity-0 transition-opacity duration-500">
                    ${a.summary ? `<p class="text-[12px] text-slate-400 mt-2">${escapeHtml(a.summary)}</p>` : ''}
                    ${causes ? `<div class="mt-4"><div class="stat-label mb-2">Olası nedenler</div>${causes}</div>` : ''}
                    ${solutionHtml(a, false)}
                    ${steps ? `<div class="mt-4"><div class="stat-label mb-2">Kontrol & tetkik adımları</div><div class="space-y-1.5">${steps}</div></div>` : ''}
                    ${(a.monitoring || []).length ? `<div class="mt-4"><div class="stat-label mb-2">İzlenecek</div><ul class="space-y-1">${list(a.monitoring, '📈')}</ul></div>` : ''}
                    ${questions ? `<div class="mt-4"><div class="stat-label mb-2">Daha iyi değerlendirmem için</div><div class="flex flex-wrap gap-1.5">${questions}</div></div>` : ''}
                    <div class="mt-3 text-[10px] font-mono text-slate-600">${a.engine.startsWith('gemini') ? '✨' : '🛰️'} ${escapeHtml(a.model)} · ${a.latency_ms} ms</div>
                </div>`);

            qsa('[data-followup]', el).forEach((b) => b.addEventListener('click', () => { $('ai-input').value = ''; $('ai-input').focus(); $('ai-input').placeholder = b.dataset.followup; }));
            qsa('[data-related]', el).forEach((b) => b.addEventListener('click', () => { conditions.selected = b.dataset.related; nav.go('conditions'); conditions.render(); }));

            setText('ai-latency', a.latency_ms >= 1000 ? `${(a.latency_ms / 1000).toFixed(1)} sn` : `${a.latency_ms} ms`);
            setText('ai-earth', fmtDelay(a.earth_round_trip_seconds));
            setText('ai-engine', a.engine.startsWith('gemini') ? `${a.model} (çevrimiçi)` : 'Aegis çevrimdışı kural motoru — model erişilemedi');
            setChip($('ai-model-chip'), a.engine.startsWith('gemini') ? 'chip-violet' : 'chip-gold', escapeHtml(a.model));

            const target = el.querySelector('[data-typewriter]');
            const text = a.spoken_reply || '';
            let i = 0;
            const type = () => {
                target.textContent = text.slice(0, i);
                i += 2;
                if (i <= text.length + 1) { setTimeout(type, 14); return; }
                target.classList.remove('ai-caret');
                const details = el.querySelector('.ai-details');
                details.classList.remove('opacity-0');
                qsa('[data-w]', el).forEach((bar) => { bar.style.width = `${bar.dataset.w}%`; });
                $('ai-transcript').scrollTo({ top: $('ai-transcript').scrollHeight, behavior: 'smooth' });
            };
            type();

            const isEmergency = a.urgency === 'emergency';
            if (isEmergency) {
                toast('🚨 AI uçuş doktoru: ACİL', a.spoken_reply, 'red');
                $('nav-alert-badge')?.classList.remove('hidden');
            }
            const done = () => {
                this.setOrb(isEmergency ? 'emergency' : 'idle', isEmergency ? 'ACİL durum işaretlendi — adımları uygula.' : 'Dinlemeye hazırım. Başka bir şikâyetin var mı?');
                if ($('ai-handsfree').checked && !isEmergency) setTimeout(() => this.startMic(), 400);
            };
            if (!speak) {
                this.setOrb(isEmergency ? 'emergency' : 'idle', isEmergency ? 'ACİL durum işaretlendi — adımları uygula.' : 'Dinlemeye hazırım.');
            } else if ($('ai-speak').checked) {
                voice.speak(text, { onstart: () => this.setOrb(isEmergency ? 'emergency' : 'speaking', 'Konuşuyorum…'), onend: done });
            } else {
                done();
            }
        },
        toggleMic() { this.listening ? this.stopMic() : this.startMic(); },
        startMic(target = 'panel') {
            this.target = target;
            const inputEl = () => $(this.target === 'widget' ? 'cw-input' : 'ai-input');
            const micEl = () => $(this.target === 'widget' ? 'cw-mic' : 'ai-mic');
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) {
                toast('Sesli giriş desteklenmiyor', 'Chrome veya Edge kullanın; metin kutusuna yazabilirsiniz.', 'gold');
                inputEl().focus();
                return;
            }
            if (this.listening || this.busy || chat.busy) return;
            if ('speechSynthesis' in window) speechSynthesis.cancel();
            const recognition = (this.recognition = new SR());
            recognition.lang = 'tr-TR';
            recognition.interimResults = true;
            recognition.continuous = false;
            let finalText = '';
            recognition.onresult = (event) => {
                let interim = '';
                for (const result of event.results) {
                    if (result.isFinal) finalText = result[0].transcript; else interim += result[0].transcript;
                }
                inputEl().value = finalText || interim;
            };
            recognition.onerror = (event) => {
                if (event.error === 'not-allowed') toast('Mikrofon izni gerekli', 'Tarayıcı adres çubuğundan mikrofona izin verin.', 'gold');
            };
            recognition.onend = () => {
                const target = this.target;
                this.stopMic(false);
                if (finalText.trim()) target === 'widget' ? chat.send(finalText) : this.send(finalText);
            };
            try { recognition.start(); } catch (e) { return; }
            this.listening = true;
            micEl().classList.add('recording');
            this.setOrb('listening', 'Dinliyorum… şikâyetini anlat.');
            chat.setOrb('listening');
            if (this.target === 'panel') this.startWave();
        },
        stopMic(abort = true) {
            if (abort && this.recognition) { try { this.recognition.stop(); } catch (e) {} }
            this.listening = false;
            $('ai-mic')?.classList.remove('recording');
            $('cw-mic')?.classList.remove('recording');
            if (!chat.busy && $('cw-orb')?.dataset.state === 'listening') chat.setOrb('idle');
            this.stopWave();
            if (!this.busy && $('ai-orb')?.dataset.state === 'listening') this.setOrb('idle');
        },
        async startWave() {
            const canvas = $('ai-wave');
            if (!navigator.mediaDevices?.getUserMedia || !canvas) return;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const ctxAudio = new (window.AudioContext || window.webkitAudioContext)();
                const analyser = ctxAudio.createAnalyser();
                analyser.fftSize = 256;
                ctxAudio.createMediaStreamSource(stream).connect(analyser);
                this.audio = { stream, ctxAudio, analyser, raf: null };
                canvas.style.opacity = '1';
                const ctx = canvas.getContext('2d');
                const data = new Uint8Array(analyser.frequencyBinCount);
                const draw = () => {
                    if (!this.audio) return;
                    canvas.width = canvas.offsetWidth * devicePixelRatio;
                    canvas.height = canvas.offsetHeight * devicePixelRatio;
                    analyser.getByteFrequencyData(data);
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    const bars = 48, w = canvas.width / bars;
                    for (let i = 0; i < bars; i++) {
                        const v = data[Math.floor((i / bars) * data.length * 0.7)] / 255;
                        const h = Math.max(2, v * canvas.height * 0.8);
                        ctx.fillStyle = `rgba(0,255,157,${0.15 + v * 0.6})`;
                        ctx.fillRect(i * w + w * 0.2, (canvas.height - h) / 2, w * 0.6, h);
                    }
                    this.audio.raf = requestAnimationFrame(draw);
                };
                draw();
            } catch (e) {}
        },
        stopWave() {
            if (!this.audio) return;
            cancelAnimationFrame(this.audio.raf);
            this.audio.stream.getTracks().forEach((t) => t.stop());
            this.audio.ctxAudio.close();
            this.audio = null;
            $('ai-wave').style.opacity = '0';
        },
        reset() {
            this.history = [];
            if ('speechSynthesis' in window) speechSynthesis.cancel();
            const box = $('ai-transcript');
            box.innerHTML = '';
            this.bubble(`Yeni görüşme başladı. Seni dinliyorum, ${escapeHtml(BOOT.crew[state.member].name)}.`);
            this.setOrb('idle', 'Şikâyetini sesli anlat; canlı vitallerini görerek değerlendiririm.');
        },
    };

    /* ---------------------------------------------------------------- Floating live chat */
    const chat = {
        isOpen: false,
        busy: false,
        unread: 0,
        greeted: false,
        teaserTimer: null,
        now: () => new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }),
        setOrb(stateName) { $('cw-orb')?.setAttribute('data-state', stateName); },
        toggle() { this.isOpen ? this.close() : this.open(); },
        open() {
            this.isOpen = true;
            $('chat-widget').classList.add('open');
            this.hideTeaser();
            this.unread = 0;
            this.updateBadge();
            if (!this.greeted) this.greet();
            setTimeout(() => $('cw-input')?.focus(), 250);
        },
        close() {
            this.isOpen = false;
            $('chat-widget').classList.remove('open');
            if (assistant.listening) assistant.stopMic();
        },
        expand() {
            this.close();
            nav.go('assistant');
        },
        hideTeaser() { clearTimeout(this.teaserTimer); $('cw-teaser')?.classList.add('hidden'); },
        showTeaser(text) {
            if (this.isOpen || document.body.classList.contains('on-assistant')) return;
            setText('cw-teaser-text', text);
            $('cw-teaser').classList.remove('hidden');
            clearTimeout(this.teaserTimer);
            this.teaserTimer = setTimeout(() => this.hideTeaser(), 12000);
        },
        updateBadge() {
            const badge = $('cw-badge');
            badge.textContent = this.unread;
            badge.classList.toggle('hidden', this.unread === 0);
        },
        updateContext({ member, telemetry: t }) {
            if (!t) return;
            setText('cw-member', member.name);
            setText('cw-vitals', `♥ ${t.heart_rate} · SpO₂ ${t.spo2}% · ${t.body_temp_c}°C`);
        },
        row(html, who = 'bot', extraClass = '') {
            const row = document.createElement('div');
            row.className = `cw-row ${who === 'me' ? 'me' : ''} ${extraClass}`;
            row.innerHTML = `<div class="cw-av">AI</div><div><div class="cw-msg">${html}</div><div class="cw-meta">${this.now()}${who === 'me' ? ' · ✓✓' : ' · Aegis'}</div></div>`;
            const box = $('cw-messages');
            box.appendChild(row);
            box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
            if (!this.isOpen && who !== 'me') { this.unread++; this.updateBadge(); }
            return row;
        },
        typing() { return this.row('<div class="ai-typing"><span></span><span></span><span></span></div>'); },
        greet() {
            this.greeted = true;
            const name = BOOT.crew[state.member].name;
            const t = this.typing();
            setTimeout(() => {
                t.remove();
                this.row(`Merhaba ${escapeHtml(name)} 👋 Ben <strong>Aegis</strong>, gemideki AI uçuş doktorun. Canlı vitallerini görüyorum. Nasıl hissediyorsun?`);
            }, 700);
        },
        async send(preset) {
            const input = $('cw-input');
            const message = (preset ?? input.value).trim();
            if (!message || this.busy || assistant.busy) return;
            if (!this.isOpen) this.open();
            input.value = '';
            this.busy = true;
            if ('speechSynthesis' in window) speechSynthesis.cancel();
            const image = attachments.take('widget');
            const thumb = image ? `<img src="${image}" class="w-32 rounded-lg mb-1.5" alt="Ek">` : '';
            this.row(thumb + escapeHtml(message), 'me');
            assistant.bubble(thumb + escapeHtml(message), 'user');
            const t = this.typing();
            this.setOrb('thinking');
            setText('cw-status', 'Yazıyor…');
            let payload;
            try {
                payload = await api('/api/assistant/consult', { method: 'POST', body: JSON.stringify({ member_id: state.member, message, history: assistant.history, image }) });
            } catch (e) {
                t.remove();
                this.row('Bağlantı hatası oluştu, tekrar dener misin?', 'bot', 'alert');
                this.setOrb('idle');
                setText('cw-status', 'Çevrimiçi · gemide, gecikmesiz');
                this.busy = false;
                return;
            }
            t.remove();
            const a = payload.answer;
            assistant.history.push({ role: 'user', content: message }, { role: 'assistant', content: `${a.spoken_reply} ${a.summary}`.trim() });
            assistant.history = assistant.history.slice(-12);
            this.renderAnswer(a);
            assistant.renderAnswer(a, { speak: false });
            pollEvents();
            this.busy = false;
        },
        renderAnswer(a) {
            const isEmergency = a.urgency === 'emergency';
            const [chip, label] = assistant.urgencyMeta[a.urgency] || assistant.urgencyMeta.monitor;
            const row = this.row(`
                <div class="mb-1.5"><span class="chip ${chip} !text-[9px]">${label}</span></div>
                <div data-type></div>
                ${insightHtml(a)}
                ${solutionHtml(a, true)}
                <button class="cw-link" data-expand>Detaylı değerlendirmeyi aç ›</button>
                ${(a.follow_up_questions || [])[0] ? `<div class="text-[12px] text-cyan-300 mt-2">↳ ${escapeHtml(a.follow_up_questions[0])}</div>` : ''}
            `, 'bot', isEmergency ? 'alert' : '');
            row.querySelector('[data-expand]').addEventListener('click', () => this.expand());
            const target = row.querySelector('[data-type]');
            const text = a.spoken_reply || a.summary || '';
            let i = 0;
            const type = () => {
                target.textContent = text.slice(0, i);
                i += 2;
                if (i <= text.length + 1) { setTimeout(type, 12); $('cw-messages').scrollTop = $('cw-messages').scrollHeight; }
            };
            type();
            setText('cw-model', a.engine.startsWith('gemini') ? a.model : 'Aegis çevrimdışı motor');
            setText('cw-status', a.engine.startsWith('gemini') ? `Çevrimiçi · ${(a.latency_ms / 1000).toFixed(1)} sn'de yanıtladı` : 'Çevrimdışı mod · gemideki kural motoru');
            const done = () => this.setOrb(isEmergency ? 'emergency' : 'idle');
            if ($('ai-speak')?.checked !== false) {
                voice.speak(text, { onstart: () => this.setOrb(isEmergency ? 'emergency' : 'speaking'), onend: done });
            } else {
                done();
            }
        },
        mic() { assistant.listening ? assistant.stopMic() : assistant.startMic('widget'); },
        proactive(memberName, ai) {
            const text = `⚠️ ${memberName} için kritik değerler algıladım: ${ai.message} Önerim: ${ai.recommended_action}`;
            this.row(escapeHtml(text), 'bot', 'alert');
            this.showTeaser(`${memberName} için kritik durum — hemen bak!`);
        },
    };

    /* ---------------------------------------------------------------- Photo attachments */
    const attachments = {
        panel: null,
        widget: null,
        ids: { panel: ['ai-attach-preview', 'ai-attach-img', 'ai-attach-size'], widget: ['cw-attach-preview', 'cw-attach-img', null] },
        pick(input, target) {
            const file = input.files?.[0];
            input.value = '';
            if (!file || !file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = () => {
                const img = new Image();
                img.onload = () => {
                    const scale = Math.min(1, 1024 / Math.max(img.width, img.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.width * scale);
                    canvas.height = Math.round(img.height * scale);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.82);
                    this[target] = dataUrl;
                    const [wrapId, imgId, sizeId] = this.ids[target];
                    $(imgId).src = dataUrl;
                    if (sizeId) setText(sizeId, `${canvas.width}×${canvas.height} · ${Math.round(dataUrl.length * 0.75 / 1024)} KB`);
                    $(wrapId).classList.remove('hidden');
                    $(wrapId).classList.add('flex');
                    $(target === 'widget' ? 'cw-input' : 'ai-input').placeholder = 'Fotoğraftaki şikâyetini anlat… (örn. kolumda kızarıklık var)';
                    toast('Fotoğraf eklendi', 'Şikâyetini yazıp gönder; Aegis görüntüyü inceleyecek.', 'cyan');
                };
                img.src = reader.result;
            };
            reader.readAsDataURL(file);
        },
        take(target) {
            const image = this[target];
            this.clear(target);
            return image;
        },
        clear(target) {
            this[target] = null;
            const [wrapId] = this.ids[target];
            $(wrapId)?.classList.add('hidden');
            $(wrapId)?.classList.remove('flex');
        },
    };

    /* ---------------------------------------------------------------- History & image insight block */
    function insightHtml(a) {
        const parts = [];
        if (a.image_findings) parts.push(`<div class="p-3 rounded-xl border border-cyan-500/30 bg-cyan-500/5 text-[12.5px] text-cyan-100"><div class="text-[10px] font-mono font-bold text-cyan-300 tracking-wider mb-1">📷 FOTOĞRAF BULGULARI${a.image_analyzed ? '' : ' (analiz yapılamadı)'}</div>${escapeHtml(a.image_findings)}</div>`);
        if (a.history_insight) parts.push(`<div class="p-3 rounded-xl border border-violet-500/30 bg-violet-500/5 text-[12.5px] text-violet-100"><div class="text-[10px] font-mono font-bold text-violet-300 tracking-wider mb-1">🧠 SAĞLIK GEÇMİŞİNDEN</div>${escapeHtml(a.history_insight)}</div>`);
        return parts.length ? `<div class="mt-2.5 space-y-2">${parts.join('')}</div>` : '';
    }

    /* ---------------------------------------------------------------- Pharmacy */
    const pharmacy = {
        data: null,
        async load() {
            try { this.data = await api('/api/medications'); } catch (e) { return; }
            this.render();
        },
        render() {
            if (!this.data) return;
            const s = this.data.summary;
            setText('ph-total', s.total_items);
            setText('ph-pct', s.stock_pct);
            setText('ph-low', s.low_count);
            setText('ph-out', s.out_count);
            setText('ph-member', BOOT.crew[state.member].name);
            const q = ($('ph-search')?.value || '').toLocaleLowerCase('tr');
            const statusMeta = { ok: ['chip-green', 'YETERLİ', 'from-emerald-400 to-cyan-400'], low: ['chip-gold', 'KRİTİK', 'from-amber-500 to-amber-300'], out: ['chip-red', 'TÜKENDİ', 'from-rose-600 to-rose-400'] };
            $('ph-grid').innerHTML = this.data.medications
                .filter((m) => !q || `${m.name} ${m.category}`.toLocaleLowerCase('tr').includes(q))
                .map((m, i) => {
                    const [chip, label, bar] = statusMeta[m.status];
                    return `<div class="ph-card ${m.status !== 'ok' ? m.status : ''}" style="animation-delay:${i * 25}ms" data-ph="${m.id}">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1"><div class="font-semibold text-white text-[14px] truncate">${escapeHtml(m.name)} <span class="text-slate-500 font-mono text-[11px]">${escapeHtml(m.strength || '')}</span></div><div class="text-[11px] text-slate-500">${escapeHtml(m.category)} · ${escapeHtml(m.form)}</div></div>
                            <span class="chip ${chip} !text-[9px]">${label}</span>
                        </div>
                        <div class="flex items-end justify-between mt-3"><span class="font-space font-bold text-2xl text-white">${m.quantity}<span class="text-[11px] text-slate-500 font-mono"> / ${m.initial_quantity} ${escapeHtml(m.unit)}</span></span><span class="text-[10px] font-mono text-slate-500">SKT ${escapeHtml((m.expires_on || '').slice(0, 7))}</span></div>
                        <div class="h-1.5 rounded-full bg-[#0b1120] mt-2 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r ${bar} transition-all duration-700" style="width:${m.stock_pct}%"></div></div>
                        <button class="btn btn-ghost w-full mt-3 !py-1.5 !text-[11px]" ${m.quantity === 0 ? 'disabled style="opacity:.4"' : ''} data-dispense="${m.id}" data-name="${escapeHtml(m.name)}">💊 ${escapeHtml(BOOT.crew[state.member].name)}'e 1 ${escapeHtml(m.unit)} ver</button>
                    </div>`;
                }).join('');
            const log = this.data.dispenses;
            $('ph-log').innerHTML = log.length ? log.map((e) => `<div class="feed-item"><div class="w-8 h-8 shrink-0 rounded-lg bg-[#06080E] border border-[#1A2235] grid place-items-center">💊</div><div class="min-w-0"><div class="text-[13px] font-semibold ${e.severity === 'warning' ? 'text-amber-300' : 'text-cyberGreen'}">${escapeHtml(e.title)}</div><div class="text-[11px] text-slate-400 truncate">${escapeHtml(e.message || '')} · ${timeAgo(e.created_at)}</div></div></div>`).join('') : '<div class="text-[12px] text-slate-500 p-2">Henüz ilaç verilmedi.</div>';
        },
        async dispense(id, name, reason = 'Manuel', button = null) {
            if (button) { button.disabled = true; button.textContent = 'Veriliyor…'; }
            let payload;
            try {
                payload = await api(`/api/medications/${id}/dispense`, { method: 'POST', body: JSON.stringify({ member_id: state.member, amount: 1, reason }) });
            } catch (e) {
                toast('İlaç verilemedi', `${name} stokta yetersiz olabilir.`, 'red');
                if (button) { button.disabled = false; button.textContent = '💊 Tekrar dene'; }
                return;
            }
            const m = payload.medication;
            toast(`${m.name} verildi`, `${BOOT.crew[state.member].name} · kalan ${m.quantity} ${m.unit}`, 'green');
            if (payload.warning) { toast('⚠️ Kritik stok', payload.warning, 'gold'); voice.speak(payload.warning); }
            if (button) { button.textContent = `✓ Verildi · kalan ${m.quantity}`; button.classList.add('!text-cyberGreen'); }
            await this.load();
            document.querySelector(`[data-ph="${id}"]`)?.classList.add('flash');
            pollEvents();
        },
    };
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-dispense]');
        if (!button || button.disabled) return;
        const reason = button.dataset.reason || (state.currentPanel === 'pharmacy' ? 'Manuel' : 'AI uçuş doktoru önerisi');
        pharmacy.dispense(button.dataset.dispense, button.dataset.name, reason, button);
    });

    /* ---------------------------------------------------------------- AI crew briefing room */
    const briefing = {
        data: null,
        isOpen: false,
        paused: false,
        runId: 0,
        segmentIndex: 0,
        colors: { green: ['chip-green', 'YEŞİL', C.green], yellow: ['chip-gold', 'SARI', C.gold], red: ['chip-red', 'KIRMIZI', C.red] },
        async open(refresh = false) {
            this.isOpen = true;
            this.paused = false;
            setText('br-pause', '⏸');
            $('briefing').classList.add('open');
            $('br-loading').classList.remove('hidden');
            $('br-content').classList.add('hidden');
            $('br-subtitle').textContent = '';
            $('br-progress').style.width = '0';
            setText('br-headline', 'Brifing hazırlanıyor…');
            setText('br-meta', 'Mürettebat vitalleri, risk projeksiyonları, son 24 saat, uzay havası ve eczane analiz ediliyor');
            setChip($('br-status'), 'chip-cyan', '<span class="dot dot-live"></span>ANALİZ');
            $('br-orb').dataset.state = 'thinking';
            chat.close();
            const runId = ++this.runId;
            let payload;
            try {
                payload = await api(`/api/assistant/briefing${refresh ? '?refresh=1' : ''}`);
            } catch (e) {
                setText('br-headline', 'Brifing alınamadı. Lütfen tekrar deneyin.');
                $('br-orb').dataset.state = 'emergency';
                return;
            }
            if (runId !== this.runId || !this.isOpen) return;
            this.data = payload.briefing;
            this.render();
            this.play();
        },
        render() {
            const b = this.data;
            const [chip, label] = this.colors[b.overall_status] || this.colors.green;
            setText('br-headline', b.headline);
            setText('br-meta', `${b.mission.name} · Gün ${b.mission.day} · Mars'a ${b.mission.days_to_mars} gün · ${new Date(b.generated_at).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })}`);
            setChip($('br-status'), chip, `DURUM: ${label}`);
            setText('br-engine', `${b.engine === 'gemini' ? '✨' : '🛰️'} ${b.model} · ${(b.latency_ms / 1000).toFixed(1)} sn`);
            $('br-crew').innerHTML = b.crew.map((m, i) => {
                const crewInfo = BOOT.crew[m.id] || { name: m.id, initials: '?', role: '' };
                const v = b.vitals[m.id] || {};
                const color = (this.colors[m.status] || this.colors.green)[2];
                return `<div class="br-member" data-seg="crew-${m.id}" data-status="${m.status}" style="animation-delay:${i * 90}ms">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl grid place-items-center font-space font-bold text-white" style="background:${hexA(color, 0.15)};border:1px solid ${hexA(color, 0.5)}">${escapeHtml(crewInfo.initials)}</div>
                        <div class="min-w-0 flex-1"><div class="font-semibold text-white truncate">${escapeHtml(crewInfo.name)}</div><div class="text-[11px] text-slate-500 truncate">${escapeHtml(crewInfo.role)}</div></div>
                        <span class="br-status-dot" style="color:${color}"></span>
                    </div>
                    <div class="text-[13px] font-semibold mt-3" style="color:${color}">${escapeHtml(m.headline || '')}</div>
                    <div class="grid grid-cols-3 gap-1.5 mt-2 font-mono text-[10px] text-slate-400">
                        <div>♥ <span class="text-white">${v.heart_rate ?? '—'}</span></div><div>SpO₂ <span class="text-white">${v.spo2 ?? '—'}</span></div><div>İndeks <span class="text-white">${v.health_index ?? '—'}</span></div>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-2"><span class="text-cyan-300 font-mono text-[10px]">ODAK</span> ${escapeHtml(m.focus || '')}</div>
                    <div class="mt-2 space-y-0.5">${(m.actions || []).map((a) => `<div class="text-[11px] text-slate-300">✓ ${escapeHtml(a)}</div>`).join('')}</div>
                </div>`;
            }).join('');
            $('br-priorities').innerHTML = b.priorities.map((p, i) => `<div class="flex gap-3"><span class="w-6 h-6 rounded-lg shrink-0 grid place-items-center text-[11px] font-mono font-bold bg-[#142620] text-cyberGreen">${i + 1}</span><div><div class="text-[13px] text-white font-semibold">${escapeHtml(p.title || '')}</div><div class="text-[11px] text-slate-400">${escapeHtml(p.detail || '')}${p.owner ? ` · <span class="text-cyan-300">${escapeHtml(p.owner)}</span>` : ''}</div></div></div>`).join('');
            $('br-schedule').innerHTML = b.schedule.map((s) => `<div class="flex gap-3 text-[12px]"><span class="font-mono text-cyberGreen w-12 shrink-0">${escapeHtml(s.time || '')}</span><span class="text-slate-200 flex-1">${escapeHtml(s.item || '')}</span><span class="text-slate-500 text-[11px]">${escapeHtml(s.who || '')}</span></div>`).join('');
            setText('br-weather', b.space_weather);
            setText('br-pharmacy', b.pharmacy);
            $('br-loading').classList.add('hidden');
            $('br-content').classList.remove('hidden');
        },
        segments() {
            const b = this.data;
            return [
                { seg: null, text: b.opening },
                ...b.crew.map((m) => ({ seg: `crew-${m.id}`, text: m.spoken })),
                { seg: 'priorities', text: `Günün öncelikleri: ${b.priorities.map((p) => p.title).join(', ')}.` },
                { seg: 'schedule', text: `Tıbbi programda ${b.schedule.length} madde var; ilk olarak saat ${b.schedule[0]?.time || ''}'de ${b.schedule[0]?.item || ''}.` },
                { seg: 'weather', text: b.space_weather },
                { seg: 'pharmacy', text: b.pharmacy },
                { seg: null, text: b.closing },
            ].filter((s) => s.text);
        },
        async play() {
            const runId = ++this.runId;
            const segments = this.segments();
            qsa('#briefing [data-seg]').forEach((el) => el.classList.remove('active', 'done'));
            for (let i = 0; i < segments.length; i++) {
                if (runId !== this.runId || !this.isOpen) return;
                await waitFor(() => !this.paused || runId !== this.runId, Infinity);
                this.segmentIndex = i;
                const { seg, text } = segments[i];
                qsa('#briefing [data-seg].active').forEach((el) => { el.classList.remove('active'); el.classList.add('done'); });
                const el = seg ? document.querySelector(`#briefing [data-seg="${seg}"]`) : null;
                el?.classList.add('active');
                el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                $('br-progress').style.width = `${((i + 1) / segments.length) * 100}%`;
                this.subtitle(text);
                $('br-orb').dataset.state = 'speaking';
                await this.speakWithKaraoke(text, runId);
            }
            if (runId !== this.runId) return;
            qsa('#briefing [data-seg].active').forEach((el) => { el.classList.remove('active'); el.classList.add('done'); });
            $('br-orb').dataset.state = 'idle';
            this.segmentIndex = segments.length;
        },
        subtitle(text) {
            $('br-subtitle').innerHTML = text.split(/\s+/).map((w) => `<span class="w">${escapeHtml(w)}</span>`).join(' ');
        },
        speakWithKaraoke(text, runId) {
            const words = qsa('#br-subtitle .w');
            const msPerWord = 330;
            let i = 0;
            const timer = setInterval(() => {
                if (runId !== this.runId) { clearInterval(timer); return; }
                if (!this.paused && i < words.length) words[i++].classList.add('on');
            }, msPerWord);
            return Promise.race([
                new Promise((resolve) => voice.speak(text, { onend: resolve })),
                sleep(words.length * msPerWord + 2500),
            ]).then(() => { clearInterval(timer); words.forEach((w) => w.classList.add('on')); return sleep(450); });
        },
        togglePause() {
            this.paused = !this.paused;
            setText('br-pause', this.paused ? '▶' : '⏸');
            if ('speechSynthesis' in window) this.paused ? speechSynthesis.pause() : speechSynthesis.resume();
        },
        replay() { if (this.data) { if ('speechSynthesis' in window) speechSynthesis.cancel(); this.paused = false; setText('br-pause', '⏸'); this.play(); } },
        refresh() { if ('speechSynthesis' in window) speechSynthesis.cancel(); this.open(true); },
        close() {
            this.isOpen = false;
            this.runId++;
            $('briefing').classList.remove('open');
            if ('speechSynthesis' in window) speechSynthesis.cancel();
            pollEvents();
        },
    };

    /* ---------------------------------------------------------------- Mission Detective */
    const detective = {
        data: null,
        prev: null,
        busy: false,
        dragBound: false,
        hypothesisColor: { environment: C.cyan, individual: C.gold, sensor: C.violet },
        hypothesisIcon: { environment: '🫁', individual: '🤒', sensor: '📟' },
        async load() {
            this.bindDrag();
            try { this.apply(await api('/api/detective'), true); } catch (e) {}
        },
        async post(path, body, silent = false) {
            if (this.busy) return;
            this.busy = true;
            try {
                this.apply(await api(path, { method: 'POST', body: JSON.stringify(body) }), silent);
            } catch (e) {
                toast('Dedektif isteği başarısız', '', 'red');
            } finally {
                this.busy = false;
            }
        },
        setScenario(scenario, sensorFault = false, silent = false) {
            this.prev = null;
            return this.post('/api/detective/scenario', { scenario, sensor_fault: sensorFault }, silent);
        },
        toggleFault(force, silent = false) {
            const next = typeof force === 'boolean' ? force : !this.data?.state.sensor_fault;
            if (next && this.data?.state.scenario === 'nominal') {
                return this.post('/api/detective/scenario', { scenario: 'sensor', sensor_fault: false }, silent);
            }
            return this.post('/api/detective/sensor-fault', { active: next }, silent);
        },
        runCheck(check, silent = false) { return this.post('/api/detective/check', { check }, silent); },
        runNext(silent = false) {
            const next = this.data?.analysis.next_check;
            return next ? this.runCheck(next.id, silent) : Promise.resolve();
        },
        async runDemo() {
            nav.go('detective');
            starfield.boost(1);
            toast('🚨 Beklenmedik sağlık değişimi', 'İki astronotta belirti — kök neden analizi başladı.', 'red');
            await this.setScenario('cabin');
        },
        apply(payload, silent) {
            const previous = this.data;
            this.data = payload;
            this.render(previous);
            if (silent || !previous) return;
            const a = payload.analysis;
            if (a.mode !== 'investigating') return;
            const lead = a.hypotheses[0];
            const next = a.next_check ? ` Sıradaki kontrol: ${a.next_check.title}.` : '';
            voice.speak(`${a.confident ? 'Olası neden' : 'Değerlendirme belirsiz. Önde olan'}: ${lead.short}, yüzde ${lead.probability}.${next}`);
        },
        render(previous) {
            const d = this.data;
            const a = d.analysis;
            qsa('[data-scenario]').forEach((b) => b.classList.toggle('!border-cyberGreen', b.dataset.scenario === d.state.scenario));
            const faultBtn = $('dt-fault-btn');
            faultBtn.textContent = d.state.sensor_fault ? '✓ Sensör arızası aktif — kaldır' : '⚡ Sensör arızasını simüle et';

            // Station
            $('dt-plane').innerHTML = Object.values(d.modules).map((m) => {
                const crewHere = Object.values(d.crew).filter((c) => c.location === m.id);
                return `<div class="st-module ${m.status} ${a.focus_module === m.id ? 'focus' : ''}" style="grid-column:${m.col + 1};grid-row:${m.row + 1}" title="${escapeHtml(m.name)}">
                    <div class="st-name">${escapeHtml(m.name)}</div>
                    <div class="st-reading">${m.co2_primary_mmhg}<span class="st-sub"> mmHg</span></div>
                    <div class="st-sub">yedek <span class="${m.sensors_agree ? '' : 'st-sensor-bad'}">${m.co2_backup_mmhg}${m.sensors_agree ? '' : ' ⚠'}</span> · ${m.temp_c}°C · fan %${m.fan_flow_pct}</div>
                    <div class="st-crew">${crewHere.map((c) => `<div class="st-avatar ${c.symptomatic ? 'sick' : ''}" title="${escapeHtml(c.name)}">${escapeHtml(c.initials)}</div>`).join('')}</div>
                </div>`;
            }).join('');

            // CO₂ chart for focus module
            const focus = d.modules[a.focus_module || 'exercise'];
            const ch = chart('dt-co2-chart');
            if (ch && focus) {
                ch.setOption({
                    grid: { top: 26, right: 14, bottom: 22, left: 34 },
                    tooltip: { ...tooltip, valueFormatter: (v) => `${v} mmHg` },
                    legend: { top: 0, right: 0, textStyle: { color: '#94A3B8', fontSize: 10 } },
                    xAxis: { type: 'category', boundaryGap: false, data: focus.series.map((p) => (p.minutes_ago ? `-${p.minutes_ago}dk` : 'şimdi')), ...axisStyle },
                    yAxis: { type: 'value', min: 0, max: 8, ...axisStyle },
                    series: [
                        { name: `${focus.name} · birincil`, type: 'line', smooth: true, symbol: 'none', data: focus.series.map((p) => p.primary), lineStyle: { color: C.red, width: 2 }, areaStyle: { color: gradient(C.red, 0.15) },
                            markLine: { symbol: 'none', silent: true, data: [{ yAxis: 2.5, label: { formatter: 'Law 2014 hedefi 2.5', color: C.green, fontSize: 9 }, lineStyle: { color: C.green, type: 'dashed' } }, { yAxis: 4, label: { formatter: 'Alarm 4.0', color: C.gold, fontSize: 9 }, lineStyle: { color: C.gold, type: 'dashed' } }] } },
                        { name: 'Bağımsız yedek sensör', type: 'line', smooth: true, symbol: 'none', data: focus.series.map((p) => p.backup), lineStyle: { color: C.cyan, width: 2, type: 'dotted' } },
                    ],
                });
            }

            // Verdict
            const modeChip = a.mode === 'nominal' ? ['chip-green', 'NOMİNAL'] : a.confident ? ['chip-violet', 'GÜÇLÜ KANIT'] : ['chip-gold', 'BELİRSİZ · KONTROL GEREKLİ'];
            setChip($('dt-mode'), modeChip[0], modeChip[1]);
            setText('dt-headline', a.headline);
            setText('dt-disclaimer', a.disclaimer || '');
            $('dt-hypotheses').innerHTML = a.hypotheses.map((h, i) => `
                <div>
                    <div class="flex justify-between text-[12.5px] mb-1"><span class="${i === 0 ? 'text-white font-semibold' : 'text-slate-400'}">${this.hypothesisIcon[h.id]} ${escapeHtml(h.label)}</span><span class="font-mono font-bold" style="color:${this.hypothesisColor[h.id]}">%${h.probability}</span></div>
                    <div class="dt-bar"><div data-w="${h.probability}" style="width:${previous?.analysis.hypotheses.find((p) => p.id === h.id)?.probability ?? 0}%;background:${this.hypothesisColor[h.id]};box-shadow:0 0 12px ${this.hypothesisColor[h.id]}"></div></div>
                </div>`).join('') || '<div class="text-[13px] text-slate-400">Mürettebat ve kabin ortamı nominal. Bir senaryo başlatın.</div>';
            requestAnimationFrame(() => requestAnimationFrame(() => qsa('#dt-hypotheses [data-w]').forEach((bar) => { bar.style.width = `${bar.dataset.w}%`; })));

            // What changed
            const changeEl = $('dt-change');
            const before = previous?.analysis.hypotheses?.[0];
            const after = a.hypotheses[0];
            const lastEvidence = a.evidence[a.evidence.length - 1];
            if (before && after && previous.state.scenario === d.state.scenario && (before.id !== after.id || Math.abs(before.probability - after.probability) >= 8)) {
                const prevSame = previous.analysis.hypotheses.find((h) => h.id === after.id)?.probability ?? 0;
                changeEl.innerHTML = `🔄 <strong>Değerlendirme güncellendi:</strong> ${escapeHtml(after.short)} %${prevSame} → %${after.probability}${before.id !== after.id ? ` (önceki lider: ${escapeHtml(before.short)})` : ''}<br><span class="text-cyan-300">Neden:</span> ${escapeHtml(lastEvidence?.text || '')}`;
                changeEl.classList.remove('hidden');
                $('dt-verdict-card').classList.remove('flash'); void $('dt-verdict-card').offsetWidth; $('dt-verdict-card').classList.add('flash');
            } else if (!previous || previous.state.scenario !== d.state.scenario) {
                changeEl.classList.add('hidden');
            }

            // Next check
            const next = a.next_check;
            setText('dt-next-title', next ? next.title : a.mode === 'nominal' ? 'Gerek yok — her şey nominal' : 'Yeterli kanıt toplandı');
            setText('dt-next-reason', next ? next.reason : a.mode === 'nominal' ? 'Bir senaryo başlatarak analizi görün.' : 'Önceden onaylanmış prosedürü uygulayın.');
            $('dt-next-btn').disabled = !next;
            $('dt-next-btn').style.opacity = next ? '1' : '.4';

            // Evidence chain
            const short = { environment: 'Kabin', individual: 'Bireysel', sensor: 'Sensör' };
            $('dt-evidence').innerHTML = a.evidence.map((e, i) => `
                <div class="dt-evidence ${e.kind.startsWith('check') ? 'check' : ''}" style="animation-delay:${i * 60}ms">
                    <span class="shrink-0 text-lg">${e.kind.startsWith('check') ? '🧪' : '👁️'}</span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[13px] text-slate-200">${escapeHtml(e.text)}</div>
                        <div class="flex flex-wrap gap-1 mt-1.5">${Object.entries(e.ratios).map(([h, r]) => `<span class="dt-ratio ${r >= 1 ? 'up' : 'down'}">${r >= 1 ? '▲' : '▼'} ${short[h]} ×${r}</span>`).join('')}</div>
                    </div>
                </div>`).join('') || '<div class="text-[12px] text-slate-500">Kanıt yok.</div>';

            // Checks
            $('dt-checks').innerHTML = a.checks.map((c) => `
                <button class="w-full flex items-center gap-2 p-2.5 rounded-xl text-left text-[12.5px] border transition ${c.done ? 'border-emerald-500/30 bg-emerald-500/5 text-slate-300' : c.recommended ? 'border-cyan-500/60 bg-cyan-500/10 text-white' : 'border-[#1A2235] bg-[#06080E] text-slate-300 hover:border-slate-600'}" ${c.done || a.mode === 'nominal' ? 'disabled' : ''} onclick="VB.detective.runCheck('${c.id}')">
                    <span>${c.done ? '✅' : c.recommended ? '🧭' : '○'}</span><span class="flex-1">${escapeHtml(c.title)}</span><span class="font-mono text-[10px] text-slate-500">${c.minutes} dk</span>
                </button>`).join('');

            // Procedure
            setText('dt-proc-title', a.procedure?.title || 'Aktif prosedür yok.');
            $('dt-proc').innerHTML = (a.procedure?.steps || []).map((s) => `<label class="flex gap-2 text-[12.5px] text-slate-200 cursor-pointer"><input type="checkbox" class="accent-emerald-400 mt-0.5">${escapeHtml(s)}</label>`).join('');

            // Crew
            $('dt-crew').innerHTML = Object.values(d.crew).map((c) => `
                <div class="card p-4 ${c.symptomatic ? '!border-rose-500/50' : ''}">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl grid place-items-center font-space font-bold ${c.symptomatic ? 'bg-rose-500/20 text-rose-200' : 'bg-emerald-500/10 text-cyberGreen'}">${escapeHtml(c.initials)}</div>
                        <div class="min-w-0 flex-1"><div class="text-white font-semibold text-[13px] truncate">${escapeHtml(c.name)}</div><div class="text-[11px] text-slate-500 truncate">📍 ${escapeHtml(d.modules[c.location]?.name || c.location)} · son 6 sa %${c.dwell[c.location]}</div></div>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-2">${c.symptoms.length ? c.symptoms.map((s) => `<span class="chip chip-red !text-[9px]">${escapeHtml(s)}</span>`).join('') : '<span class="chip chip-green !text-[9px]">Belirti yok</span>'}</div>
                    <div class="grid grid-cols-4 gap-1 mt-2 font-mono text-[10px] text-slate-400"><div>♥ <span class="text-white">${c.vitals.heart_rate}</span></div><div>RR <span class="text-white">${c.vitals.respiratory_rate}</span></div><div>🌡 <span class="text-white">${c.vitals.body_temp_c}</span></div><div>O₂ <span class="text-white">${c.vitals.spo2}</span></div></div>
                </div>`).join('');
        },
        bindDrag() {
            if (this.dragBound) return;
            const station = $('dt-station');
            if (!station) return;
            this.dragBound = true;
            let start = null;
            station.addEventListener('pointerdown', (e) => { start = { x: e.clientX, y: e.clientY, rz: parseFloat(getComputedStyle(station).getPropertyValue('--rz')), rx: parseFloat(getComputedStyle(station).getPropertyValue('--rx')) }; station.setPointerCapture(e.pointerId); });
            station.addEventListener('pointermove', (e) => {
                if (!start) return;
                station.style.setProperty('--rz', `${start.rz + (e.clientX - start.x) * 0.4}deg`);
                station.style.setProperty('--rx', `${Math.max(20, Math.min(72, start.rx - (e.clientY - start.y) * 0.25))}deg`);
            });
            station.addEventListener('pointerup', () => { start = null; });
        },
    };

    /* ---------------------------------------------------------------- Member selection */
    function selectMember(memberId, goToCrew = false) {
        if (!BOOT.crew[memberId]) return;
        state.member = memberId;
        state.hrHistory = [];
        state.spo2History = [];
        state.lastStatus = null;
        $('crew-select').value = memberId;
        qsa('[data-crew-dot]').forEach((el) => el.classList.toggle('!border-cyberGreen', el.dataset.crewDot === memberId));
        qsa('[data-crew-card]').forEach((el) => { el.classList.toggle('ring-1', el.dataset.crewCard === memberId); el.classList.toggle('ring-cyberGreen/40', el.dataset.crewCard === memberId); });
        pollTelemetry();
        if (conditions.data) conditions.load();
        if (pharmacy.data) pharmacy.render();
        if (goToCrew) nav.go('crew');
        toast('Astronot seçildi', `${BOOT.crew[memberId].name} · ${BOOT.crew[memberId].role}`, 'cyan');
    }

    /* ---------------------------------------------------------------- Keyboard */
    addEventListener('keydown', (e) => {
        if (['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName) || e.metaKey || e.ctrlKey || e.altKey) return;
        if (e.code === 'Space' && state.currentPanel === 'assistant') { e.preventDefault(); if (!e.repeat) assistant.startMic(); return; }
        const n = e.key === '0' ? 10 : parseInt(e.key, 10);
        if (n >= 1 && n <= PANELS.length) nav.go(PANELS[n - 1]);
        else if (e.key.toLowerCase() === 'd') tour.active ? tour.stop() : tour.start();
        else if (e.key.toLowerCase() === 'p' && tour.active) tour.togglePause();
        else if (e.key.toLowerCase() === 'e') emergency.toggle();
        else if (e.key.toLowerCase() === 'b') briefing.isOpen ? briefing.close() : briefing.open();
        else if (e.key === 'Escape') { if (briefing.isOpen) { briefing.close(); return; } tour.stop(); chat.close(); }
        else if (e.key === 'ArrowRight' && tour.active) tour.next();
        else if (e.key === 'ArrowLeft' && tour.active) tour.prev();
    });

    /* ---------------------------------------------------------------- Init */
    addEventListener('keyup', (e) => {
        if (e.code === 'Space' && state.currentPanel === 'assistant' && assistant.listening && document.activeElement?.tagName !== 'INPUT') assistant.stopMic();
    });
    if ('speechSynthesis' in window) speechSynthesis.onvoiceschanged = () => speechSynthesis.getVoices();

    window.VB = { nav, tour, emergency, voice, pvt, countermeasures, conditions, assistant, chat, attachments, pharmacy, briefing, detective, report, selectMember, state };
    setTimeout(() => chat.showTeaser('Bir şikâyetin mi var? Bana yaz ya da sesli söyle 🎙️'), 7000);

    boot.run();
    const initial = (location.hash || '#overview').slice(1);
    nav.go(PANELS.includes(initial) ? initial : 'overview', { silent: true });
    trajectory.render(state.mission);
    trajectory.animateSignal();
    renderMission(state.mission);
    countermeasures.sync();
    selectMember('astronot1');
    pollCrew();
    pollEvents();
    loadSpaceWeather();
    setInterval(pollTelemetry, 2000);
    setInterval(pollCrew, 5000);
    setInterval(pollEvents, 6000);
    setInterval(loadSpaceWeather, 10 * 60 * 1000);
    addEventListener('hashchange', () => nav.go(location.hash.slice(1), { silent: true }));
})();
