@php
    $navigation = [
        ['id' => 'overview', 'label' => 'Görev Kontrol', 'group' => 'Operasyon', 'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
        ['id' => 'crew', 'label' => 'Mürettebat Sağlığı', 'group' => 'Operasyon', 'icon' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><path d="M3.2 12h4.3l1.5-3 2.5 6 1.5-3h7.8"/>'],
        ['id' => 'radiation', 'label' => 'Radyasyon & Uzay Havası', 'group' => 'Operasyon', 'icon' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>'],
        ['id' => 'triage', 'label' => 'Otonom AI Triage', 'group' => 'Operasyon', 'icon' => '<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6" rx="1"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>'],
        ['id' => 'detective', 'label' => 'Sağlık Dedektifi', 'group' => 'Operasyon', 'icon' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M8 11h6M11 8v6"/>'],
        ['id' => 'assistant', 'label' => 'AI Uçuş Doktoru', 'group' => 'Operasyon', 'icon' => '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5M8 22h8"/><path d="M19 3l.6 1.4L21 5l-1.4.6L19 7l-.6-1.4L17 5l1.4-.6z"/>'],
        ['id' => 'conditions', 'label' => 'Uzay Hastalıkları', 'group' => 'Sağlık', 'icon' => '<path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6 6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6 6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/>'],
        ['id' => 'countermeasures', 'label' => 'Karşı-Tedbir & Bilişsel', 'group' => 'Sağlık', 'icon' => '<path d="M6.5 6.5h11M6.5 17.5h11M6 20v-4M18 20v-4M6 8V4M18 8V4M3 10v4M21 10v4M6 12h12"/>'],
        ['id' => 'pharmacy', 'label' => 'Gemi Eczanesi', 'group' => 'Sağlık', 'icon' => '<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/>'],
        ['id' => 'data', 'label' => 'NASA Veri Merkezi', 'group' => 'Bilim', 'icon' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/>'],
        ['id' => 'report', 'label' => 'Tıbbi Rapor', 'group' => 'Bilim', 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6M9 9h1"/>'],
        ['id' => 'story', 'label' => 'Proje & Etki', 'group' => 'Sunum', 'icon' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>'],
    ];
@endphp

<aside class="sidebar" id="sidebar">
    <div class="flex items-center gap-3 px-4 h-[68px] border-b border-[#1A2235] shrink-0">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 via-teal-500 to-cyan-500 grid place-items-center font-tech font-black text-black shadow-[0_0_24px_rgba(0,255,157,0.45)] shrink-0">VB</div>
        <div class="sb-hide leading-tight min-w-0">
            <div class="font-space font-bold text-white tracking-tight">VanBlue</div>
            <div class="text-[10px] font-mono text-cyberGreen tracking-[0.18em]">AEGISHEALTH OS</div>
        </div>
        <button onclick="VB.nav.toggleCollapse()" class="sb-hide ml-auto text-slate-500 hover:text-white hidden lg:block" title="Daralt">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        @foreach ($navigation as $index => $item)
            @if ($index === 0 || $navigation[$index - 1]['group'] !== $item['group'])
                <div class="sb-hide px-3 pt-3 pb-1.5 text-[10px] font-mono tracking-[0.2em] text-slate-600 uppercase">{{ $item['group'] }}</div>
            @endif
            <button class="nav-item" data-panel="{{ $item['id'] }}" onclick="VB.nav.go('{{ $item['id'] }}')" title="{{ $item['label'] }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                <span class="nav-label truncate">{{ $item['label'] }}</span>
                @if ($item['id'] === 'triage')
                    <span class="nav-badge hidden" id="nav-alert-badge">!</span>
                @else
                    <span class="nav-kbd nav-label">{{ $index + 1 }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    <div class="p-3 border-t border-[#1A2235] space-y-2 shrink-0">
        <div class="sb-hide rounded-xl bg-[#06080E] border border-[#1A2235] p-3">
            <div class="flex items-center justify-between text-[10px] font-mono text-slate-500 mb-2">
                <span>MÜRETTEBAT</span>
                <span id="sb-crew-index" class="text-cyberGreen font-bold">—</span>
            </div>
            <div class="flex gap-1.5" id="sb-crew-dots">
                @foreach ($crew as $member)
                    <button onclick="VB.selectMember('{{ $member['id'] }}', true)" data-crew-dot="{{ $member['id'] }}" class="flex-1 h-8 rounded-lg bg-[#0C101B] border border-[#1A2235] text-[10px] font-mono font-bold text-slate-300 hover:border-cyberGreen/60 transition relative" title="{{ $member['name'] }}">
                        {{ $member['initials'] }}
                        <span class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-cyberGreen border-2 border-[#05070d]" data-crew-status></span>
                    </button>
                @endforeach
            </div>
        </div>
        <button onclick="VB.briefing.open()" class="btn btn-green w-full" title="AI Günlük Brifing (B)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/></svg>
            <span class="nav-label">AI BRİFİNG</span>
        </button>
        <button onclick="VB.tour.start()" class="btn btn-cyan w-full" title="Jüri Demo Turu">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            <span class="nav-label">JÜRİ DEMO TURU</span>
        </button>
        <button onclick="VB.emergency.toggle()" id="sb-emergency" class="btn btn-red w-full" title="Güneş Fırtınası Simülasyonu">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/></svg>
            <span class="nav-label" id="sb-emergency-label">SPE SİMÜLE ET</span>
        </button>
    </div>
</aside>
<div class="sidebar-backdrop" onclick="VB.nav.closeMobile()"></div>
