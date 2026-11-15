<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Mission Detective: explainable, evidence-based root-cause analysis that decides whether a health change
 * comes from an individual, the shared cabin environment, or a faulty sensor — and which check to run next.
 *
 * Calibration: Law et al., JOEM 2014 (NASA) — on the ISS, odds of a reported headache doubled per +1 mmHg ppCO₂;
 * keeping headache risk < 1 % requires a 7-day average below 2.5 mmHg.
 */
class MissionDetective
{
    public const string CACHE_KEY = 'vanblue.detective';

    public const float CO2_TARGET_MMHG = 2.5;

    public const float CO2_ALERT_MMHG = 4.0;

    public const array SCENARIOS = ['nominal', 'cabin', 'individual', 'sensor'];

    /**
     * @var array<string, array{name: string, col: int, row: int}>
     */
    public const array MODULES = [
        'command' => ['name' => 'Komuta Güvertesi', 'col' => 0, 'row' => 0],
        'habitat' => ['name' => 'Yaşam Modülü', 'col' => 1, 'row' => 0],
        'lab' => ['name' => 'Laboratuvar', 'col' => 2, 'row' => 0],
        'shelter' => ['name' => 'Fırtına Sığınağı', 'col' => 0, 'row' => 1],
        'exercise' => ['name' => 'Egzersiz Bölmesi', 'col' => 1, 'row' => 1],
        'eclss' => ['name' => 'ECLSS Yaşam Destek', 'col' => 2, 'row' => 1],
    ];

    /**
     * Diagnostic checks: what each one costs and how strongly it separates each hypothesis pair.
     *
     * @var array<string, array{title: string, minutes: int, power: array<string, float>}>
     */
    public const array CHECKS = [
        'cdm_handheld' => ['title' => 'Bağımsız el tipi CO₂ ölçümü (CDM)', 'minutes' => 5, 'power' => ['environment|sensor' => 1.0, 'environment|individual' => 0.5, 'individual|sensor' => 0.4]],
        'eclss_logs' => ['title' => 'Havalandırma & CO₂ temizleyici (CDRA) kayıtları', 'minutes' => 3, 'power' => ['environment|sensor' => 0.7, 'environment|individual' => 0.6, 'individual|sensor' => 0.3]],
        'sensor_selftest' => ['title' => 'Sensör öz-testi ve kalibrasyon geçmişi', 'minutes' => 2, 'power' => ['environment|sensor' => 0.8, 'environment|individual' => 0.1, 'individual|sensor' => 0.6]],
        'relocate' => ['title' => 'Semptomlu astronotları temiz modüle al, 30 dk izle', 'minutes' => 30, 'power' => ['environment|individual' => 0.9, 'environment|sensor' => 0.3, 'individual|sensor' => 0.2]],
        'clinical_exam' => ['title' => 'Klinik muayene: ateş, boğaz, kan sayımı', 'minutes' => 15, 'power' => ['environment|individual' => 0.8, 'individual|sensor' => 0.7, 'environment|sensor' => 0.1]],
    ];

    public function __construct(protected NasaDataRepository $nasaData) {}

    /**
     * @return array{scenario: string, sensor_fault: bool, checks: array<int, string>, started_at: int|null}
     */
    public function state(): array
    {
        return Cache::get(self::CACHE_KEY, ['scenario' => 'nominal', 'sensor_fault' => false, 'checks' => [], 'started_at' => null]);
    }

    public function setScenario(string $scenario, bool $sensorFault = false): void
    {
        Cache::put(self::CACHE_KEY, ['scenario' => $scenario, 'sensor_fault' => $sensorFault, 'checks' => [], 'started_at' => now()->timestamp], now()->addHours(6));
    }

    public function setSensorFault(bool $sensorFault): void
    {
        $state = $this->state();
        $state['sensor_fault'] = $sensorFault;
        Cache::put(self::CACHE_KEY, $state, now()->addHours(6));
    }

