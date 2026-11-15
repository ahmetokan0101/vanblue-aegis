<section class="panel-view" id="panel-radiation" data-title="Radyasyon & Uzay Havası">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <div class="card reveal p-5 xl:col-span-4">
            <div class="flex items-center justify-between">
                <div class="card-title">☢️ Anlık Doz Hızı</div>
                <span class="chip chip-gold">GCR + SPE</span>
            </div>
            <div class="card-sub">Seçili astronot · aktif dozimetre</div>
            <div id="chart-gauge" class="w-full h-[220px]"></div>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="stat-tile !p-2"><div class="stat-label">Uyarı</div><div class="font-bold text-amber-300">3.0</div></div>
                <div class="stat-tile !p-2"><div class="stat-label">Kritik</div><div class="font-bold text-rose-400">5.0</div></div>
                <div class="stat-tile !p-2"><div class="stat-label">MSL Cruise</div><div class="font-bold text-cyberCyan">1.84</div></div>
            </div>
            <button onclick="VB.emergency.toggle()" class="btn btn-red w-full mt-4" id="rad-emergency-btn">🚨 GÜNEŞ FIRTINASI SİMÜLE ET</button>
        </div>

        <div class="card reveal p-5 xl:col-span-8">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="card-title">Kümülatif Doz Projeksiyonu</div>
                    <div class="card-sub">MSL RAD ölçümlerine dayalı · NASA kariyer limiti 600 mSv (NASA-STD-3001)</div>
                </div>
                <div class="flex gap-2">
                    <span class="chip chip-cyan">Gidiş 210g</span>
                    <span class="chip chip-gold">Yüzey 500g</span>
                    <span class="chip chip-violet">Dönüş 210g</span>
                </div>
            </div>
            <div id="chart-dose-projection" class="w-full h-[300px]"></div>
        </div>

        <div class="card reveal p-5 xl:col-span-5">
            <div class="card-title mb-1">Mürettebat Doz Bütçesi</div>
            <div class="card-sub mb-3">Kariyer limitine göre kullanılan pay</div>
            <div id="dose-budget" class="space-y-3"></div>
        </div>

        <div class="card reveal p-5 xl:col-span-7">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div>
                    <div class="card-title"><span class="dot text-amber-300 dot-live"></span>NASA DONKI · Güneş Patlamaları (Son 30 gün)</div>
                    <div class="card-sub" id="donki-source">Yükleniyor…</div>
                </div>
                <span class="chip chip-green" id="donki-threat">—</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="md:col-span-2">
                    <div id="chart-flare-classes" class="w-full h-[200px]"></div>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <div class="stat-tile !p-2"><div class="stat-label">Patlama</div><div class="font-bold text-white" id="donki-flares">—</div></div>
                        <div class="stat-tile !p-2"><div class="stat-label">CME</div><div class="font-bold text-white" id="donki-cme">—</div></div>
                    </div>
                </div>
                <div class="md:col-span-3 max-h-[270px] overflow-y-auto">
                    <table class="table-clean">
                        <thead><tr><th>Sınıf</th><th>Zirve (UTC)</th><th>Kaynak</th></tr></thead>
                        <tbody id="donki-table"><tr><td colspan="3" class="text-slate-500">—</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card reveal p-5 xl:col-span-12">
            <div class="card-title mb-3">Koruma Stratejisi — Neden 8 Dakika?</div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-[12px]">
                <div class="stat-tile"><div class="text-amber-300 font-bold mb-1">1 · Tespit</div><p class="text-slate-400">X sınıfı patlamada enerjik protonlar gemiye dakikalar-saatler içinde ulaşır; ilk dakikalar kritiktir.</p></div>
                <div class="stat-tile"><div class="text-cyberCyan font-bold mb-1">2 · Otonom Karar</div><p class="text-slate-400">Dünya'dan onay beklemek dakikalar sürer. AegisHealth eşik aşılınca kararı gemide anında verir.</p></div>
                <div class="stat-tile"><div class="text-cyberGreen font-bold mb-1">3 · Sığınak</div><p class="text-slate-400">Su/polietilen ile &gt;20 g/cm² koruma (MSL RAD önerisi) SPE dozunu ciddi ölçüde düşürür.</p></div>
                <div class="stat-tile"><div class="text-cyberViolet font-bold mb-1">4 · Kayıt</div><p class="text-slate-400">Olay, doz ve mürettebat yanıtı tıbbi kayda yazılır; Dünya'ya gecikmeli senkronize edilir.</p></div>
            </div>
        </div>
    </div>
</section>
