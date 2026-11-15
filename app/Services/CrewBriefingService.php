<?php

namespace App\Services;

use App\Models\HealthEvent;
use App\Models\Medication;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Daily AI medical briefing for the whole crew — the "morning stand-up" a flight surgeon would give.
 */
class CrewBriefingService
{
    public const string CACHE_KEY = 'vanblue.crew_briefing';

    public function __construct(
        protected NasaDataRepository $nasaData,
        protected CrewHealthEngine $healthEngine,
        protected SpaceMedicineEngine $spaceMedicine,
        protected SpaceWeatherService $spaceWeather,
        protected GeminiClient $gemini,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(bool $refresh = false): array
    {
        if (! $refresh && Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        $startedAt = hrtime(true);
        $snapshot = $this->snapshot();
        $briefing = $this->askGemini($snapshot);
        $engine = $briefing ? 'gemini' : 'offline';
        $briefing = $this->normalize($briefing ?? $this->offlineBriefing($snapshot), $snapshot);

        $result = $briefing + [
            'engine' => $engine,
            'model' => $engine === 'gemini' ? $this->gemini->model() : 'Aegis Offline Briefing Engine',
            'generated_at' => now()->toIso8601String(),
            'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1e6),
            'mission' => $snapshot['mission'],
            'vitals' => collect($snapshot['crew'])->mapWithKeys(fn (array $member): array => [$member['id'] => $member['vitals'] + ['health_index' => $member['health_index']]])->all(),
        ];

        HealthEvent::create([
            'category' => 'briefing',
            'severity' => $result['overall_status'] === 'red' ? 'warning' : 'info',
            'title' => 'Günlük AI tıbbi brifingi',
            'message' => Str::limit($result['headline'], 200),
            'payload' => ['engine' => $engine],
        ]);

        Cache::put(self::CACHE_KEY, $result, now()->addMinutes(10));

        return $result;
    }

    /**
     * Everything the briefing is grounded in.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $mission = $this->healthEngine->missionStatus();
        $weather = $this->spaceWeather->summary();

        $crew = collect($this->nasaData->crew())->map(function (array $member) use ($mission): array {
            $telemetry = $this->healthEngine->telemetry($member);
            $triage = $this->healthEngine->triage($telemetry, $mission['comm_delay_seconds']);
            $risks = $this->spaceMedicine->assess($member, $mission['mission_day'], 0.6);

            return [
                'id' => $member['id'],
                'name' => $member['name'],
                'role' => $member['role'],
                'status' => $triage['status'],
                'health_index' => $triage['health_index'],
                'vitals' => [
                    'heart_rate' => $telemetry['heart_rate'],
                    'spo2' => $telemetry['spo2'],
                    'body_temp_c' => $telemetry['body_temp_c'],
                    'hrv_ms' => $telemetry['hrv_ms'],
                    'cognitive_index' => $telemetry['cognitive_index'],
                    'fatigue_risk_pct' => $telemetry['fatigue_risk_pct'],
                    'cumulative_dose_msv' => $telemetry['cumulative_dose_msv'],
                ],
                'top_risks' => collect($risks['conditions'])->take(3)->map(fn (array $condition): string => "{$condition['name']} ({$condition['projection']['value']}, risk {$condition['projection']['risk']})")->all(),
                'last_24h' => HealthEvent::query()
                    ->where('member_id', $member['id'])
                    ->where('created_at', '>=', now()->subDay())
                    ->latest('id')
                    ->limit(4)
                    ->get()
                    ->map(fn (HealthEvent $event): string => "{$event->title}: ".Str::limit((string) $event->message, 120))
                    ->all(),
            ];
        })->values()->all();

        return [
            'mission' => [
                'name' => $mission['name'],
                'day' => $mission['mission_day'],
                'days_to_mars' => $mission['days_to_mars'],
                'comm_delay_seconds' => $mission['comm_delay_seconds'],
                'solar_storm_active' => $mission['emergency'],
            ],
            'crew' => $crew,
            'space_weather' => [
                'threat_level' => $weather['threat_level'],
                'strongest_flare' => $weather['strongest']['class'] ?? null,
                'flare_count_30d' => $weather['flare_count'],
                'source' => $weather['source'],
            ],
            'pharmacy' => [
                'low' => Medication::query()->whereColumn('quantity', '<=', 'min_threshold')->where('quantity', '>', 0)->get()->map(fn (Medication $m): string => "{$m->name} ({$m->quantity} {$m->unit})")->all(),
                'out' => Medication::query()->where('quantity', 0)->pluck('name')->all(),
            ],
            'vehicle_alerts_24h' => HealthEvent::query()->whereNull('member_id')->where('created_at', '>=', now()->subDay())->whereIn('severity', ['warning', 'critical'])->pluck('title')->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>|null
     */
    protected function askGemini(array $snapshot): ?array
    {
        if (! $this->gemini->isConfigured()) {
            return null;
        }

        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $system = <<<PROMPT
        Sen "Aegis", Mars yolundaki Orion aracının yapay zekâ uçuş cerrahısın. Şimdi mürettebata GÜNLÜK SABAH TIBBİ BRİFİNGİNİ sesli olarak sunacaksın.
        Ton: profesyonel bir uçuş cerrahı + güven veren görev komutanı. Türkçe, net, kısa cümleler; sesli okunacağı için doğal konuş.
        Verilerdeki sayıları kullan, uydurma veri ekleme. Her astronot için kişisel ve uygulanabilir bir odak ver.
        Riskleri önceliklendir: önce kritik/kırmızı, sonra uyarılar, sonra rutin.

        VERİ:
        {$snapshotJson}

        YALNIZCA şu JSON şemasını döndür:
        {
          "headline": "Brifingin tek cümlelik başlığı",
          "overall_status": "green | yellow | red",
          "opening": "Sesli açılış: 2 cümle (görev günü, genel durum)",
          "crew": [{"id": "crew id", "status": "green|yellow|red", "headline": "5-8 kelimelik durum", "spoken": "Bu astronot için 1-2 cümle sesli değerlendirme", "focus": "Bugünkü tıbbi odak", "actions": ["2-3 somut görev"]}],
          "priorities": [{"title": "Öncelik", "detail": "Neden ve ne yapılacak", "owner": "Kim sorumlu"}],
          "schedule": [{"time": "HH:MM", "item": "Tıbbi program maddesi", "who": "Kimler"}],
          "space_weather": "Uzay havası hakkında 1 sesli cümle",
          "pharmacy": "Eczane/stok hakkında 1 sesli cümle",
          "closing": "Motive edici 1 kapanış cümlesi"
        }
        PROMPT;

        return $this->gemini->decodeJson($this->gemini->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => 'Bugünün brifingini hazırla.'],
        ], 2200, 0.4));
    }

    /**
     * Deterministic briefing used when the model is unreachable.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function offlineBriefing(array $snapshot): array
    {
        $colorOf = fn (string $status): string => match ($status) {
            'CRITICAL' => 'red',
            'CAUTION' => 'yellow',
            default => 'green',
        };
        $crew = collect($snapshot['crew']);
        $worst = $crew->sortBy('health_index')->first();
        $overall = $crew->contains('status', 'CRITICAL') ? 'red' : ($crew->contains('status', 'CAUTION') ? 'yellow' : 'green');
        $mission = $snapshot['mission'];

        return [
            'headline' => $overall === 'green'
                ? "Gün {$mission['day']}: mürettebat sağlıklı, odak önleyici bakım."
                : "Gün {$mission['day']}: {$worst['name']} yakın takipte.",
            'overall_status' => $overall,
            'opening' => "Günaydın ekip. Görevin {$mission['day']}. günündeyiz, Mars'a {$mission['days_to_mars']} gün kaldı. Mürettebatın ortalama sağlık indeksi ".round($crew->avg('health_index')).'.',
            'crew' => $crew->map(fn (array $member): array => [
                'id' => $member['id'],
                'status' => $colorOf($member['status']),
                'headline' => $member['status'] === 'NOMINAL' ? 'Stabil, rutin program' : 'Yakın takip gerekli',
                'spoken' => $member['name'].", nabzın {$member['vitals']['heart_rate']}, oksijenin yüzde {$member['vitals']['spo2']}. En yüksek riskin ".Str::before($member['top_risks'][0] ?? 'kemik kaybı', ' (').'.',
                'focus' => Str::before($member['top_risks'][0] ?? 'Önleyici egzersiz', ' ('),
                'actions' => ['ARED 45 dk', $member['vitals']['cognitive_index'] < 85 ? 'PVT testi tekrarı' : 'LBNP 20 dk', 'Hidrasyon 2.5 L'],
            ])->all(),
            'priorities' => array_values(array_filter([
                $mission['solar_storm_active'] ? ['title' => 'Güneş fırtınası', 'detail' => 'Sığınak protokolü sürüyor, EVA iptal.', 'owner' => 'Tüm mürettebat'] : null,
                $snapshot['pharmacy']['low'] !== [] ? ['title' => 'Kritik ilaç stoku', 'detail' => implode(', ', $snapshot['pharmacy']['low']).' idareli kullanılmalı.', 'owner' => 'Biyomedikal uzman'] : null,
                ['title' => 'Kemik ve kas koruma', 'detail' => 'ARED ve CEVIS programına tam uyum.', 'owner' => 'Tüm mürettebat'],
            ])),
            'schedule' => [
                ['time' => '08:00', 'item' => 'Sabah vital ölçümü ve brifing', 'who' => 'Tüm mürettebat'],
                ['time' => '10:00', 'item' => 'ARED direnç egzersizi', 'who' => 'Astronot 2, Astronot 1'],
                ['time' => '13:00', 'item' => 'CEVIS kardiyo + LBNP seansı', 'who' => 'Astronot 3, Astronot 4'],
                ['time' => '16:00', 'item' => 'PVT bilişsel test', 'who' => 'Tüm mürettebat'],
                ['time' => '21:30', 'item' => 'Işık programı: uyku hazırlığı', 'who' => 'Tüm mürettebat'],
            ],
            'space_weather' => "Uzay havası tehdit seviyesi {$snapshot['space_weather']['threat_level']}".($snapshot['space_weather']['strongest_flare'] ? ", son 30 günün en güçlü patlaması {$snapshot['space_weather']['strongest_flare']}." : '.'),
            'pharmacy' => $snapshot['pharmacy']['low'] === [] ? 'Eczane stokları yeterli.' : 'Kritik stokta: '.implode(', ', $snapshot['pharmacy']['low']).'.',
            'closing' => 'Bugün de birbirimize iyi bakalım. Mars bizi bekliyor.',
        ];
    }

    /**
     * @param  array<string, mixed>  $briefing
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function normalize(array $briefing, array $snapshot): array
    {
        $fallback = $this->offlineBriefing($snapshot);
        $crewById = collect($briefing['crew'] ?? [])->filter(fn ($member): bool => is_array($member))->keyBy('id');

        return [
            'headline' => (string) ($briefing['headline'] ?? $fallback['headline']),
            'overall_status' => in_array($briefing['overall_status'] ?? null, ['green', 'yellow', 'red'], true) ? $briefing['overall_status'] : $fallback['overall_status'],
            'opening' => (string) ($briefing['opening'] ?? $fallback['opening']),
            'crew' => collect($fallback['crew'])->map(fn (array $default): array => array_merge($default, array_filter((array) $crewById->get($default['id'], []), fn ($value): bool => filled($value))))->all(),
            'priorities' => array_values(array_filter((array) ($briefing['priorities'] ?? $fallback['priorities']), 'is_array')),
            'schedule' => array_values(array_filter((array) ($briefing['schedule'] ?? $fallback['schedule']), 'is_array')),
            'space_weather' => (string) ($briefing['space_weather'] ?? $fallback['space_weather']),
            'pharmacy' => (string) ($briefing['pharmacy'] ?? $fallback['pharmacy']),
            'closing' => (string) ($briefing['closing'] ?? $fallback['closing']),
        ];
    }
}
