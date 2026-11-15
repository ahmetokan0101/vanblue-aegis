<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VanBlue AegisHealth OS · Derin Uzay Mürettebat Sağlığı</title>
    <meta name="description" content="Mars görevleri için otonom, Dünya'dan bağımsız astronot sağlık izleme ve triage sistemi — NASA HRP, MSL RAD, OSDR ve DONKI verileriyle.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&family=Orbitron:wght@600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        voidBg: '#05070D', panelBg: '#0C101B', panelBorder: '#1A2235',
                        cyberGreen: '#00FF9D', cyberMint: '#34D399', cyberCyan: '#00E5FF',
                        cyberGold: '#FBBF24', cyberRed: '#FF0055', cyberViolet: '#A78BFA', nasaBlue: '#0B3D91',
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        space: ['"Space Grotesk"', 'sans-serif'],
                        tech: ['"Orbitron"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="{{ asset('css/vanblue.css') }}?v={{ filemtime(public_path('css/vanblue.css')) }}">
</head>
<body class="font-sans antialiased">

    {{-- Boot sequence --}}
    <div id="boot">
        <div class="boot-logo"><span class="font-tech font-black text-2xl text-cyberGreen">VB</span></div>
        <div class="mt-6 font-tech text-white tracking-[0.3em] text-sm">AEGISHEALTH OS</div>
        <div class="mt-5 h-[110px] w-[320px] font-mono text-[11px] text-slate-400 space-y-1" id="boot-log"></div>
        <div class="boot-bar mt-3"><div id="boot-progress"></div></div>
        <button onclick="VB.boot.finish()" class="mt-6 text-[10px] font-mono text-slate-600 hover:text-slate-300">ATLA ›</button>
    </div>

    <canvas id="starfield"></canvas>
    <div class="nebula"></div>
    <div class="vignette"></div>

    <div class="app-shell">
        @include('partials.sidebar')

        <div class="main">
            @include('partials.topbar')

            <main class="p-4 lg:p-6 max-w-[1680px] mx-auto">
                @include('panels.overview')
                @include('panels.crew')
                @include('panels.radiation')
                @include('panels.triage')
                @include('panels.detective')
                @include('panels.assistant')
                @include('panels.conditions')
                @include('panels.countermeasures')
                @include('panels.pharmacy')
                @include('panels.data')
                @include('panels.report')
                @include('panels.story')
            </main>

            <footer class="px-6 py-4 border-t border-[#1A2235] text-[11px] font-mono text-slate-500 flex flex-wrap gap-2 justify-between">
                <span>Takım <strong class="text-slate-300">VanBlue</strong> · NASA Space Apps Challenge 2026 · Van</span>
                <span>Veri: NASA-STD-3001 · MSL RAD · NASA OSDR (canlı) · NASA DONKI · Stavnichuk &amp; Corlett 2020</span>
                <span>Kısayollar: <kbd class="text-slate-300">1-9</kbd> panel · <kbd class="text-slate-300">D</kbd> demo · <kbd class="text-slate-300">E</kbd> alarm</span>
            </footer>
        </div>
    </div>

    {{-- Demo tour caption --}}
    <div id="tour-spot"></div>

    <div id="tour-caption">
        <div class="card p-4 border-cyan-500/40">
            <div class="flex items-center gap-3 mb-2">
                <span class="chip chip-cyan"><span class="dot dot-live"></span>JÜRİ TURU · <span id="tour-chapter">—</span></span>
                <span class="text-[10px] font-mono text-slate-500" id="tour-step">1/15</span>
                <span class="ml-auto flex gap-1.5">
                    <button onclick="VB.tour.prev()" class="btn btn-ghost !py-1 !px-2.5" title="Geri (←)">‹</button>
                    <button onclick="VB.tour.togglePause()" class="btn btn-ghost !py-1 !px-2.5" title="Duraklat (P)" id="tour-pause">⏸</button>
                    <button onclick="VB.tour.next()" class="btn btn-ghost !py-1 !px-2.5" title="İleri (→)">›</button>
                    <button onclick="VB.tour.stop()" class="btn btn-ghost !py-1 !px-2.5" title="Bitir (Esc)">✕</button>
                </span>
            </div>
            <div id="tour-title" class="font-space font-bold text-white text-lg leading-snug"></div>
            <p id="tour-text" class="text-[13.5px] text-slate-300 leading-relaxed mt-1"></p>
            <div class="flex items-center gap-3 mt-3">
                <div id="tour-dots" class="flex gap-1.5 flex-1"></div>
            </div>
            <div class="tour-progress mt-2"><div id="tour-bar"></div></div>
        </div>
    </div>

    <div id="tour-cover">
        <div id="tour-cover-intro" class="text-center max-w-3xl px-6">
            <div class="boot-logo mx-auto"><span class="font-tech font-black text-2xl text-cyberGreen">VB</span></div>
            <div class="mt-6 text-[11px] font-mono text-cyan-300 tracking-[0.35em]">NASA SPACE APPS CHALLENGE 2026 · TAKIM VANBLUE</div>
            <h2 class="font-space text-4xl lg:text-6xl font-bold text-white tracking-tight mt-3 leading-tight">Mars yolunda<br><span class="bg-gradient-to-r from-cyberGreen via-cyberCyan to-cyberViolet bg-clip-text text-transparent">yaşayan bir doktor.</span></h2>
            <p class="text-slate-400 mt-4">3 dakikalık jüri turu · 7 bölüm · canlı demo</p>
            <div class="flex flex-wrap justify-center gap-2 mt-6">
                @foreach (['Problem', 'Görev', 'Vücut', 'Kriz', 'AI Doktor', 'Önleme', 'Bilim'] as $i => $chapter)
                    <span class="chip chip-cyan tour-chip" style="animation-delay: {{ 0.15 * $i }}s">{{ $i + 1 }} · {{ $chapter }}</span>
                @endforeach
            </div>
            <div class="mt-10 flex items-center justify-center gap-4">
                <div class="font-tech text-5xl text-cyberGreen tabular-nums" id="tour-countdown">5</div>
                <button onclick="VB.tour.skipIntro?.()" class="btn btn-green">HEMEN BAŞLA ▶</button>
            </div>
        </div>
        <div id="tour-cover-outro" class="hidden text-center max-w-4xl px-6">
            <div class="text-[11px] font-mono text-cyan-300 tracking-[0.35em]">UZAYDAN DÜNYAYA</div>
            <h2 class="font-space text-4xl lg:text-5xl font-bold text-white tracking-tight mt-3">Uzayda hayat kurtaran sistem,<br><span class="bg-gradient-to-r from-cyberGreen to-cyberCyan bg-clip-text text-transparent">Dünya'da da hayat kurtarır.</span></h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-8 text-left">
                <div class="stat-tile"><div class="text-2xl">🏔️</div><div class="text-white font-semibold text-sm mt-1">Kırsal Van köyleri</div><div class="text-[11px] text-slate-500">Doktora uzak bölgeler</div></div>
                <div class="stat-tile"><div class="text-2xl">🏚️</div><div class="text-white font-semibold text-sm mt-1">Afet bölgeleri</div><div class="text-[11px] text-slate-500">Deprem sonrası sahra sağlığı</div></div>
                <div class="stat-tile"><div class="text-2xl">🚢</div><div class="text-white font-semibold text-sm mt-1">Gemi & denizaltı</div><div class="text-[11px] text-slate-500">İzole mürettebat</div></div>
                <div class="stat-tile"><div class="text-2xl">🧊</div><div class="text-white font-semibold text-sm mt-1">Antarktika</div><div class="text-[11px] text-slate-500">Kutup istasyonları</div></div>
            </div>
            <div class="flex flex-wrap justify-center gap-2 mt-6 text-[11px]">
                <span class="chip chip-green">NASA OSDR · canlı</span><span class="chip chip-green">NASA DONKI</span><span class="chip chip-cyan">MSL RAD</span><span class="chip chip-cyan">NASA-STD-3001</span><span class="chip chip-violet">Gemini AI + offline protokol</span>
            </div>
            <div class="font-space text-2xl text-white mt-8">Teşekkürler · <span class="text-cyberGreen">Takım VanBlue</span></div>
            <div class="flex justify-center gap-3 mt-6">
                <button onclick="VB.tour.stop(); VB.tour.start()" class="btn btn-cyan">↻ TURU TEKRARLA</button>
                <button onclick="VB.tour.stop()" class="btn btn-green">PANELLERE DÖN</button>
            </div>
        </div>
    </div>

    <div id="toasts"></div>

    @include('partials.chat-widget')
    @include('partials.briefing')

    <script>
        window.VB_BOOT = {
            crew: @json($crew),
            mission: @json($mission),
            assets: { astronaut: @json(asset('images/astronaut.png')) },
        };
    </script>
    <script src="{{ asset('js/vanblue.js') }}?v={{ filemtime(public_path('js/vanblue.js')) }}"></script>
</body>
</html>
