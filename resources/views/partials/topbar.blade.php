<header class="topbar">
    <div id="alert-banner" class="items-center justify-center gap-3 px-4 py-2 text-[12px] font-mono font-bold text-white border-b border-rose-500/50">
        <span class="dot dot-live text-cyberRed"></span>
        KRİTİK: GÜNEŞ PARÇACIK OLAYI (SPE) — FIRTINA SIĞINAĞINA İNTİKAL
        <span class="px-2 py-0.5 rounded bg-black/40 border border-rose-400/50 tabular-nums" id="shelter-countdown">08:00</span>
    </div>
    <div class="flex items-center gap-3 px-4 lg:px-6 h-[64px]">
        <button class="lg:hidden text-slate-300" onclick="VB.nav.openMobile()" aria-label="Menü">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <div class="min-w-0">
            <div class="text-[10px] font-mono text-slate-500 tracking-[0.18em] uppercase" id="page-eyebrow">{{ $mission['name'] }} · Gün {{ $mission['mission_day'] }}</div>
            <h1 class="font-space font-bold text-white text-lg lg:text-xl tracking-tight truncate" id="page-title">Görev Kontrol</h1>
        </div>

        <div class="ml-auto flex items-center gap-2 lg:gap-3">
            <div class="hidden xl:flex items-center gap-4 pr-3 mr-1 border-r border-[#1A2235] font-mono text-[11px]">
                <div class="text-right">
                    <div class="text-[9px] text-slate-500 tracking-widest">GÖREV SAATİ (MET)</div>
                    <div id="met-clock" class="text-cyberGreen font-bold tabular-nums tracking-wider">—</div>
                </div>
                <div class="text-right">
                    <div class="text-[9px] text-slate-500 tracking-widest">DÜNYA GECİKMESİ</div>
                    <div id="top-delay" class="text-amber-400 font-bold tabular-nums">—</div>
                </div>
                <div class="text-right">
                    <div class="text-[9px] text-slate-500 tracking-widest">UZAY HAVASI</div>
                    <div id="top-weather" class="text-slate-300 font-bold">—</div>
                </div>
            </div>

            <div class="flex items-center gap-2 bg-[#06080E] pl-2 pr-1 py-1 rounded-xl border border-[#1A2235]">
                <span class="hidden sm:inline text-[10px] font-mono text-slate-500 font-bold">ASTRONOT</span>
                <select id="crew-select" onchange="VB.selectMember(this.value)" class="bg-transparent text-white font-space font-semibold text-sm focus:outline-none cursor-pointer max-w-[150px] sm:max-w-none">
                    @foreach ($crew as $member)
                        <option value="{{ $member['id'] }}" class="bg-[#0C101B]">{{ $member['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <button onclick="VB.voice.toggle()" id="btn-voice" class="btn btn-ghost !px-2.5 !py-2" title="Sesli asistan">
                <svg id="voice-on" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5 6 9H2v6h4l5 4V5zM15.54 8.46a5 5 0 0 1 0 7.07M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>
                <svg id="voice-off" class="w-4 h-4 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5 6 9H2v6h4l5 4V5zM23 9l-6 6M17 9l6 6"/></svg>
            </button>
            <span id="link-chip" class="chip chip-green hidden md:inline-flex"><span class="dot dot-live"></span>CANLI</span>
        </div>
    </div>
</header>
