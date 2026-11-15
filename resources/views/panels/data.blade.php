<section class="panel-view" id="panel-data" data-title="NASA Veri Merkezi">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="stat-tile reveal"><div class="stat-label">Uzay Görevi Kaydı</div><div class="stat-value text-cyberGreen" id="ds-missions">—</div><div class="text-[10px] font-mono text-slate-500">1961 → günümüz</div></div>
        <div class="stat-tile reveal"><div class="stat-label">Benzersiz Astronot</div><div class="stat-value" id="ds-astronauts">—</div><div class="text-[10px] font-mono text-slate-500">40+ ülke</div></div>
        <div class="stat-tile reveal"><div class="stat-label">Toplam Uçuş Saati</div><div class="stat-value text-cyberCyan" id="ds-hours">—</div><div class="text-[10px] font-mono text-slate-500">insanlık toplamı</div></div>
        <div class="stat-tile reveal"><div class="stat-label">Toplam EVA Saati</div><div class="stat-value text-cyberGold" id="ds-eva">—</div><div class="text-[10px] font-mono text-slate-500">uzay yürüyüşü</div></div>
    </div>

    <div class="card reveal p-5 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div>
                <div class="card-title">🔍 Veri Şeffaflığı — Hangi Veri Nereden?</div>
                <div class="card-sub">Gerçek veri ile simülasyonu açıkça ayırıyoruz</div>
            </div>
            <div class="flex gap-2 text-[10px]">
                <span class="chip chip-green">CANLI NASA</span><span class="chip chip-cyan">NASA / HAKEMLİ KAYNAK</span><span class="chip chip-violet">MODEL</span><span class="chip chip-gold">SİMÜLASYON</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead><tr><th>Veri</th><th>Tür</th><th>Kaynak</th></tr></thead>
                <tbody>
                    <tr><td class="text-white">Uzay biyolojisi çalışmaları (OSD-87, 242, 137)</td><td><span class="chip chip-green">CANLI NASA</span></td><td class="text-slate-400">NASA OSDR Biological Data API</td></tr>
                    <tr><td class="text-white">Güneş patlamaları & CME</td><td><span class="chip chip-green">CANLI NASA</span></td><td class="text-slate-400">NASA DONKI (erişilemezse MSL RAD arşivi, ekranda belirtilir)</td></tr>
                    <tr><td class="text-white">Derin uzay dozu 1.84 mSv/gün, Mars gidiş-dönüş ~660 mSv</td><td><span class="chip chip-cyan">HAKEMLİ</span></td><td class="text-slate-400">Zeitlin et al., Science 2013 (Curiosity MSL RAD)</td></tr>
                    <tr><td class="text-white">Mars yüzey dozu 0.64 mSv/gün</td><td><span class="chip chip-cyan">HAKEMLİ</span></td><td class="text-slate-400">Hassler et al., Science 2014</td></tr>
                    <tr><td class="text-white">Kariyer limiti 600 mSv, SPE olayı 250 mSv</td><td><span class="chip chip-cyan">NASA</span></td><td class="text-slate-400">NASA-STD-3001 Vol.1 Rev B (2022)</td></tr>
                    <tr><td class="text-white">Kemik kaybı ayda %1–1.5, kas kaybı 5–11 günde %20'ye kadar</td><td><span class="chip chip-cyan">NASA</span></td><td class="text-slate-400">NASA HRP</td></tr>
                    <tr><td class="text-white">Virüs reaktivasyonu %53 (mekik) / %61 (ISS)</td><td><span class="chip chip-cyan">HAKEMLİ</span></td><td class="text-slate-400">Rooney et al., Front Microbiol 2019</td></tr>
                    <tr><td class="text-white">Juguler akış durgunluğu 6/11, pıhtı 1/11</td><td><span class="chip chip-cyan">HAKEMLİ</span></td><td class="text-slate-400">Marshall-Goebel et al., JAMA Netw Open 2019</td></tr>
                    <tr><td class="text-white">1.277 insanlı görev kaydı</td><td><span class="chip chip-cyan">HAKEMLİ</span></td><td class="text-slate-400">Stavnichuk & Corlett, Data in Brief 2020</td></tr>
                    <tr><td class="text-white">920 günlük risk projeksiyonları</td><td><span class="chip chip-violet">MODEL</span></td><td class="text-slate-400">VanBlue modeli — yukarıdaki literatür oranlarıyla kalibre</td></tr>
                    <tr><td class="text-white">Nabız, SpO₂, EKG, tansiyon akışı</td><td><span class="chip chip-gold">SİMÜLASYON</span></td><td class="text-slate-400">Gerçek astronot biyometrisi kamuya açık değil; giyilebilir sensör akışı simüle edilir</td></tr>
                    <tr><td class="text-white">Nabız/SpO₂ alarm eşikleri</td><td><span class="chip chip-gold">KLİNİK REF.</span></td><td class="text-slate-400">Genel klinik aralıklar (NASA standardı değil)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <div class="card reveal p-5 xl:col-span-7">
            <div class="card-title mb-1">Onyıllara Göre İnsanlı Uzay Görevleri</div>
            <div class="card-sub">Kaynak: Stavnichuk &amp; Corlett 2020, Data in Brief (NASA/Roscosmos/ESA/JAXA kamu kayıtları)</div>
            <div id="chart-decades" class="w-full h-[280px]"></div>
        </div>
        <div class="card reveal p-5 xl:col-span-5">
            <div class="card-title mb-1">Uyruk Dağılımı</div>
            <div class="card-sub">Benzersiz astronot sayısı</div>
            <div id="chart-nations" class="w-full h-[280px]"></div>
        </div>

        <div class="card reveal p-5 xl:col-span-6">
            <div class="card-title mb-3">⏳ En Uzun Toplam Uçuş Süresi</div>
            <table class="table-clean"><thead><tr><th>#</th><th>Astronot</th><th>Ülke</th><th class="text-right">Gün</th></tr></thead><tbody id="ds-top-hours"></tbody></table>
        </div>
        <div class="card reveal p-5 xl:col-span-6">
            <div class="card-title mb-3">🧑‍🚀 En Uzun Toplam EVA</div>
            <table class="table-clean"><thead><tr><th>#</th><th>Astronot</th><th>Ülke</th><th class="text-right">Saat</th></tr></thead><tbody id="ds-top-eva"></tbody></table>
        </div>

        <div class="card reveal p-5 xl:col-span-7">
            <div class="flex items-center justify-between mb-3">
                <div class="card-title">📏 NASA HRP Klinik Eşikleri</div>
                <span class="chip chip-cyan" id="ds-hrp-version">NASA-STD-3001</span>
            </div>
            <table class="table-clean"><thead><tr><th>Parametre</th><th>Eşik / Aralık</th><th>Kaynak</th></tr></thead><tbody id="ds-hrp"></tbody></table>
        </div>
        <div class="card reveal p-5 xl:col-span-5">
            <div class="flex items-center justify-between mb-3">
                <div class="card-title">🧬 NASA OSDR Çalışmaları</div>
                <span class="chip chip-gold" id="ds-osdr-status">—</span>
            </div>
            <div id="ds-osdr" class="space-y-2"></div>
            <div class="card-title mt-5 mb-2">☢️ MSL RAD Referansı</div>
            <div id="ds-msl" class="grid grid-cols-3 gap-2 text-center"></div>
        </div>
    </div>
</section>
