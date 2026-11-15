<section class="panel-view" id="panel-overview" data-title="Görev Kontrol">
    {{-- Hero --}}
    <div class="card reveal p-5 lg:p-6 mb-4 overflow-hidden">
        <div class="absolute -right-24 -top-24 w-80 h-80 rounded-full bg-gradient-to-br from-cyan-500/10 to-emerald-500/5 blur-2xl pointer-events-none"></div>
        <div class="flex flex-wrap items-end gap-6 relative">
            <div class="min-w-[260px] flex-1">
                <div class="flex items-center gap-2 mb-2">
                    <span class="chip chip-green" id="ov-status-chip"><span class="dot dot-live"></span>TÜM SİSTEMLER NOMİNAL</span>
                    <span class="chip chip-cyan">ORION + DSH</span>
                </div>
                <h2 class="font-space text-3xl lg:text-4xl font-bold text-white tracking-tight">Mars'a giden yolda,<br><span class="bg-gradient-to-r from-cyberGreen to-cyberCyan bg-clip-text text-transparent">doktor her an yanınızda.</span></h2>
                <p class="text-slate-400 text-sm mt-2 max-w-xl">Dünya ile iletişim <strong class="text-amber-300" id="ov-delay-inline">—</strong> gecikmeli. AegisHealth, mürettebatın sağlığını gemide, NASA standartlarıyla ve milisaniyeler içinde değerlendirir.</p>
                <button onclick="VB.briefing.open()" class="btn btn-green mt-4 group">
                    <span class="relative flex w-2.5 h-2.5"><span class="absolute inline-flex w-full h-full rounded-full bg-black/40 animate-ping"></span><span class="relative inline-flex w-2.5 h-2.5 rounded-full bg-black/70"></span></span>
                    🎙️ AEGIS GÜNLÜK BRİFİNGİNİ BAŞLAT
                </button>
            </div>
            <div class="w-full lg:w-[420px]">
                <div class="flex justify-between text-[11px] font-mono text-slate-400 mb-1.5">
                    <span>DÜNYA · {{ $mission['launch_date'] }}</span>
                    <span><span id="ov-day" class="text-white font-bold">{{ $mission['mission_day'] }}</span> / {{ $mission['transit_days'] }} GÜN</span>
                    <span>MARS</span>
                </div>
                <div class="progress !h-2.5"><div id="ov-progress" style="width: {{ $mission['progress_pct'] }}%"></div></div>
                <div class="flex justify-between text-[10px] font-mono text-slate-500 mt-1.5">
                    <span>%<span id="ov-progress-pct">{{ $mission['progress_pct'] }}</span> tamamlandı</span>
                    <span>Varışa <span id="ov-days-left" class="text-cyberCyan font-bold">{{ $mission['days_to_mars'] }}</span> gün</span>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI row --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <div class="stat-tile reveal card-lift">
            <div class="stat-label">Mürettebat Sağlık İndeksi</div>
            <div class="stat-value text-cyberGreen"><span data-count id="kpi-health">—</span><span class="stat-unit">/100</span></div>
            <div class="text-[10px] font-mono text-slate-500 mt-1">NASA-STD-3001 bileşke</div>
        </div>
        <div class="stat-tile reveal card-lift">
            <div class="stat-label">Tek Yön Gecikme</div>
            <div class="stat-value text-amber-300"><span id="kpi-delay">—</span></div>
            <div class="text-[10px] font-mono text-slate-500 mt-1">Işık hızıyla Dünya ↔ Gemi</div>
        </div>
        <div class="stat-tile reveal card-lift">
            <div class="stat-label">Dünya'ya Uzaklık</div>
            <div class="stat-value"><span id="kpi-distance">—</span><span class="stat-unit">M km</span></div>
            <div class="text-[10px] font-mono text-slate-500 mt-1">Hız <span id="kpi-velocity">—</span> km/s</div>
        </div>
        <div class="stat-tile reveal card-lift">
            <div class="stat-label">Ort. Kümülatif Doz</div>
            <div class="stat-value text-cyberGold"><span id="kpi-dose">—</span><span class="stat-unit">mSv</span></div>
            <div class="text-[10px] font-mono text-slate-500 mt-1">Kariyer limiti 600 mSv</div>
        </div>
        <div class="stat-tile reveal card-lift col-span-2 lg:col-span-1">
            <div class="stat-label">Güneş Aktivitesi (30g)</div>
            <div class="stat-value"><span id="kpi-flare">—</span></div>
            <div class="text-[10px] font-mono text-slate-500 mt-1" id="kpi-flare-sub">NASA DONKI</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        {{-- Trajectory --}}
        <div class="card reveal p-5 xl:col-span-7">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <div class="card-title"><span class="dot text-cyberCyan"></span>Hohmann Transfer Yörüngesi</div>
                    <div class="card-sub">Canlı konum · iletişim sinyali yayılımı</div>
                </div>
                <span class="chip chip-cyan" id="traj-chip">GÜN {{ $mission['mission_day'] }}</span>
            </div>
            <svg viewBox="0 0 600 340" class="w-full h-auto" id="trajectory">
                <defs>
                    <radialGradient id="sunGlow"><stop offset="0" stop-color="#FDE68A"/><stop offset="0.35" stop-color="#F59E0B"/><stop offset="1" stop-color="#F59E0B" stop-opacity="0"/></radialGradient>
                    <linearGradient id="transferGrad" x1="0" x2="1"><stop offset="0" stop-color="#00E5FF"/><stop offset="1" stop-color="#00FF9D"/></linearGradient>
                    <radialGradient id="earthGrad" cx="0.35" cy="0.35"><stop offset="0" stop-color="#7DD3FC"/><stop offset="1" stop-color="#1D4ED8"/></radialGradient>
                    <radialGradient id="marsGrad" cx="0.35" cy="0.35"><stop offset="0" stop-color="#FDBA74"/><stop offset="1" stop-color="#C2410C"/></radialGradient>
                </defs>
                <circle cx="300" cy="170" r="70" fill="url(#sunGlow)" opacity="0.55"><animate attributeName="r" values="62;74;62" dur="5s" repeatCount="indefinite"/></circle>
                <circle cx="300" cy="170" r="14" fill="#FCD34D"/>
                <ellipse class="orbit" cx="300" cy="170" rx="120" ry="98"/>
                <ellipse class="orbit" cx="300" cy="170" rx="250" ry="150" stroke-dasharray="2 5"/>
                <path id="transfer-path" class="transfer" d="M 180 170 A 185 124 0 0 0 550 170"/>
                <g id="earth-g"><circle r="9" fill="url(#earthGrad)"/><text y="24" text-anchor="middle" fill="#7DD3FC" font-size="10" font-family="JetBrains Mono">DÜNYA</text></g>
                <g id="mars-g" transform="translate(550 170)"><circle r="7.5" fill="url(#marsGrad)"/><text y="22" text-anchor="middle" fill="#FDBA74" font-size="10" font-family="JetBrains Mono">MARS</text></g>
                <line id="signal-line" stroke="#00E5FF" stroke-width="1" stroke-dasharray="3 4" opacity="0.5"/>
                <circle id="signal-pulse" r="3" fill="#00E5FF"/>
                <g id="ship-g">
                    <circle class="signal-wave" r="4"/>
                    <circle class="signal-wave" r="4" style="animation-delay:1s"/>
                    <circle r="5" fill="#00FF9D" style="filter: drop-shadow(0 0 6px #00FF9D)"/>
                    <text y="-12" text-anchor="middle" fill="#00FF9D" font-size="10" font-weight="700" font-family="JetBrains Mono">ORION</text>
                </g>
            </svg>
            <div class="grid grid-cols-3 gap-2 text-center mt-1">
                <div class="stat-tile !py-2"><div class="stat-label">Mars'a Mesafe</div><div class="font-space font-bold text-white"><span id="traj-mars">—</span> M km</div></div>
                <div class="stat-tile !py-2"><div class="stat-label">Gidiş-Dönüş Sinyal</div><div class="font-space font-bold text-amber-300" id="traj-rtt">—</div></div>
                <div class="stat-tile !py-2"><div class="stat-label">Otonom Karar</div><div class="font-space font-bold text-cyberGreen" id="traj-latency">&lt; 1 ms</div></div>
            </div>
        </div>

        {{-- Event feed --}}
        <div class="card reveal p-5 xl:col-span-5 flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <div class="card-title"><span class="dot text-cyberGreen dot-live"></span>Görev Tıbbi Kayıt Defteri</div>
                    <div class="card-sub">SQLite · health_events tablosu · canlı</div>
                </div>
                <button class="btn btn-ghost !py-1 !px-2.5 !text-[10px]" onclick="VB.nav.go('triage')">TÜMÜ ›</button>
            </div>
            <div id="ov-feed" class="space-y-1 overflow-y-auto max-h-[420px] -mx-2 pr-1"></div>
        </div>

        {{-- Crew cards --}}
        <div class="xl:col-span-12 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" id="ov-crew">
            @foreach ($crew as $member)
                <button onclick="VB.selectMember('{{ $member['id'] }}', true)" class="card card-lift reveal p-4 text-left" data-crew-card="{{ $member['id'] }}">
                    <div class="flex items-center gap-3">
                        <div class="relative w-14 h-14 shrink-0">
                            <svg viewBox="0 0 56 56" class="w-14 h-14">
                                <circle class="ring-track" cx="28" cy="28" r="24" stroke-width="4"/>
                                <circle class="ring-value" data-ring cx="28" cy="28" r="24" stroke-width="4" stroke="#00FF9D" stroke-dasharray="150.8" stroke-dashoffset="150.8"/>
                            </svg>
                            <div class="absolute inset-0 grid place-items-center font-space font-bold text-white">{{ $member['initials'] }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-space font-bold text-white truncate">{{ $member['name'] }}</div>
                            <div class="text-[11px] text-slate-400 truncate">{{ $member['role'] }}</div>
                        </div>
                        <span class="chip chip-green" data-crew-chip>NOMİNAL</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mt-3 font-mono text-[11px]">
                        <div><div class="text-slate-500 text-[9px]">NABIZ</div><div class="text-white font-bold"><span data-k="heart_rate">—</span> <span class="text-slate-500">bpm</span></div></div>
                        <div><div class="text-slate-500 text-[9px]">SpO₂</div><div class="text-white font-bold"><span data-k="spo2">—</span>%</div></div>
                        <div><div class="text-slate-500 text-[9px]">DOZ</div><div class="text-white font-bold"><span data-k="cumulative_dose_msv">—</span></div></div>
                    </div>
                    <div class="h-10 mt-2" data-spark></div>
                    <div class="text-[10px] font-mono text-slate-500 mt-1 truncate">📍 {{ $member['habitat'] }}</div>
                </button>
            @endforeach
        </div>
    </div>
</section>
