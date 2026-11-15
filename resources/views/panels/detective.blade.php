<section class="panel-view" id="panel-detective" data-title="Sağlık Dedektifi">
    {{-- Scenario control --}}
    <div class="card reveal p-5 mb-4 overflow-hidden">
        <div class="absolute -right-16 -top-20 w-72 h-72 rounded-full bg-violet-500/10 blur-3xl pointer-events-none"></div>
        <div class="flex flex-wrap items-center gap-4 relative">
            <div class="min-w-[260px] flex-1">
                <span class="chip chip-violet mb-2">MISSION DETECTIVE · KÖK NEDEN ANALİZİ</span>
                <h2 class="font-space text-2xl lg:text-3xl font-bold text-white tracking-tight">Sadece alarm vermiyor, <span class="bg-gradient-to-r from-cyberViolet to-cyberCyan bg-clip-text text-transparent">nedenini araştırıyor.</span></h2>
                <p class="text-slate-400 text-sm mt-1">Sorun astronotta mı, ortak kabin ortamında mı, yoksa hatalı bir sensörde mi? Kanıtları tartar ve sıradaki en bilgilendirici kontrolü söyler.</p>
            </div>
            <button onclick="VB.detective.runDemo()" class="btn btn-red !py-3.5 !px-5 !text-[13px]">🚨 MARS GÖREVİ — BEKLENMEDİK SAĞLIK DEĞİŞİMİ</button>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-4 relative">
            <span class="text-[10px] font-mono text-slate-500 tracking-widest mr-1">SENARYO</span>
            <button class="btn btn-ghost !py-1.5 !text-[11px]" data-scenario="nominal" onclick="VB.detective.setScenario('nominal')">✅ Nominal</button>
            <button class="btn btn-ghost !py-1.5 !text-[11px]" data-scenario="cabin" onclick="VB.detective.setScenario('cabin')">🫁 Kabin CO₂ birikimi</button>
            <button class="btn btn-ghost !py-1.5 !text-[11px]" data-scenario="individual" onclick="VB.detective.setScenario('individual')">🤒 Bireysel hastalık</button>
            <button class="btn btn-ghost !py-1.5 !text-[11px]" data-scenario="sensor" onclick="VB.detective.setScenario('sensor')">📟 Sensör arızası</button>
            <span class="w-px h-6 bg-[#1A2235] mx-1"></span>
            <button class="btn btn-cyan !py-1.5 !text-[11px]" id="dt-fault-btn" onclick="VB.detective.toggleFault()">⚡ Sensör arızasını simüle et</button>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        {{-- 3D station --}}
        <div class="card reveal p-5 xl:col-span-7">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <div class="card-title">🛰️ 3D Uzay Aracı · Modül & Sensör Haritası</div>
                    <div class="card-sub">Sürükleyerek döndür · renk = CO₂ seviyesi · noktalar = astronotların konumu</div>
                </div>
                <div class="flex gap-1.5 text-[10px]"><span class="chip chip-green">&lt; 2.5</span><span class="chip chip-gold">2.8–4</span><span class="chip chip-red">≥ 4 mmHg</span></div>
            </div>
            <div class="station" id="dt-station">
                <div class="station-plane" id="dt-plane"></div>
            </div>
            <div id="dt-co2-chart" class="w-full h-[170px] mt-2"></div>
        </div>

        {{-- Reasoning --}}
        <div class="xl:col-span-5 space-y-4">
            <div class="card reveal p-5" id="dt-verdict-card">
                <div class="flex items-center justify-between mb-2">
                    <div class="card-title">🔎 Dedektif Değerlendirmesi</div>
                    <span class="chip chip-violet" id="dt-mode">—</span>
                </div>
                <div class="font-space font-bold text-white text-lg leading-snug" id="dt-headline">—</div>
                <div id="dt-change" class="hidden mt-2 p-2.5 rounded-xl border border-cyan-500/40 bg-cyan-500/10 text-[12px] text-cyan-100"></div>
                <div id="dt-hypotheses" class="space-y-3 mt-4"></div>
                <p class="text-[10px] font-mono text-slate-500 mt-3" id="dt-disclaimer"></p>
            </div>

            <div class="card reveal p-5 border-cyan-500/30" id="dt-next-card">
                <div class="text-[10px] font-mono text-cyan-300 tracking-[0.2em] mb-1">🧭 SIRADAKİ EN BİLGİLENDİRİCİ KONTROL</div>
                <div class="font-semibold text-white text-[15px]" id="dt-next-title">—</div>
                <p class="text-[12px] text-slate-400 mt-1" id="dt-next-reason">—</p>
                <button class="btn btn-cyan w-full mt-3" id="dt-next-btn" onclick="VB.detective.runNext()">▶ KONTROLÜ YAP</button>
            </div>
        </div>

        <div class="card reveal p-5 xl:col-span-7">
            <div class="card-title mb-3">🧾 Kanıt Zinciri</div>
            <div id="dt-evidence" class="space-y-2"></div>
        </div>

        <div class="xl:col-span-5 space-y-4">
            <div class="card reveal p-5">
                <div class="card-title mb-3">🧪 Tanı Kontrolleri</div>
                <div id="dt-checks" class="space-y-1.5"></div>
            </div>
            <div class="card reveal p-5 border-emerald-500/30">
                <div class="flex items-center justify-between mb-2">
                    <div class="card-title">📘 Prosedür</div>
                    <span class="chip chip-green">DÜNYA BAĞLANTISI GEREKMEZ</span>
                </div>
                <div class="text-[12px] text-slate-400 mb-2" id="dt-proc-title">—</div>
                <div id="dt-proc" class="space-y-1.5"></div>
            </div>
        </div>

        <div class="xl:col-span-12 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3" id="dt-crew"></div>

        <div class="card reveal p-4 xl:col-span-12 text-[11px] text-slate-400 flex flex-wrap gap-x-6 gap-y-1">
            <span>📐 <strong class="text-slate-200">Kalibrasyon:</strong> Law et al., JOEM 2014 (NASA) — ISS'te her +1 mmHg CO₂ baş ağrısı olasılığını ~2 katına çıkarır; 7 günlük ortalama &lt; 2.5 mmHg önerilir.</span>
            <span>🛰️ <strong class="text-slate-200">Gerçek veri kaynağı:</strong> NASA OSDR Environmental Data App — ISS kabin CO₂/sıcaklık/nem telemetrisi.</span>
            <span>⚠️ Astronot sağlık verileri simülasyondur; sistem kesin teşhis koymaz.</span>
        </div>
    </div>
</section>
