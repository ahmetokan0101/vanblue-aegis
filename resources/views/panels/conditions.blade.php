<section class="panel-view" id="panel-conditions" data-title="Uzay Hastalıkları Atlası">
    {{-- Hero: Earth vs Space --}}
    <div class="card reveal p-5 lg:p-6 mb-4 overflow-hidden">
        <div class="absolute -left-20 -bottom-24 w-96 h-96 rounded-full bg-rose-500/5 blur-3xl pointer-events-none"></div>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-center relative">
            <div class="lg:col-span-2">
                <span class="chip chip-violet mb-3">NASA HRP · 12 SAĞLIK RİSKİ</span>
                <h2 class="font-space text-2xl lg:text-3xl font-bold text-white tracking-tight leading-tight">Uzay, insan vücudunu <span class="bg-gradient-to-r from-rose-400 to-amber-300 bg-clip-text text-transparent">hızlandırılmış yaşlanmaya</span> sokar.</h2>
                <p class="text-slate-400 text-sm mt-2">AegisHealth her riski ayrı izler, görev boyunca nasıl değişeceğini öngörür ve önlemi Dünya'yı beklemeden başlatır.</p>
            </div>
            <div class="lg:col-span-3 grid grid-cols-3 gap-3">
                <div class="stat-tile text-center card-lift">
                    <div class="text-2xl">🦴</div>
                    <div class="stat-value text-rose-400"><span data-hero-count="12">0</span>×</div>
                    <div class="text-[11px] text-slate-400">daha hızlı kemik kaybı</div>
                    <div class="text-[9px] font-mono text-slate-600 mt-1">ayda %1.5 · Dünya'da yılda</div>
                </div>
                <div class="stat-tile text-center card-lift">
                    <div class="text-2xl">☢️</div>
                    <div class="stat-value text-amber-300"><span data-hero-count="280">0</span>×</div>
                    <div class="text-[11px] text-slate-400">daha fazla radyasyon</div>
                    <div class="text-[9px] font-mono text-slate-600 mt-1">1.84 / 0.0065 mSv/gün</div>
                </div>
                <div class="stat-tile text-center card-lift">
                    <div class="text-2xl">👁️</div>
                    <div class="stat-value text-cyberViolet">%<span data-hero-count="70">0</span></div>
                    <div class="text-[11px] text-slate-400">astronotta göz değişikliği</div>
                    <div class="text-[9px] font-mono text-slate-600 mt-1">SANS · uzun ISS görevleri</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Time machine --}}
    <div class="card reveal p-5 mb-4">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-3">
                <button onclick="VB.conditions.togglePlay()" id="cond-play" class="w-12 h-12 rounded-2xl bg-cyberGreen text-black grid place-items-center shadow-[0_0_24px_rgba(0,255,157,0.45)] hover:scale-105 transition" title="Görevi oynat">
                    <svg id="cond-play-icon" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </button>
                <div>
                    <div class="text-[10px] font-mono text-slate-500 tracking-[0.2em]">ZAMAN MAKİNESİ · GÖREV GÜNÜ</div>
                    <div class="font-tech text-3xl font-bold text-white leading-none"><span id="cond-day">—</span><span class="text-sm text-slate-500"> / 920</span></div>
                </div>
            </div>
            <span class="chip chip-cyan" id="cond-phase">—</span>
            <div class="ml-auto flex items-center gap-5">
                <div class="text-right">
                    <div class="stat-label">Bileşik Risk</div>
                    <div class="font-space font-bold text-2xl" id="cond-overall">—</div>
                </div>
                <div class="text-right">
                    <div class="stat-label">Yüksek Risk</div>
                    <div class="font-space font-bold text-2xl text-rose-400" id="cond-high">—</div>
                </div>
                <div class="text-right">
                    <div class="stat-label">Egzersiz Uyumu</div>
                    <div class="font-space font-bold text-2xl text-cyberGreen">%<span id="cond-compliance-val">60</span></div>
                </div>
            </div>
        </div>
        <div class="mt-5 relative">
            <input type="range" min="0" max="920" step="1" value="143" id="cond-slider" class="w-full accent-emerald-400 cursor-pointer" oninput="VB.conditions.setDay(this.value)">
            <div class="relative h-6 mt-1 text-[10px] font-mono text-slate-500">
                <span class="absolute left-0">🚀 Fırlatma</span>
                <span class="absolute -translate-x-1/2" style="left: 22.8%">🔴 Mars inişi · G210</span>
                <span class="absolute -translate-x-1/2" style="left: 77.2%">🛫 Mars kalkışı · G710</span>
                <span class="absolute right-0">🌍 Dönüş · G920</span>
            </div>
        </div>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-[11px] font-mono text-slate-400 whitespace-nowrap">Karşı-tedbir uyumu</span>
            <input type="range" min="0" max="100" step="5" value="60" id="cond-compliance" class="flex-1 accent-cyan-400 cursor-pointer" oninput="VB.conditions.setCompliance(this.value)">
            <span class="text-[10px] font-mono text-slate-500 hidden sm:inline">← egzersizi bırak · tam protokol →</span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4">
        {{-- Body map --}}
        <div class="card reveal p-5 xl:col-span-4">
            <div class="flex items-center justify-between mb-2">
                <div class="card-title">Etkileşimli Vücut Haritası</div>
                <button class="chip chip-gold" onclick="VB.conditions.select('radiation')">☢️ TÜM VÜCUT</button>
            </div>
            <div class="card-sub mb-2">Bir bölgeye tıklayın</div>
            <div class="relative mx-auto w-fit">
                <img src="{{ asset('images/astronaut.png') }}" alt="Vücut haritası" class="max-h-[460px] w-auto opacity-90">
                @foreach ([
                    ['head', 9, 50, 'Beyin & iç kulak'],
                    ['eyes', 15, 46, 'Gözler'],
                    ['neck', 22, 51, 'Boyun damarları'],
                    ['chest', 31, 55, 'Kalp & damarlar'],
                    ['blood', 44, 30, 'Kan & bağışıklık'],
                    ['kidneys', 46, 50, 'Böbrekler'],
                    ['spine', 38, 44, 'Omurga'],
                    ['legs', 72, 44, 'Kemik & kaslar'],
                ] as [$zone, $top, $left, $label])
                    <button class="absolute -translate-x-1/2 -translate-y-1/2 group z-10" style="top: {{ $top }}%; left: {{ $left }}%" onclick="VB.conditions.selectZone('{{ $zone }}')" data-zone="{{ $zone }}" title="{{ $label }}">
                        <span class="relative grid place-items-center text-cyberGreen" data-zone-color>
                            <span class="hud-pin-ring"></span>
                            <span class="w-4 h-4 rounded-full bg-current border-2 border-black shadow-[0_0_14px_currentColor]"></span>
                        </span>
                        <span class="absolute left-6 top-1/2 -translate-y-1/2 whitespace-nowrap text-[10px] font-mono font-bold bg-black/80 border border-[#26334D] rounded-md px-1.5 py-0.5 text-slate-200 opacity-0 group-hover:opacity-100 transition">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
            <div class="flex justify-center gap-3 text-[10px] font-mono text-slate-500 mt-2">
                <span><span class="inline-block w-2 h-2 rounded-full bg-cyberGreen"></span> düşük</span>
                <span><span class="inline-block w-2 h-2 rounded-full bg-amber-300"></span> orta</span>
                <span><span class="inline-block w-2 h-2 rounded-full bg-rose-500"></span> yüksek</span>
            </div>
        </div>

        {{-- Detail --}}
        <div class="card reveal p-5 xl:col-span-8" id="cond-detail">
            <div class="flex flex-wrap items-start gap-4">
                <div class="text-5xl" id="cd-icon">🦴</div>
                <div class="flex-1 min-w-[220px]">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="chip chip-violet" id="cd-system">—</span>
                        <span class="chip" id="cd-level">—</span>
                        <span class="text-[10px] font-mono text-slate-500" id="cd-onset">—</span>
                    </div>
                    <h3 class="font-space font-bold text-2xl text-white" id="cd-name">—</h3>
                    <div class="text-[11px] font-mono text-slate-500" id="cd-name-en">—</div>
                </div>
                <div class="text-right">
                    <div class="stat-label" id="cd-metric">—</div>
                    <div class="font-tech text-3xl font-bold" id="cd-value">—</div>
                    <div class="text-[11px] text-slate-400" id="cd-note">—</div>
                </div>
            </div>

            <p class="text-[13px] text-slate-300 mt-4 leading-relaxed" id="cd-fact">—</p>

            <div class="mt-4 p-4 rounded-2xl bg-[#06080E] border border-[#1A2235]">
                <div class="flex items-center justify-between mb-3">
                    <div class="card-title !text-[11px]">Uzay vs Dünya</div>
                    <span class="chip chip-red" id="cd-multiplier">—</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-[12px] mb-1"><span class="text-rose-300">🚀 <span id="cd-space-label">—</span></span></div>
                        <div class="h-3 rounded-full bg-[#0b1120] overflow-hidden"><div id="cd-space-bar" class="h-full rounded-full bg-gradient-to-r from-rose-500 to-amber-400 transition-all duration-1000 ease-out" style="width:0"></div></div>
                    </div>
                    <div>
                        <div class="flex justify-between text-[12px] mb-1"><span class="text-cyan-300">🌍 <span id="cd-earth-label">—</span></span></div>
                        <div class="h-3 rounded-full bg-[#0b1120] overflow-hidden"><div id="cd-earth-bar" class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-emerald-400 transition-all duration-1000 ease-out" style="width:0"></div></div>
                    </div>
                </div>
                <div class="text-[10px] font-mono text-slate-500 mt-3">📎 <span id="cd-citation">—</span></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
                <div class="stat-tile"><div class="stat-label mb-2">Belirtiler</div><ul class="space-y-1 text-[12px] text-slate-300" id="cd-symptoms"></ul></div>
                <div class="stat-tile"><div class="stat-label mb-2">Nasıl Tespit Ediyoruz</div><ul class="space-y-1 text-[12px] text-slate-300" id="cd-sensors"></ul></div>
                <div class="stat-tile"><div class="stat-label mb-2">Karşı-Tedbir</div><ul class="space-y-1 text-[12px] text-slate-300" id="cd-countermeasures"></ul></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                <div class="p-4 rounded-2xl border border-cyberGreen/30 bg-gradient-to-br from-emerald-500/10 to-transparent">
                    <div class="text-[10px] font-mono text-cyberGreen font-bold tracking-widest mb-1">🤖 AEGIS OTONOM AKSİYON</div>
                    <p class="text-[13px] text-slate-200" id="cd-aegis">—</p>
                </div>
                <div class="p-4 rounded-2xl border border-cyan-500/30 bg-gradient-to-br from-cyan-500/10 to-transparent">
                    <div class="text-[10px] font-mono text-cyan-300 font-bold tracking-widest mb-1">🌍 DÜNYA'DA KİME FAYDA SAĞLAR?</div>
                    <p class="text-[13px] text-slate-200" id="cd-earth-analog">—</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Risk matrix --}}
    <div class="flex items-center justify-between mb-3">
        <div class="card-title">Risk Matrisi · <span id="cond-member" class="text-cyberGreen">—</span></div>
        <span class="text-[11px] font-mono text-slate-500">Riske göre sıralı · karta tıklayın</span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3 mb-4" id="cond-grid"></div>

    <div class="card reveal p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="card-title">Görev Boyunca Risk Eğrileri</div>
                <div class="card-sub">920 günlük Mars görevi · seçili günü dikey çizgi gösterir · Mars yüzeyinde 0.38 g kısmi yük</div>
            </div>
        </div>
        <div id="chart-conditions" class="w-full h-[340px]"></div>
    </div>
</section>
