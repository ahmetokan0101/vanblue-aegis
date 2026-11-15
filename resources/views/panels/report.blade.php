<section class="panel-view" id="panel-report" data-title="Tıbbi Rapor">
    <div class="flex flex-wrap items-center gap-2 mb-4 no-print">
        <span class="text-[12px] text-slate-400">Rapor, seçili astronotun <strong class="text-white">canlı</strong> verisiyle oluşturulur.</span>
        <div class="ml-auto flex gap-2">
            <button onclick="VB.report.render()" class="btn btn-ghost">↻ YENİLE</button>
            <button onclick="window.print()" class="btn btn-green">🖨️ YAZDIR / PDF</button>
        </div>
    </div>

    <div id="print-report" class="card reveal p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#1A2235] pb-4 mb-5">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-500 grid place-items-center font-tech font-black text-black">VB</div>
                <div>
                    <div class="text-[10px] font-mono text-cyan-400 tracking-[0.2em]">FLIGHT MEDICINE · CLINICAL BRIEF</div>
                    <h2 class="font-space font-bold text-xl text-white">Derin Uzay Tıbbi Durum Raporu</h2>
                    <div class="text-[11px] font-mono text-slate-400">Belge: <span id="rp-doc" class="text-white">—</span> · Oluşturma: <span id="rp-time">—</span></div>
                </div>
            </div>
            <span class="chip chip-green text-sm" id="rp-verdict">UÇUŞA UYGUN</span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 text-[12px]">
            <div><div class="stat-label">Astronot</div><div class="font-bold text-white" id="rp-name">—</div></div>
            <div><div class="stat-label">Görev Rolü</div><div class="font-bold text-cyberGreen" id="rp-role">—</div></div>
            <div><div class="stat-label">Kan Grubu</div><div class="font-bold text-white" id="rp-blood">—</div></div>
            <div><div class="stat-label">Görev Günü</div><div class="font-bold text-cyberCyan" id="rp-day">—</div></div>
        </div>

        <h3 class="card-title mb-2">1 · Fizyolojik Vitaller</h3>
        <table class="table-clean mb-5"><thead><tr><th>Parametre</th><th>Değer</th><th>Referans</th><th>Durum</th></tr></thead><tbody id="rp-vitals"></tbody></table>

        <h3 class="card-title mb-2">2 · Radyasyon Dozimetrisi</h3>
        <div class="grid grid-cols-3 gap-3 mb-5 text-[12px]">
            <div class="stat-tile"><div class="stat-label">Anlık Doz Hızı</div><div class="font-bold text-cyberGold" id="rp-rate">—</div></div>
            <div class="stat-tile"><div class="stat-label">Kümülatif</div><div class="font-bold text-white" id="rp-cum">—</div></div>
            <div class="stat-tile"><div class="stat-label">Kariyer Limiti Kullanımı</div><div class="font-bold text-white" id="rp-career">—</div></div>
        </div>

        <h3 class="card-title mb-2">3 · Otonom Triage Değerlendirmesi</h3>
        <p class="text-[13px] text-slate-300 mb-2" id="rp-message">—</p>
        <ul class="list-disc pl-5 text-[12px] text-slate-300 space-y-1 mb-5" id="rp-actions"></ul>

        <div class="flex flex-wrap justify-between gap-2 pt-4 border-t border-[#1A2235] text-[10px] font-mono text-slate-500">
            <span>Motor: VanBlue Aegis Rule Engine v2 · NASA-STD-3001 / HRP / MSL RAD referanslı</span>
            <span>Bu belge bir hackathon prototipidir; klinik tanı yerine geçmez.</span>
        </div>
    </div>
</section>
