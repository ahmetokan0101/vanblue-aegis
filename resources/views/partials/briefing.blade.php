<div id="briefing" class="no-print" aria-hidden="true">
    <div class="br-bg"></div>
    <div class="br-shell">
        <div class="br-top">
            <div class="flex items-center gap-4 min-w-0">
                <div class="ai-orb !w-14 !h-14" id="br-orb" data-state="thinking"><span></span></div>
                <div class="min-w-0">
                    <div class="text-[10px] font-mono text-cyan-300 tracking-[0.3em]">AEGIS · GÜNLÜK TIBBİ BRİFİNG</div>
                    <div class="font-space font-bold text-white text-2xl lg:text-3xl leading-tight" id="br-headline">Brifing hazırlanıyor…</div>
                    <div class="text-[11px] font-mono text-slate-500 mt-0.5" id="br-meta">Mürettebat verileri, riskler, uzay havası ve eczane analiz ediliyor</div>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="chip" id="br-status">—</span>
                <button class="btn btn-ghost !px-3" onclick="VB.briefing.togglePause()" id="br-pause" title="Duraklat">⏸</button>
                <button class="btn btn-ghost !px-3" onclick="VB.briefing.replay()" title="Baştan oynat">↻</button>
                <button class="btn btn-ghost !px-3" onclick="VB.briefing.refresh()" title="Yeniden üret">✨</button>
                <button class="btn btn-ghost !px-3" onclick="VB.briefing.close()" title="Kapat (Esc)">✕</button>
            </div>
        </div>

        <div class="br-subtitle" id="br-subtitle"></div>

        <div class="br-body">
            <div class="br-loading" id="br-loading">
                <div class="br-scan"></div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 w-full max-w-4xl">
                    @foreach ($crew as $member)
                        <div class="stat-tile br-skel"><div class="font-space font-bold text-white">{{ $member['initials'] }}</div><div class="text-[11px] text-slate-500">{{ $member['name'] }}</div><div class="text-[10px] font-mono text-cyan-300 mt-2">analiz ediliyor…</div></div>
                    @endforeach
                </div>
            </div>

            <div class="br-content hidden" id="br-content">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3" id="br-crew"></div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mt-3">
                    <div class="br-panel" data-seg="priorities"><div class="br-panel-title">🎯 Günün Öncelikleri</div><div id="br-priorities" class="space-y-2"></div></div>
                    <div class="br-panel" data-seg="schedule"><div class="br-panel-title">🗓️ Tıbbi Program</div><div id="br-schedule" class="space-y-1.5"></div></div>
                    <div class="space-y-3">
                        <div class="br-panel" data-seg="weather"><div class="br-panel-title">☀️ Uzay Havası</div><p id="br-weather" class="text-[13px] text-slate-300"></p></div>
                        <div class="br-panel" data-seg="pharmacy"><div class="br-panel-title">💊 Eczane</div><p id="br-pharmacy" class="text-[13px] text-slate-300"></p></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="br-foot">
            <div class="flex-1 tour-progress"><div id="br-progress"></div></div>
            <span class="text-[10px] font-mono text-slate-500" id="br-engine">—</span>
        </div>
    </div>
</div>
