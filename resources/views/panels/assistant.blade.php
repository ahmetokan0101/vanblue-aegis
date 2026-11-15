<section class="panel-view" id="panel-assistant" data-title="AI Uçuş Doktoru">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        {{-- Conversation --}}
        <div class="xl:col-span-8 card reveal flex flex-col min-h-[720px] overflow-hidden">
            <div class="flex flex-wrap items-center gap-4 p-5 border-b border-[#1A2235]">
                <div class="ai-orb" id="ai-orb" data-state="idle"><span></span></div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="font-space font-bold text-xl text-white">Aegis · AI Uçuş Doktoru</h2>
                        <span class="chip chip-violet" id="ai-model-chip">{{ config('services.gemini.model') }}</span>
                    </div>
                    <div class="text-[12px] text-slate-400" id="ai-state-label">Şikâyetini sesli anlat; canlı vitallerini görerek değerlendiririm.</div>
                </div>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-1.5 text-[11px] font-mono text-slate-400 cursor-pointer"><input type="checkbox" id="ai-handsfree" class="accent-emerald-400"> Eller serbest</label>
                    <label class="flex items-center gap-1.5 text-[11px] font-mono text-slate-400 cursor-pointer"><input type="checkbox" id="ai-speak" class="accent-emerald-400" checked> Sesli yanıt</label>
                    <button onclick="VB.assistant.reset()" class="btn btn-ghost !py-1.5 !px-2.5 !text-[10px]" title="Yeni görüşme">↺ YENİ</button>
                </div>
            </div>

            <div id="ai-transcript" class="flex-1 overflow-y-auto p-5 space-y-4 max-h-[560px]">
                <div class="ai-msg ai-msg-bot">
                    <div class="ai-avatar">AI</div>
                    <div class="ai-bubble">
                        <div class="text-[14px] text-slate-200">Merhaba, ben Aegis. Dünya ile aramızda dakikalar süren bir gecikme var ama ben burada, gemideyim. 🎙️ Mikrofona basıp şikâyetini anlatabilirsin. Örneğin <em>"eklemlerimde ağrı var"</em> ya da <em>"kulaklarım ağrıyor"</em> diyebilirsin.</div>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-[#1A2235] bg-[#05070d]/60">
                <div class="flex flex-wrap gap-2 mb-3" id="ai-quick">
                    @foreach (['Eklemlerimde ağrı var', 'Kulaklarımda ağrı ve basınç var', 'Başım ağrıyor, görmem bulanıklaştı', 'Midem bulanıyor, başım dönüyor', 'Belim çok ağrıyor', 'Birkaç gündür uyuyamıyorum', 'Kendimi çok yalnız ve gergin hissediyorum', 'Göğsümde baskı var'] as $symptom)
                        <button class="chip chip-cyan hover:!bg-cyan-500/20 transition !py-1" onclick="VB.assistant.send(@js($symptom))">{{ $symptom }}</button>
                    @endforeach
                </div>
                <div id="ai-attach-preview" class="hidden items-center gap-3 mb-3 p-2 pr-3 rounded-2xl bg-[#0b1120] border border-cyan-500/30 w-fit">
                    <img id="ai-attach-img" class="w-14 h-14 rounded-xl object-cover" alt="Ek">
                    <div class="text-[11px]"><div class="text-white font-semibold">📷 Fotoğraf eklendi</div><div class="text-slate-500 font-mono" id="ai-attach-size">—</div></div>
                    <button onclick="VB.attachments.clear('panel')" class="text-slate-500 hover:text-white ml-2" title="Kaldır">✕</button>
                </div>
                <div class="flex items-center gap-3">
                    <input type="file" id="ai-file" accept="image/*" capture="environment" class="hidden" onchange="VB.attachments.pick(this, 'panel')">
                    <button onclick="document.getElementById('ai-file').click()" class="w-[54px] h-[54px] rounded-[18px] grid place-items-center shrink-0 bg-[#0b1120] border border-[#1A2235] text-slate-300 hover:text-cyberCyan hover:border-cyan-500/50 transition" title="Fotoğraf ekle (döküntü, yara, göz…)">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                    </button>
                    <button id="ai-mic" onclick="VB.assistant.toggleMic()" class="ai-mic" title="Konuş (Boşluk tuşu basılı tut)">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5M8 22h8"/></svg>
                    </button>
                    <div class="flex-1 relative">
                        <canvas id="ai-wave" class="absolute inset-0 w-full h-full pointer-events-none opacity-0 transition-opacity"></canvas>
                        <input id="ai-input" type="text" maxlength="1000" placeholder="Şikâyetini yaz veya mikrofona bas…" class="w-full bg-[#0b1120] border border-[#1A2235] focus:border-cyberGreen/60 rounded-2xl px-4 py-3.5 text-[14px] text-white placeholder:text-slate-600 outline-none transition" onkeydown="if (event.key === 'Enter') VB.assistant.send()">
                    </div>
                    <button onclick="VB.assistant.send()" class="btn btn-green !py-3.5" id="ai-send">GÖNDER</button>
                </div>
                <div class="text-[10px] font-mono text-slate-600 mt-2" id="ai-mic-hint">Sesli giriş: Chrome / Edge · tr-TR · Karar destek amaçlıdır, tanı koymaz.</div>
            </div>
        </div>

        {{-- Context --}}
        <div class="xl:col-span-4 space-y-4">
            <div class="card reveal p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="card-title"><span class="dot text-cyberGreen dot-live"></span>Aegis'in Gördüğü Bağlam</div>
                    <span class="text-[10px] font-mono text-slate-500" id="ai-ctx-name">—</span>
                </div>
                <div class="grid grid-cols-2 gap-2" id="ai-context">
                    @foreach ([['heart_rate', 'Nabız', 'bpm'], ['spo2', 'SpO₂', '%'], ['blood_pressure', 'Tansiyon', 'mmHg'], ['body_temp_c', 'Isı', '°C'], ['hrv_ms', 'HRV', 'ms'], ['radiation_msv', 'Doz hızı', 'mSv/g'], ['cognitive_index', 'Bilişsel', '/100'], ['cabin_co2', 'Kabin CO₂', '%']] as [$key, $label, $unit])
                        <div class="stat-tile !p-2.5"><div class="stat-label">{{ $label }}</div><div class="font-space font-bold text-white"><span data-ctx="{{ $key }}">—</span> <span class="text-[10px] text-slate-500 font-mono">{{ $unit }}</span></div></div>
                    @endforeach
                </div>
                <p class="text-[11px] text-slate-500 mt-3">Her soru; canlı vitaller, görev günü, iletişim gecikmesi, uzay havası, NASA risk atlası ve gemideki ilaç kitiyle birlikte modele gönderilir.</p>
            </div>

            <div class="card reveal p-5">
                <div class="card-title mb-3">⏱️ Yanıt Süresi</div>
                <div class="space-y-3 font-mono text-[12px]">
                    <div>
                        <div class="flex justify-between mb-1"><span class="text-slate-300">🚀 Aegis (gemide)</span><span class="text-cyberGreen font-bold" id="ai-latency">—</span></div>
                        <div class="h-2.5 rounded-full bg-[#0b1120] overflow-hidden"><div class="h-full bg-cyberGreen" style="width: 2%"></div></div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span class="text-slate-300">🌍 Dünya'daki doktor</span><span class="text-amber-300 font-bold" id="ai-earth">—</span></div>
                        <div class="h-2.5 rounded-full bg-[#0b1120] overflow-hidden"><div class="h-full bg-gradient-to-r from-amber-500 to-rose-500" style="width: 100%"></div></div>
                    </div>
                </div>
                <div class="text-[11px] text-slate-500 mt-3">Motor: <span class="text-slate-300" id="ai-engine">—</span></div>
            </div>

            <div class="card reveal p-5">
                <div class="card-title mb-2">🚩 Kırmızı Bayrak Koruması</div>
                <p class="text-[12px] text-slate-400 mb-3">Bu ifadeler algılanırsa model ne derse desin durum <strong class="text-rose-400">ACİL</strong> olarak işaretlenir ve alarm verilir.</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach (array_unique(\App\Services\AiFlightSurgeon::RED_FLAGS) as $flag)
                        <span class="chip chip-red !text-[9px]">{{ $flag }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
