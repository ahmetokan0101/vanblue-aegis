<section class="panel-view" id="panel-triage" data-title="Otonom AI Triage">
    {{-- Pipeline --}}
    <div class="card reveal p-5 mb-4">
        <div class="flex items-center justify-between mb-4">
            <div>
                <div class="card-title">🤖 Gemide Çalışan Karar Hattı</div>
                <div class="card-sub">Sensör → NASA eşikleri → risk skoru → aksiyon · internet / Dünya bağlantısı gerekmez</div>
            </div>
            <span class="chip chip-green"><span class="dot dot-live"></span>OFFLINE-FIRST</span>
        </div>
        <div class="flex flex-col md:flex-row items-stretch md:items-center gap-2 md:gap-0">
            <div class="flow-node md:w-40"><div class="text-2xl">📡</div><div class="text-[11px] font-bold text-white mt-1">Biyosensörler</div><div class="text-[10px] font-mono text-slate-500">EKG · SpO₂ · Doz</div></div>
            <div class="flow-arrow hidden md:block"></div>
            <div class="flow-node md:w-44"><div class="text-2xl">📚</div><div class="text-[11px] font-bold text-white mt-1">NASA-STD-3001</div><div class="text-[10px] font-mono text-slate-500">HRP eşik tablosu</div></div>
            <div class="flow-arrow hidden md:block"></div>
            <div class="flow-node md:w-44"><div class="text-2xl">🧠</div><div class="text-[11px] font-bold text-white mt-1">Aegis Kural Motoru</div><div class="text-[10px] font-mono text-slate-500">risk skoru 0-100</div></div>
            <div class="flow-arrow hidden md:block"></div>
            <div class="flow-node md:w-40"><div class="text-2xl">⚡</div><div class="text-[11px] font-bold text-white mt-1">Aksiyon + Ses</div><div class="text-[10px] font-mono text-slate-500">mürettebata anında</div></div>
            <div class="flow-arrow hidden md:block"></div>
            <div class="flow-node md:w-40"><div class="text-2xl">🛰️</div><div class="text-[11px] font-bold text-white mt-1">Dünya Senkron</div><div class="text-[10px] font-mono text-amber-300" id="flow-delay">+— dk</div></div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <div class="card reveal p-5 xl:col-span-4">
            <div class="card-title mb-3">Triage Durumu · <span id="tr-name" class="text-cyberGreen">—</span></div>
            <div class="flex items-center gap-5">
                <div class="relative w-36 h-36 shrink-0">
                    <svg viewBox="0 0 120 120" class="w-36 h-36">
                        <circle class="ring-track" cx="60" cy="60" r="52" stroke-width="10"/>
                        <circle class="ring-value" id="tr-ring" cx="60" cy="60" r="52" stroke-width="10" stroke="#00FF9D" stroke-dasharray="326.7" stroke-dashoffset="326.7"/>
                    </svg>
                    <div class="absolute inset-0 grid place-items-center text-center">
                        <div><div class="font-tech text-3xl font-bold text-white" id="tr-index">—</div><div class="text-[9px] font-mono text-slate-500">SAĞLIK İNDEKSİ</div></div>
                    </div>
                </div>
                <div class="space-y-2">
                    <span class="chip chip-green text-sm" id="tr-status">NOMİNAL</span>
                    <div class="text-[11px] font-mono text-slate-400">Risk skoru: <span class="text-white font-bold" id="tr-risk">—</span></div>
                    <div class="text-[11px] font-mono text-slate-400">Karar süresi: <span class="text-cyberGreen font-bold" id="tr-latency">—</span></div>
                    <div class="text-[10px] font-mono text-slate-500" id="tr-time">—</div>
                </div>
            </div>
            <p class="text-[13px] text-slate-300 mt-4 leading-relaxed" id="tr-message">—</p>
        </div>

        <div class="card reveal p-5 xl:col-span-8">
            <div class="card-title mb-1">⏱️ Neden Otonom? Karar Gecikmesi Karşılaştırması</div>
            <div class="card-sub mb-4">Aynı tıbbi olay için yanıt süresi</div>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-[12px] font-mono mb-1"><span class="text-slate-300">🌍 Dünya Görev Kontrol (gidiş-dönüş + değerlendirme)</span><span class="text-amber-300 font-bold" id="cmp-earth">—</span></div>
                    <div class="h-4 rounded-md bg-[#0b1120] border border-[#1A2235] overflow-hidden"><div id="cmp-earth-bar" class="h-full bg-gradient-to-r from-amber-500 to-rose-500 transition-all duration-[2500ms] ease-out" style="width:0"></div></div>
                </div>
                <div>
                    <div class="flex justify-between text-[12px] font-mono mb-1"><span class="text-slate-300">🚀 VanBlue AegisHealth (gemide)</span><span class="text-cyberGreen font-bold" id="cmp-onboard">—</span></div>
                    <div class="h-4 rounded-md bg-[#0b1120] border border-[#1A2235] overflow-hidden"><div class="h-full bg-cyberGreen shadow-[0_0_12px_#00FF9D]" style="width:0.6%"></div></div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3 mt-5">
                <div class="stat-tile"><div class="stat-label">Kazanılan Süre</div><div class="stat-value text-cyberGreen" id="cmp-saved">—</div></div>
                <div class="stat-tile"><div class="stat-label">Değerlendirilen Kural</div><div class="stat-value">7</div></div>
                <div class="stat-tile"><div class="stat-label">Kaynak Standart</div><div class="stat-value text-cyberCyan text-lg">NASA HRP</div></div>
            </div>
        </div>

        <div class="card reveal p-5 xl:col-span-7">
            <div class="card-title mb-3">Bulgular (Kanıta Dayalı)</div>
            <div id="tr-findings" class="space-y-2"></div>
        </div>

        <div class="card reveal p-5 xl:col-span-5">
            <div class="card-title mb-3">Önerilen Klinik Aksiyonlar</div>
            <ol id="tr-actions" class="space-y-2"></ol>
            <div class="card-title mt-6 mb-2">Tıbbi Kayıt Defteri</div>
            <div id="tr-feed" class="space-y-1 max-h-[260px] overflow-y-auto -mx-2"></div>
        </div>
    </div>
</section>
