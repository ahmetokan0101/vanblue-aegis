<section class="panel-view" id="panel-pharmacy" data-title="Gemi Eczanesi">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="stat-tile reveal"><div class="stat-label">İlaç Kalemi</div><div class="stat-value" id="ph-total">—</div><div class="text-[10px] font-mono text-slate-500">gemideki kit</div></div>
        <div class="stat-tile reveal"><div class="stat-label">Toplam Stok</div><div class="stat-value text-cyberGreen">%<span id="ph-pct">—</span></div><div class="text-[10px] font-mono text-slate-500">başlangıca göre</div></div>
        <div class="stat-tile reveal"><div class="stat-label">Kritik Stok</div><div class="stat-value text-amber-300" id="ph-low">—</div><div class="text-[10px] font-mono text-slate-500">eşik altında</div></div>
        <div class="stat-tile reveal"><div class="stat-label">Tükenen</div><div class="stat-value text-rose-400" id="ph-out">—</div><div class="text-[10px] font-mono text-slate-500">ikmal yok</div></div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <div class="xl:col-span-8">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <div class="card-title">💊 İlaç Envanteri</div>
                <span class="text-[11px] text-slate-500">Veriliyor: <strong class="text-white" id="ph-member">—</strong></span>
                <input id="ph-search" type="search" placeholder="İlaç ara…" oninput="VB.pharmacy.render()" class="ml-auto bg-[#0b1120] border border-[#1A2235] focus:border-cyberGreen/60 rounded-xl px-3 py-1.5 text-[12px] text-white outline-none w-44">
            </div>
            <div id="ph-grid" class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-3"></div>
        </div>

        <div class="xl:col-span-4 space-y-4">
            <div class="card reveal p-5">
                <div class="card-title mb-3">📋 Son Verilen İlaçlar</div>
                <div id="ph-log" class="space-y-1 max-h-[360px] overflow-y-auto -mx-2"></div>
            </div>
            <div class="card reveal p-5">
                <div class="card-title mb-2">🚀 Neden Önemli?</div>
                <div class="space-y-2 text-[12px] text-slate-400">
                    <p><strong class="text-white">İkmal yok:</strong> Mars yolculuğunda kargo aracı gelemez. Tükenen ilaç görevin sonuna kadar yoktur.</p>
                    <p><strong class="text-white">Radyasyon ve raf ömrü:</strong> Uzayda saklanan bazı ilaçlarda etken madde kaybının Dünya'dakinden hızlı olduğu gözlendi (Du et al., AAPS J 2011).</p>
                    <p><strong class="text-white">AI ile entegre:</strong> Aegis yalnızca stokta olan ilacı önerir, kritik stokta idareli kullanımı hatırlatır ve tek tıkla stoktan düşer.</p>
                </div>
            </div>
        </div>
    </div>
</section>
