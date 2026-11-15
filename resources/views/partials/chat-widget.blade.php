<div id="chat-widget" class="no-print">
    {{-- Launcher --}}
    <button id="cw-launcher" onclick="VB.chat.toggle()" aria-label="Aegis AI asistanı ile sohbet">
        <span class="cw-launcher-ring"></span>
        <svg class="cw-icon-open w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8M8 13h5"/></svg>
        <svg class="cw-icon-close w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        <span id="cw-badge" class="cw-badge hidden">1</span>
    </button>
    <div id="cw-teaser" class="cw-teaser hidden" onclick="VB.chat.open()">
        <button class="cw-teaser-close" onclick="event.stopPropagation(); VB.chat.hideTeaser()">✕</button>
        <div class="text-[12px] text-slate-200"><strong class="text-cyberGreen">Aegis:</strong> <span id="cw-teaser-text">Bir şikâyetin mi var? Bana yaz ya da sesli söyle 🎙️</span></div>
    </div>

    {{-- Window --}}
    <div id="cw-window" role="dialog" aria-label="Aegis sohbet">
        <div class="cw-header">
            <div class="relative">
                <div class="ai-orb !w-10 !h-10" id="cw-orb" data-state="idle"><span></span></div>
                <span class="cw-online"></span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="font-space font-bold text-white text-[15px] leading-tight">Aegis · AI Uçuş Doktoru</div>
                <div class="text-[11px] text-emerald-300/90 flex items-center gap-1.5" id="cw-status">Çevrimiçi · gemide, gecikmesiz</div>
            </div>
            <button onclick="VB.chat.expand()" class="cw-head-btn" title="Tam ekran panelde aç">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
            </button>
            <button onclick="VB.chat.close()" class="cw-head-btn" title="Küçült">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14"/></svg>
            </button>
        </div>

        <div class="cw-member">
            <span class="text-slate-500">Hasta:</span>
            <span id="cw-member" class="text-white font-semibold">—</span>
            <span class="ml-auto font-mono text-[10px]" id="cw-vitals">—</span>
        </div>

        <div id="cw-messages" class="cw-messages">
            <div class="cw-day">Bugün</div>
        </div>

        <div class="cw-quick" id="cw-quick">
            @foreach (['🦴 Eklemlerim ağrıyor', '👂 Kulaklarım ağrıyor', '🤕 Başım ağrıyor', '🤢 Midem bulanıyor', '😴 Uyuyamıyorum'] as $chip)
                <button onclick="VB.chat.send(@js(trim(preg_replace('/^\S+\s/u', '', $chip))))">{{ $chip }}</button>
            @endforeach
        </div>

        <div id="cw-attach-preview" class="hidden items-center gap-2 mx-3 mb-1 p-1.5 pr-2 rounded-xl bg-[#0b1120] border border-cyan-500/30 w-fit text-[11px]">
            <img id="cw-attach-img" class="w-9 h-9 rounded-lg object-cover" alt="Ek">
            <span class="text-slate-300">📷 Fotoğraf hazır</span>
            <button onclick="VB.attachments.clear('widget')" class="text-slate-500 hover:text-white ml-1">✕</button>
        </div>
        <div class="cw-composer">
            <input type="file" id="cw-file" accept="image/*" capture="environment" class="hidden" onchange="VB.attachments.pick(this, 'widget')">
            <button onclick="document.getElementById('cw-file').click()" class="cw-mic" title="Fotoğraf ekle">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
            </button>
            <button id="cw-mic" onclick="VB.chat.mic()" class="cw-mic" title="Sesli söyle">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5"/></svg>
            </button>
            <input id="cw-input" type="text" maxlength="1000" placeholder="Mesajını yaz…" autocomplete="off" onkeydown="if (event.key === 'Enter') VB.chat.send()">
            <button onclick="VB.chat.send()" class="cw-send" title="Gönder">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M3.4 20.4 21 12 3.4 3.6 3.4 10l12.6 2-12.6 2z"/></svg>
            </button>
        </div>
        <div class="cw-foot">⚡ <span id="cw-model">{{ config('services.gemini.model') }}</span> · karar destek amaçlıdır</div>
    </div>
</div>
