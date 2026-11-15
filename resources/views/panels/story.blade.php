<section class="panel-view" id="panel-story" data-title="Proje & Etki">
    <div class="card reveal p-6 lg:p-10 mb-4 overflow-hidden text-center">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(0,229,255,0.12),transparent_60%)] pointer-events-none"></div>
        <span class="chip chip-cyan mb-4">NASA SPACE APPS CHALLENGE 2026 · VAN</span>
        <h2 class="font-space text-3xl lg:text-5xl font-bold text-white tracking-tight leading-tight">Mars'ta en yakın hastane<br><span class="bg-gradient-to-r from-cyberGreen via-cyberCyan to-cyberViolet bg-clip-text text-transparent">225 milyon km uzakta.</span></h2>
        <p class="text-slate-400 mt-4 max-w-2xl mx-auto">Derin uzayda Dünya'dan gelen tıbbi yardım 22 dakikaya kadar gecikir. VanBlue AegisHealth, mürettebatın sağlığını gemide, NASA'nın kendi verileri ve standartlarıyla, otonom olarak izler ve karar verir.</p>
        <div class="flex flex-wrap justify-center gap-3 mt-6">
            <button onclick="VB.tour.start()" class="btn btn-green">▶ DEMO TURUNU BAŞLAT</button>
            <button onclick="VB.nav.go('overview')" class="btn btn-ghost">GÖREV KONTROLE GİT ›</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="card card-lift reveal p-5">
            <div class="text-3xl mb-2">⚠️</div>
            <div class="card-title mb-2">Problem</div>
            <p class="text-[13px] text-slate-400">İletişim gecikmesi, güneş fırtınaları, kemik/kas kaybı, SANS ve izolasyon. Gemide doktor yok, Dünya'dan anlık müdahale imkânsız.</p>
        </div>
        <div class="card card-lift reveal p-5">
            <div class="text-3xl mb-2">🛡️</div>
            <div class="card-title mb-2">Çözüm</div>
            <p class="text-[13px] text-slate-400">Giyilebilir biyosensörlerden gelen veriyi NASA-STD-3001 eşikleriyle değerlendiren, offline çalışan, sesli uyarı veren otonom triage işletim sistemi.</p>
        </div>
        <div class="card card-lift reveal p-5">
            <div class="text-3xl mb-2">🌍</div>
            <div class="card-title mb-2">Dünya'daki Etki</div>
            <p class="text-[13px] text-slate-400">Aynı mimari; Van'ın kırsal köyleri, deprem bölgeleri, Antarktika istasyonları ve denizdeki gemiler gibi doktora uzak yerlerde uzaktan sağlık izleme için kullanılabilir.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="card reveal p-5">
            <div class="card-title mb-3">📊 Kullanılan NASA Verileri</div>
            <table class="table-clean">
                <thead><tr><th>Veri Seti</th><th>Nerede Kullanılıyor</th></tr></thead>
                <tbody>
                    <tr><td class="text-white">NASA HRP / NASA-STD-3001</td><td class="text-slate-400">Triage eşikleri (nabız, SpO₂, ısı, PEL)</td></tr>
                    <tr><td class="text-white">MSL RAD (PDS) — Zeitlin 2013</td><td class="text-slate-400">Günlük GCR dozu, SPE senaryosu, doz projeksiyonu</td></tr>
                    <tr><td class="text-white">NASA HRP Risk & Evidence Books</td><td class="text-slate-400">12 risklik Uzay Hastalıkları Atlası & 920 günlük projeksiyon</td></tr>
                    <tr><td class="text-white">NASA DONKI (CCMC)</td><td class="text-slate-400">Canlı güneş patlaması & CME akışı</td></tr>
                    <tr><td class="text-white">NASA OSDR (canlı API) — OSD-87, OSD-242, OSD-137</td><td class="text-slate-400">SANS ve bağışıklık riskine bağlı uzay biyolojisi çalışmaları</td></tr>
                    <tr><td class="text-white">Astronot Görev Veritabanı (Stavnichuk &amp; Corlett 2020)</td><td class="text-slate-400">1.277 görev, insanlık uçuş istatistikleri</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card reveal p-5">
            <div class="card-title mb-3">🏆 Jüri Kriterleri ile Eşleşme</div>
            <div class="space-y-2 text-[13px]">
                <div class="stat-tile"><span class="text-cyberGreen font-bold">Etki:</span> <span class="text-slate-400">Uzayda hayat kurtarır, Dünya'da uzak bölgelere sağlık götürür.</span></div>
                <div class="stat-tile"><span class="text-cyberCyan font-bold">Yaratıcılık:</span> <span class="text-slate-400">Işık-hızı gecikmesini hesaplayan, sesli, otonom karar veren hologram arayüz.</span></div>
                <div class="stat-tile"><span class="text-cyberViolet font-bold">Geçerlilik:</span> <span class="text-slate-400">Çalışan Laravel API + test edilmiş kural motoru + gerçek NASA verisi.</span></div>
                <div class="stat-tile"><span class="text-amber-300 font-bold">İlgililik:</span> <span class="text-slate-400">Artemis & Mars görevlerinin HRP risk listesindeki ana başlıklar.</span></div>
                <div class="stat-tile"><span class="text-rose-400 font-bold">Sunum:</span> <span class="text-slate-400">Tek tıkla, sesli anlatımlı jüri demo turu.</span></div>
            </div>
        </div>
        <div class="card reveal p-5 xl:col-span-2">
            <div class="card-title mb-3">👩‍🚀 Takım VanBlue</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ($crew as $member)
                    <div class="stat-tile flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400/20 to-cyan-500/20 border border-cyberGreen/30 grid place-items-center font-space font-bold text-cyberGreen">{{ $member['initials'] }}</div>
                        <div class="min-w-0"><div class="font-bold text-white text-sm truncate">{{ $member['name'] }}</div><div class="text-[11px] text-slate-500 truncate">{{ $member['role'] }}</div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
