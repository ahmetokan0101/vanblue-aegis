<section class="panel-view" id="panel-countermeasures" data-title="Karşı-Tedbir & Bilişsel">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <div class="card reveal p-5 xl:col-span-5">
            <div class="flex items-center justify-between mb-2">
                <div class="card-title">🏋️ Günlük Karşı-Tedbir Protokolü</div>
                <span class="chip chip-green" id="cm-badge">0/5</span>
            </div>
            <div class="progress mb-4"><div id="cm-bar" style="width:0"></div></div>
            <div class="space-y-2" id="cm-list">
                @foreach ([
                    ['ARED direnç egzersizi', '45 dk', 'Kemik koruma', 'text-cyberGreen'],
                    ['CEVIS bisiklet ergometresi', '30 dk', 'Kardiyo', 'text-cyberCyan'],
                    ['T2 koşu bandı (bungee)', '30 dk', 'Kas/iskelet', 'text-cyberMint'],
                    ['LBNP alt vücut vakum', '20 dk', 'SANS & sıvı', 'text-cyberViolet'],
                    ['Antioksidan & radyasyon kürü', '—', 'DNA onarımı', 'text-amber-300'],
                ] as $index => [$task, $duration, $benefit, $color])
                    <label class="flex items-center gap-3 p-3 rounded-xl bg-[#06080E] border border-[#1A2235] cursor-pointer hover:border-slate-600 transition">
                        <input type="checkbox" class="accent-emerald-400 w-4 h-4" data-cm="{{ $index }}" onchange="VB.countermeasures.toggle(this)" @checked($index < 3)>
                        <span class="flex-1 text-[13px] text-slate-200">{{ $task }} <span class="text-slate-500 font-mono text-[11px]">· {{ $duration }}</span></span>
                        <span class="text-[10px] font-mono font-bold {{ $color }}">{{ $benefit }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="card reveal p-5 xl:col-span-7">
            <div class="card-title mb-1">🦴 Kemik Mineral Yoğunluğu Projeksiyonu</div>
            <div class="card-sub mb-2">Kalça (trabeküler) · ARED ile aylık −%0.2, karşı-tedbirsiz aylık −%1.5 (NASA HRP)</div>
            <div id="chart-bone" class="w-full h-[290px]"></div>
        </div>

        <div class="card reveal p-5 xl:col-span-7">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <div class="card-title">🎯 Psikomotor Uyanıklık Testi (PVT)</div>
                    <div class="card-sub">NASA PVT-B tabanlı · 5 hedef · sonuç kayda yazılır ve radarı günceller</div>
                </div>
                <button onclick="VB.pvt.start()" class="btn btn-green">TESTİ BAŞLAT</button>
            </div>
            <div id="arena" class="relative w-full h-64 bg-[#03050a] rounded-xl border border-[#1A2235] overflow-hidden grid place-items-center select-none">
                <div id="target" onclick="VB.pvt.hit()" class="hidden absolute w-12 h-12 rounded-full bg-cyberGreen border-2 border-white shadow-[0_0_25px_#00FF9D] cursor-pointer"></div>
                <span id="pvt-status" class="text-sm font-mono text-slate-400">Hazır olduğunuzda testi başlatın.</span>
            </div>
            <div class="grid grid-cols-3 gap-3 mt-3">
                <div class="stat-tile"><div class="stat-label">Ortalama</div><div class="stat-value text-cyberCyan" id="pvt-avg">—</div></div>
                <div class="stat-tile"><div class="stat-label">En Hızlı</div><div class="stat-value" id="pvt-best">—</div></div>
                <div class="stat-tile"><div class="stat-label">Skor</div><div class="stat-value text-cyberGreen" id="pvt-score">—</div></div>
            </div>
        </div>

        <div class="card reveal p-5 xl:col-span-5">
            <div class="card-title mb-3">Neden Önemli?</div>
            <div class="space-y-3 text-[13px] text-slate-300">
                <div class="stat-tile"><span class="text-cyberGreen font-bold">Kemik:</span> Mikroyerçekiminde kemik kaybı Dünya'daki osteoporoz hastasından ~10 kat hızlıdır.</div>
                <div class="stat-tile"><span class="text-cyberViolet font-bold">SANS:</span> Sıvının başa kayması optik sinir ödemine yol açar; LBNP bu kaymayı tersine çevirir.</div>
                <div class="stat-tile"><span class="text-cyberCyan font-bold">Bilişsel:</span> Uyku kaybı ve izolasyon reaksiyon süresini uzatır; PVT erken uyarı verir.</div>
            </div>
        </div>
    </div>
</section>
