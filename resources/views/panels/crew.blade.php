<section class="panel-view" id="panel-crew" data-title="Mürettebat Sağlığı">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        {{-- Left column --}}
        <div class="xl:col-span-3 space-y-4 order-2 xl:order-1">
            <div class="card reveal p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="card-title">Biyolojik Kondisyon</div>
                    <span class="chip chip-green" id="crew-status-chip">NOMİNAL</span>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="stat-tile !p-2.5"><div class="stat-label">Kan Basıncı</div><div class="font-space font-bold text-cyberGreen" id="m-bp">—</div><div class="text-[9px] font-mono text-slate-500">mmHg</div></div>
                    <div class="stat-tile !p-2.5"><div class="stat-label">Vücut Isısı</div><div class="font-space font-bold text-white" id="m-temp">—</div><div class="text-[9px] font-mono text-slate-500">°C</div></div>
                    <div class="stat-tile !p-2.5"><div class="stat-label">Solunum</div><div class="font-space font-bold text-cyberCyan" id="m-rr">—</div><div class="text-[9px] font-mono text-slate-500">rpm</div></div>
                    <div class="stat-tile !p-2.5"><div class="stat-label">SpO₂</div><div class="font-space font-bold text-cyberGreen" id="m-spo2">—</div><div class="text-[9px] font-mono text-slate-500">%</div></div>
                    <div class="stat-tile !p-2.5"><div class="stat-label">HRV</div><div class="font-space font-bold text-cyberViolet" id="m-hrv">—</div><div class="text-[9px] font-mono text-slate-500">ms RMSSD</div></div>
                    <div class="stat-tile !p-2.5"><div class="stat-label">Hidrasyon</div><div class="font-space font-bold text-cyberCyan" id="m-hyd">—</div><div class="text-[9px] font-mono text-slate-500">%</div></div>
                </div>
            </div>

            <div class="card reveal p-4">
                <div class="flex items-center justify-between mb-1">
                    <div class="card-title">NASA 5-Stresör Radarı</div>
                    <span class="text-[10px] font-mono text-cyberMint" id="radar-label">—</span>
                </div>
                <div class="card-sub">HRP risk alanları bileşke skoru</div>
                <div id="chart-radar" class="w-full h-[230px]"></div>
            </div>
        </div>

        {{-- Hologram --}}
        <div class="xl:col-span-6 order-1 xl:order-2">
            <div class="card reveal p-4 relative overflow-hidden min-h-[640px] flex flex-col">
                <div class="laser-scanner"></div>
                <div class="flex items-center justify-between pb-3 border-b border-[#1A2235] z-30 relative">
                    <div>
                        <div class="text-[10px] font-mono text-cyberGreen tracking-[0.2em]">TAM VÜCUT BİYOSENSÖR TARAMASI</div>
                        <div class="font-space font-bold text-white text-lg" id="crew-name">—</div>
                        <div class="text-[11px] font-mono text-slate-400"><span id="crew-role">—</span> · Kan: <span id="crew-blood" class="text-cyberGreen">—</span> · Yaş <span id="crew-age">—</span></div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-mono text-slate-500">SAĞLIK İNDEKSİ</div>
                        <div class="font-tech text-4xl font-bold text-cyberGreen" id="crew-health">—</div>
                    </div>
                </div>

                <div class="relative flex-1 flex items-center justify-center my-2">
                    <div class="absolute bottom-6 w-80 h-20 rounded-full border border-cyan-500/30 bg-[#06080E]/60 shadow-[0_0_25px_rgba(0,229,255,0.2)] hologram-disk pointer-events-none"></div>
                    <div class="absolute bottom-9 w-60 h-14 rounded-full border border-dashed border-emerald-400/40 hologram-disk pointer-events-none animate-spin" style="animation-duration: 25s;"></div>

                    <div class="relative z-10 astro-float">
                        <img src="{{ asset('images/astronaut.png') }}" alt="Astronot hologramı" class="max-h-[470px] w-auto object-contain drop-shadow-[0_15px_30px_rgba(0,0,0,0.9)]">

                        <div class="hud-pin absolute top-[13%] left-[50%] z-30 -translate-x-1/2 text-cyberGreen">
                            <div class="relative flex items-center justify-center"><span class="hud-pin-ring"></span><span class="w-4 h-4 rounded-full bg-cyberGreen border-2 border-black grid place-items-center text-[9px] font-mono font-black text-black">1</span></div>
                            <div class="hud-card absolute left-6 -top-4 w-48 hidden sm:block">
                                <div class="text-[9px] text-cyberGreen font-mono font-bold tracking-widest">KASK · NÖRO & SANS</div>
                                <div class="text-xs font-bold text-white">Bilişsel indeks: <span id="pin-cog">—</span></div>
                                <div class="text-[10px] text-cyan-300 font-mono">SANS: Frisen Grade 0</div>
                            </div>
                        </div>
                        <div class="hud-pin absolute top-[31%] left-[53%] z-30 -translate-x-1/2 text-cyberGreen">
                            <div class="relative flex items-center justify-center"><span class="hud-pin-ring"></span><span class="w-4 h-4 rounded-full bg-cyberGreen border-2 border-black grid place-items-center text-[9px] font-mono font-black text-black">2</span></div>
                            <div class="hud-card absolute -left-52 -top-2 w-48 hidden sm:block">
                                <div class="text-[9px] text-cyberGreen font-mono font-bold tracking-widest">GÖĞÜS BİYOPAKI</div>
                                <div class="text-xs font-bold text-white">Kalp: <span id="pin-hr">—</span> BPM</div>
                                <div class="text-[10px] text-slate-400 font-mono">Tansiyon <span id="pin-bp">—</span></div>
                            </div>
                        </div>
                        <div class="hud-pin absolute top-[48%] left-[34%] z-30 -translate-x-1/2 text-cyberGold">
                            <div class="relative flex items-center justify-center"><span class="hud-pin-ring"></span><span class="w-4 h-4 rounded-full bg-cyberGold border-2 border-black grid place-items-center text-[9px] font-mono font-black text-black">3</span></div>
                            <div class="hud-card absolute -left-52 -top-2 w-48 hidden sm:block">
                                <div class="text-[9px] text-cyberGold font-mono font-bold tracking-widest">KOL DOZİMETRESİ</div>
                                <div class="text-xs font-bold text-white">Kümülatif: <span id="pin-dose">—</span> mSv</div>
                                <div class="text-[10px] text-slate-400 font-mono">Anlık <span id="pin-rate">—</span> mSv/gün</div>
                            </div>
                        </div>
                        <div class="hud-pin absolute top-[75%] left-[46%] z-30 -translate-x-1/2 text-cyberCyan">
                            <div class="relative flex items-center justify-center"><span class="hud-pin-ring"></span><span class="w-4 h-4 rounded-full bg-cyberCyan border-2 border-black grid place-items-center text-[9px] font-mono font-black text-black">4</span></div>
                            <div class="hud-card absolute left-6 -top-4 w-48 hidden sm:block">
                                <div class="text-[9px] text-cyberCyan font-mono font-bold tracking-widest">LBNP · SIVI KAYMASI</div>
                                <div class="text-xs font-bold text-white">Sıvı dengesi: Stabil</div>
                                <div class="text-[10px] text-slate-400 font-mono">Kemik/Kas: <span id="pin-bone">—</span>%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 pt-3 border-t border-[#1A2235] text-center font-mono text-xs z-30">
                    <div class="stat-tile !py-2"><div class="stat-label">Kabin Basıncı</div><div class="text-white font-bold"><span id="cab-p">—</span> kPa</div></div>
                    <div class="stat-tile !py-2"><div class="stat-label">CO₂</div><div class="text-cyberGreen font-bold">%<span id="cab-co2">—</span></div></div>
                    <div class="stat-tile !py-2"><div class="stat-label">O₂</div><div class="text-cyberCyan font-bold">%<span id="cab-o2">—</span></div></div>
                </div>
            </div>
        </div>

        {{-- Right column --}}
        <div class="xl:col-span-3 space-y-4 order-3">
            <div class="card reveal p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="card-title"><span class="dot text-cyberGreen dot-live" id="ecg-dot"></span>Canlı EKG</div>
                        <div class="card-sub">Derivasyon II · 250 Hz simülasyon</div>
                    </div>
                    <div class="text-right">
                        <div class="font-tech text-4xl font-bold text-cyberGreen leading-none" id="m-hr">—</div>
                        <div class="text-[10px] font-mono text-slate-500">BPM</div>
                    </div>
                </div>
                <canvas id="ecg-canvas" class="w-full h-[130px] mt-2 rounded-lg bg-[#03050a] border border-[#1A2235]"></canvas>
            </div>

            <div class="card reveal p-4">
                <div class="card-title mb-1">Vital Trendi</div>
                <div class="card-sub">Son 60 örnek · nabız & SpO₂</div>
                <div id="chart-trend" class="w-full h-[170px]"></div>
            </div>

            <div class="card reveal p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="card-title">🌙 Sirkadiyen & Uyku</div>
                    <span class="chip chip-cyan">FAZ 3 · UYANIK</span>
                </div>
                <div class="flex justify-between text-[11px] font-mono mb-1"><span class="text-slate-400">Son uyku</span><span class="text-white font-bold">7s 20dk <span class="text-cyberGreen">(%92)</span></span></div>
                <div class="progress mb-3"><div style="width: 92%"></div></div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="stat-tile !p-2"><div class="stat-label">Bilişsel Yorgunluk</div><div class="font-bold text-cyberGreen text-sm">%<span id="m-fatigue">—</span> risk</div></div>
                    <div class="stat-tile !p-2"><div class="stat-label">Melatonin</div><div class="font-bold text-cyan-300 text-sm">Nominal</div></div>
                </div>
            </div>
        </div>
    </div>
</section>