    public function runCheck(string $checkId): void
    {
        $state = $this->state();

        if (! in_array($checkId, $state['checks'], true)) {
            $state['checks'][] = $checkId;
        }

        Cache::put(self::CACHE_KEY, $state, now()->addHours(6));
    }

    /**
     * Full situation picture + analysis.
     *
     * @return array<string, mixed>
     */
    public function investigate(): array
    {
        $state = $this->state();
        $modules = $this->modules($state);
        $crew = $this->crew($state);
        $analysis = $this->analyze($state, $modules, $crew);

        return [
            'state' => $state,
            'modules' => $modules,
            'crew' => $crew,
            'analysis' => $analysis,
            'calibration' => [
                'co2_target_mmhg' => self::CO2_TARGET_MMHG,
                'co2_alert_mmhg' => self::CO2_ALERT_MMHG,
                'source' => 'Law et al., J Occup Environ Med 2014 (NASA): ISS\'te her +1 mmHg CO₂ baş ağrısı olasılığını ~2 katına çıkarır; 7 günlük ortalama < 2.5 mmHg önerilir.',
                'real_data_source' => 'NASA OSDR Environmental Data App (ISS kabin CO₂ / sıcaklık / nem telemetrisi)',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, array<string, mixed>>
     */
    protected function modules(array $state): array
    {
        $scenario = $state['scenario'];
        $result = [];

        foreach (self::MODULES as $id => $module) {
            $seed = crc32($id) % 100 / 100;
            $baseline = 2.1 + $seed * 0.5;
            $primary = $baseline;
            $backup = $baseline + 0.05;
            $fanFlow = 100;

            if ($id === 'exercise' && $scenario === 'cabin') {
                $primary = 6.2;
                $backup = $state['sensor_fault'] ? 2.4 : 6.0;
                $fanFlow = 38;
            }

            if ($id === 'exercise' && $scenario === 'sensor') {
                $primary = 6.4;
                $backup = 2.5;
            }

            $series = [];
            for ($i = 35; $i >= 0; $i--) {
                $rampPrimary = $i < 14 ? 1 - $i / 14 : 0;
                $wobble = 0.08 * sin(($i + $seed * 10) / 2.3);
                $series[] = [
                    'minutes_ago' => $i * 10,
                    'primary' => round($baseline + ($primary - $baseline) * $rampPrimary + $wobble, 2),
                    'backup' => round($baseline + 0.05 + ($backup - $baseline - 0.05) * $rampPrimary + $wobble * 0.9, 2),
                ];
            }

            $result[$id] = $module + [
                'id' => $id,
                'co2_primary_mmhg' => round($primary, 2),
                'co2_backup_mmhg' => round($backup, 2),
                'co2_percent' => round($primary / 760 * 100, 2),
                'sensors_agree' => abs($primary - $backup) < 1.0,
                'temp_c' => round(22.4 + $seed * 1.4 + ($id === 'exercise' && $scenario === 'cabin' ? 1.6 : 0), 1),
                'humidity_pct' => (int) round(42 + $seed * 10 + ($id === 'exercise' && $scenario === 'cabin' ? 9 : 0)),
                'fan_flow_pct' => $fanFlow,
                'status' => $primary >= self::CO2_ALERT_MMHG ? 'alert' : ($primary > self::CO2_TARGET_MMHG + 0.3 ? 'caution' : 'nominal'),
                'series' => $series,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, array<string, mixed>>
     */
    protected function crew(array $state): array
    {
        $scenario = $state['scenario'];
        $dwell = match ($scenario) {
            'cabin', 'sensor' => ['astronot2' => ['exercise' => 70, 'habitat' => 30], 'astronot1' => ['exercise' => 65, 'command' => 35], 'astronot3' => ['command' => 80, 'lab' => 20], 'astronot4' => ['eclss' => 75, 'habitat' => 25]],
            default => ['astronot2' => ['command' => 60, 'habitat' => 40], 'astronot1' => ['command' => 70, 'exercise' => 30], 'astronot3' => ['lab' => 75, 'habitat' => 25], 'astronot4' => ['eclss' => 75, 'habitat' => 25]],
        };

        $symptoms = match ($scenario) {
            'cabin' => ['astronot2' => ['Baş ağrısı', 'Konsantrasyon güçlüğü'], 'astronot1' => ['Yorgunluk', 'Hafif baş ağrısı']],
            'individual' => ['astronot4' => ['Ateş 38.3 °C', 'Halsizlik', 'Boğaz ağrısı']],
            default => [],
        };

        $result = [];

        foreach ($this->nasaData->crew() as $id => $member) {
            $isSymptomatic = isset($symptoms[$id]);
            $location = array_key_first($dwell[$id]);
            $vitals = [
                'heart_rate' => $member['base_hr'],
                'respiratory_rate' => $member['base_rr'],
                'body_temp_c' => $member['base_temp'],
                'spo2' => 98.4,
            ];

            if ($isSymptomatic && $scenario === 'cabin') {
                $vitals['heart_rate'] += 9;
                $vitals['respiratory_rate'] += 5;
                $vitals['spo2'] = 97.6;
            }

            if ($isSymptomatic && $scenario === 'individual') {
                $vitals['heart_rate'] += 22;
                $vitals['respiratory_rate'] += 3;
                $vitals['body_temp_c'] = 38.3;
            }

            $result[$id] = [
                'id' => $id,
                'name' => $member['name'],
                'initials' => $member['initials'],
                'location' => $location,
                'dwell' => $dwell[$id],
                'symptoms' => $symptoms[$id] ?? [],
                'symptomatic' => $isSymptomatic,
                'vitals' => $vitals,
            ];
        }

        return $result;
    }

    /**
     * Bayesian-style likelihood-ratio reasoning over three hypotheses.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, array<string, mixed>>  $modules
     * @param  array<string, array<string, mixed>>  $crew
     * @return array<string, mixed>
     */
    protected function analyze(array $state, array $modules, array $crew): array
    {
        $hottest = collect($modules)->sortByDesc('co2_primary_mmhg')->first();
        $symptomatic = collect($crew)->where('symptomatic', true);
        $hasFever = $symptomatic->contains(fn (array $member): bool => $member['vitals']['body_temp_c'] >= 37.8);

        if ($symptomatic->isEmpty() && $hottest['status'] === 'nominal') {
            return [
                'mode' => 'nominal',
                'headline' => 'Anomali yok: mürettebat ve kabin ortamı nominal.',
                'hypotheses' => [],
                'evidence' => [],
                'next_check' => null,
                'checks' => $this->checkList($state, null),
                'procedure' => null,
                'disclaimer' => null,
            ];
        }

        $focusModule = $symptomatic->isNotEmpty()
            ? collect($symptomatic->first()['dwell'])->keys()->first(fn (string $module): bool => $symptomatic->every(fn (array $member): bool => ($member['dwell'][$module] ?? 0) >= 50)) ?? $hottest['id']
            : $hottest['id'];
        $module = $modules[$focusModule];
        $moduleName = $module['name'];

        $hypotheses = [
            'environment' => ['label' => "Ortak kabin ortamı — {$moduleName} CO₂ birikimi", 'short' => 'Kabin ortamı', 'p' => 1.0],
            'individual' => ['label' => 'Bireysel sağlık sorunu (enfeksiyon / yorgunluk)', 'short' => 'Bireysel', 'p' => 1.0],
            'sensor' => ['label' => "Sensör arızası — {$moduleName} birincil CO₂ sensörü", 'short' => 'Sensör arızası', 'p' => 1.0],
        ];
        $evidence = [];
        $add = function (string $text, array $ratios, string $kind = 'observation') use (&$evidence, &$hypotheses): void {
            foreach ($ratios as $hypothesis => $ratio) {
                $hypotheses[$hypothesis]['p'] *= $ratio;
            }
            $evidence[] = ['text' => $text, 'ratios' => $ratios, 'kind' => $kind];
        };

        $colocated = $symptomatic->count() >= 2 && $symptomatic->every(fn (array $member): bool => ($member['dwell'][$focusModule] ?? 0) >= 50);

        if ($colocated) {
            $add("{$symptomatic->count()} astronotta benzer belirti var ve hepsi son 6 saatin çoğunu {$moduleName}'nde geçirdi.", ['environment' => 4.0, 'individual' => 0.4, 'sensor' => 0.5]);
        } elseif ($symptomatic->count() === 1) {
            $add("Sadece {$symptomatic->first()['name']} etkilenmiş; aynı modüldeki diğer kişilerde belirti yok.", ['environment' => 0.4, 'individual' => 3.5, 'sensor' => 0.8]);
        } elseif ($symptomatic->isEmpty()) {
            $add("{$moduleName}'nde yüksek CO₂ okunmasına rağmen hiçbir astronotta belirti yok.", ['environment' => 0.35, 'individual' => 0.2, 'sensor' => 2.5]);
        }

        $asymptomaticElsewhere = collect($crew)->where('symptomatic', false)->filter(fn (array $member): bool => ($member['dwell'][$focusModule] ?? 0) < 50);
        if ($colocated && $asymptomaticElsewhere->isNotEmpty()) {
            $add('Başka modüllerde kalan '.$asymptomaticElsewhere->pluck('name')->implode(', ').' sağlıklı (negatif kontrol).', ['environment' => 1.6, 'individual' => 0.8, 'sensor' => 1.0]);
        }

        $excess = round($module['co2_primary_mmhg'] - self::CO2_TARGET_MMHG, 1);
        if ($module['co2_primary_mmhg'] >= self::CO2_ALERT_MMHG) {
            $oddsMultiplier = round(2 ** max(0, $excess), 1);
            $add("{$moduleName} birincil sensörü {$module['co2_primary_mmhg']} mmHg okuyor (hedef < 2.5). Law 2014'e göre bu seviyede baş ağrısı olasılığı ~{$oddsMultiplier} kat.", ['environment' => 2.2, 'individual' => 0.5, 'sensor' => 2.0]);
        } else {
            $add("{$moduleName} CO₂ seviyesi normal ({$module['co2_primary_mmhg']} mmHg).", ['environment' => 0.25, 'individual' => 2.0, 'sensor' => 0.6]);
        }

        if ($module['co2_primary_mmhg'] >= self::CO2_ALERT_MMHG) {
            if ($module['sensors_agree']) {
                $add("Bağımsız yedek sensör de yüksek okuyor ({$module['co2_backup_mmhg']} mmHg) — iki sensör uyumlu.", ['environment' => 3.0, 'sensor' => 0.25]);
            } else {
                $add("Yedek sensör UYUŞMUYOR: {$module['co2_backup_mmhg']} mmHg. Sensörlerden biri hatalı olabilir.", ['environment' => 0.25, 'sensor' => 4.0]);
            }
        }

        if ($symptomatic->isNotEmpty()) {
            $hasFever
                ? $add('Belirti profili enfeksiyonla uyumlu: ateş ve taşikardi var.', ['environment' => 0.4, 'individual' => 3.0, 'sensor' => 0.7])
                : $add('Belirti profili CO₂ maruziyetiyle uyumlu: baş ağrısı, yorgunluk, solunum hızında artış; ateş yok.', ['environment' => 2.0, 'individual' => 0.6, 'sensor' => 0.9]);
        }

        foreach ($state['checks'] as $checkId) {
            [$text, $ratios] = $this->checkResult($checkId, $state, $moduleName, $symptomatic->pluck('name')->all());
            $add($text, $ratios, 'check:'.$checkId);
        }

        $total = array_sum(array_column($hypotheses, 'p'));
        $ranked = collect($hypotheses)
            ->map(fn (array $hypothesis, string $key): array => $hypothesis + ['id' => $key, 'probability' => (int) round($hypothesis['p'] / $total * 100)])
            ->sortByDesc('probability')
            ->values();

        $leader = $ranked[0];
        $runnerUp = $ranked[1];
        $isConfident = $leader['probability'] >= 70;
        $nextCheck = $this->nextCheck($state, $leader['id'], $runnerUp['id']);

        return [
            'mode' => 'investigating',
            'focus_module' => $focusModule,
            'headline' => $isConfident
                ? "Olası neden: {$leader['label']} (%{$leader['probability']})"
                : "Belirsiz: {$leader['short']} (%{$leader['probability']}) ile {$runnerUp['short']} (%{$runnerUp['probability']}) arasında karar için kontrol gerekli",
            'confident' => $isConfident,
            'hypotheses' => $ranked->map(fn (array $hypothesis): array => collect($hypothesis)->except('p')->all())->all(),
            'evidence' => $evidence,
            'next_check' => $nextCheck,
            'checks' => $this->checkList($state, $nextCheck['id'] ?? null),
            'procedure' => $this->procedure($leader['id'], $isConfident, $moduleName),
            'disclaimer' => 'Bu bir teşhis değildir; alternatif nedenler hâlâ mümkündür. Sistem karar destek sağlar.',
        ];
    }

    /**
     * Simulated (scenario ground-truth) result of a diagnostic check.
     *
     * @param  array<string, mixed>  $state
     * @param  array<int, string>  $symptomaticNames
     * @return array{0: string, 1: array<string, float>}
     */
    protected function checkResult(string $checkId, array $state, string $moduleName, array $symptomaticNames): array
    {
        $scenario = $state['scenario'];
        $names = $symptomaticNames ? implode(' ve ', $symptomaticNames) : 'mürettebat';

        return match ($checkId) {
            'cdm_handheld' => match ($scenario) {
                'cabin' => ["El tipi CDM {$moduleName}'nde 5.8 mmHg ölçtü — CO₂ gerçekten yüksek.", ['environment' => 4.0, 'sensor' => 0.15, 'individual' => 0.6]],
                'sensor' => ["El tipi CDM {$moduleName}'nde 2.6 mmHg ölçtü — ortam normal, birincil sensör yanlış okuyor.", ['environment' => 0.1, 'sensor' => 5.0]],
                default => ["El tipi CDM {$moduleName}'nde 2.4 mmHg ölçtü — ortam normal.", ['environment' => 0.2, 'individual' => 2.0]],
            },
            'eclss_logs' => match ($scenario) {
                'cabin' => ["CDRA kayıtları: {$moduleName} fan akışı 2 saattir %38'e düşmüş (filtre tıkanıklığı).", ['environment' => 3.5, 'sensor' => 0.4]],
                default => ['Havalandırma ve CDRA kayıtları normal.', ['environment' => 0.4, 'sensor' => 1.6, 'individual' => 1.4]],
            },
            'sensor_selftest' => match (true) {
                $scenario === 'sensor' => ['Birincil sensör öz-testi BAŞARISIZ: +3.8 mmHg sapma (kalibrasyon kayması).', ['sensor' => 5.0, 'environment' => 0.2]],
                $scenario === 'cabin' && $state['sensor_fault'] => ['Birincil sensör sağlam; YEDEK sensör öz-testi başarısız (−3.5 mmHg sapma). Uyuşmazlığın nedeni yedek sensör.', ['environment' => 3.0, 'sensor' => 0.3]],
                default => ['Sensör öz-testleri ve kalibrasyon geçmişi normal.', ['sensor' => 0.3, 'environment' => 1.3, 'individual' => 1.2]],
            },
            'relocate' => match ($scenario) {
                'cabin' => ["{$names} temiz modüle alındı: 30 dk'da baş ağrısı azaldı, solunum hızı normale döndü.", ['environment' => 3.5, 'individual' => 0.3]],
                'individual' => ["{$names} modül değiştirdi ama ateş ve halsizlik sürüyor.", ['individual' => 3.5, 'environment' => 0.3]],
                default => ['Belirtili astronot olmadığı için anlamlı sonuç yok.', ['sensor' => 1.2]],
            },
            'clinical_exam' => match ($scenario) {
                'individual' => ["{$names}: 38.3 °C ateş, kızarık boğaz, lökosit yüksek — enfeksiyon bulguları.", ['individual' => 4.5, 'environment' => 0.2]],
                'cabin' => ["{$names}: ateş yok, boğaz ve kan sayımı normal.", ['individual' => 0.3, 'environment' => 1.6]],
                default => ['Muayene bulguları normal.', ['individual' => 0.4, 'sensor' => 1.3]],
            },
            default => ['Bilinmeyen kontrol.', []],
        };
    }

    /**
     * Picks the unperformed check that best separates the two leading hypotheses (cheap checks preferred).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    protected function nextCheck(array $state, string $leader, string $runnerUp): ?array
    {
        $pair = collect([$leader, $runnerUp])->sort()->implode('|');

        $best = collect(self::CHECKS)
            ->reject(fn (array $check, string $id): bool => in_array($id, $state['checks'], true))
            ->map(fn (array $check, string $id): array => $check + ['id' => $id, 'score' => ($check['power'][$pair] ?? 0) - $check['minutes'] / 200])
            ->sortByDesc('score')
            ->first();

        if ($best === null || ($best['power'][$pair] ?? 0) < 0.2) {
            return null;
        }

        $labels = ['environment' => 'kabin ortamı', 'individual' => 'bireysel neden', 'sensor' => 'sensör arızası'];

        return [
            'id' => $best['id'],
            'title' => $best['title'],
            'minutes' => $best['minutes'],
            'reason' => "Bu kontrol, {$labels[$leader]} ile {$labels[$runnerUp]} arasındaki belirsizliği en çok azaltır ve {$best['minutes']} dakika sürer.",
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, array<string, mixed>>
     */
    protected function checkList(array $state, ?string $recommendedId): array
    {
        return collect(self::CHECKS)->map(fn (array $check, string $id): array => [
            'id' => $id,
            'title' => $check['title'],
            'minutes' => $check['minutes'],
            'done' => in_array($id, $state['checks'], true),
            'recommended' => $id === $recommendedId,
        ])->values()->all();
    }

    /**
     * Pre-approved flight-rule style procedure that can run without Earth.
     *
     * @return array<string, mixed>
     */
    protected function procedure(string $leader, bool $isConfident, string $moduleName): array
    {
        $steps = match ($leader) {
            'environment' => [
                "{$moduleName}'ni tahliye et; semptomlu mürettebatı temiz modüle al",
                'Bölge fanını maksimuma al, CO₂ temizleyiciyi (CDRA) yedek yatağa geçir',
                'Acil LiOH kartuşlu CO₂ temizleyiciyi hazırla',
                'Semptomlu astronotlarda 30 dk dinlenme; SpO₂ ve solunum hızı takibi',
                'CO₂ 2.5 mmHg altına inene kadar bölmede egzersizi ertele',
            ],
            'sensor' => [
                "{$moduleName} birincil sensörünü 'güvenilmez' işaretle, yedek sensöre geç",
                'El tipi monitörle bölmenin 3 noktasından ölçüm al',
                'Sensör kalibrasyon / değişim prosedürünü uygula',
                'Doğrulanana kadar bölmede yalnız çalışma yapma (iki kişi kuralı)',
            ],
            default => [
                'Semptomlu astronotu dinlendir ve ayrı uyku istasyonuna al',
                'AI uçuş doktoru danışmasını başlat',
                'Ateş, kan sayımı ve boğaz testini 6 saatte bir tekrarla',
                'Diğer mürettebatta belirti taraması yap',
            ],
        };

        return [
            'title' => $isConfident ? 'Önceden onaylanmış prosedür — uygulanabilir' : 'Ön hazırlık — karar kesinleşmeden geri alınabilir adımları uygula',
            'offline' => true,
            'steps' => $isConfident ? $steps : array_slice($steps, 0, 2),
        ];
    }
}
