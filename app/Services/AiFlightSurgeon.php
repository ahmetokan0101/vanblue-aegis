<?php

namespace App\Services;

use App\Models\HealthEvent;
use App\Models\Medication;
use Illuminate\Support\Str;

/**
 * Voice-driven AI flight surgeon: grounds Gemini (OpenAI-compatible endpoint) in live telemetry,
 * mission context and the NASA HRP condition atlas; falls back to an on-board rule engine when offline.
 */
class AiFlightSurgeon
{
    /**
     * Phrases that always escalate to an emergency, regardless of the model's answer.
     *
     * @var array<string, string>
     */
    public const array RED_FLAGS = [
        'göğüs' => 'Göğüs ağrısı / baskısı',
        'göğs' => 'Göğüs ağrısı / baskısı',
        'nefes alam' => 'Nefes darlığı',
        'nefes darl' => 'Nefes darlığı',
        'bayıl' => 'Bilinç kaybı / bayılma',
        'bilinc' => 'Bilinç değişikliği',
        'bilinç' => 'Bilinç değişikliği',
        'konuşam' => 'Konuşma bozukluğu (inme şüphesi)',
        'uyuşma' => 'Tek taraflı uyuşma (inme şüphesi)',
        'felç' => 'Felç belirtisi',
        'kanama' => 'Aktif kanama',
        'kan kus' => 'Kan kusma',
        'şiddetli baş ağrısı' => 'Ani şiddetli baş ağrısı',
        'göremiyorum' => 'Ani görme kaybı',
        'nöbet' => 'Nöbet',
        'kendime zarar' => 'Kendine zarar verme düşüncesi',
        'intihar' => 'İntihar düşüncesi',
    ];

    /**
     * Phrases that raise urgency to at least "urgent".
     *
     * @var array<string, string>
     */
    public const array URGENT_FLAGS = [
        'testis' => 'Testis ağrısı (torsiyon dışlanmalı)',
        'yumurtalar' => 'Testis ağrısı (torsiyon dışlanmalı)',
        'skrotum' => 'Skrotal ağrı (torsiyon dışlanmalı)',
        'idrar yapamıyorum' => 'İdrar retansiyonu',
        'idrarda kan' => 'İdrarda kan (hematüri)',
        'kanlı idrar' => 'İdrarda kan (hematüri)',
        'eva' => 'EVA sonrası belirti (dekompresyon hastalığı dışlanmalı)',
    ];

    /**
     * Phrases that indicate an evasive / refusing model answer.
     */
    protected const string EVASIVE_PATTERN = '/(yardımcı olamam|cevap veremem|uygun değil|bu konuda konuşamam|bir doktora başvur|sağlık profesyoneline danış|i can(no|\')t help|i\'m unable)/iu';

    /**
     * Keyword → condition atlas mapping for the offline engine.
     *
     * @var array<string, array<int, string>>
     */
    protected const array KEYWORDS = [
        'bone_loss' => ['kemik', 'kırık', 'kalça'],
        'muscle_atrophy' => ['kas', 'güçsüz', 'halsiz', 'bacak', 'eklem', 'diz', 'omuz'],
        'renal_stones' => ['böbrek', 'yan ağr', 'idrar', 'böğür'],
        'sans' => ['göz', 'görme', 'bulanık', 'baş ağr', 'başım ağr'],
        'orthostatic' => ['baş dön', 'sersem', 'çarpıntı', 'kalbim'],
        'radiation' => ['radyasyon', 'doz', 'cilt yan', 'kızarık'],
        'immune' => ['ateş', 'uçuk', 'yara', 'enfeksiyon', 'boğaz', 'öksür', 'döküntü'],
        'vte' => ['boyun', 'şişlik', 'dolgunluk'],
        'spine' => ['bel', 'sırt', 'omurga'],
        'sas' => ['mide', 'bulantı', 'kus', 'kulak', 'denge', 'mide bulan'],
        'sleep' => ['uyku', 'uyuyam', 'yorgun', 'uykusuz'],
        'behavioral' => ['stres', 'kaygı', 'üzgün', 'yalnız', 'mutsuz', 'gergin', 'ailem'],
    ];

    public function __construct(
        protected NasaDataRepository $nasaData,
        protected CrewHealthEngine $healthEngine,
        protected SpaceMedicineEngine $spaceMedicine,
        protected GeminiClient $gemini,
    ) {}

    /**
     * @param  array<string, mixed>  $member
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<string, mixed>
     */
    public function consult(array $member, string $message, array $history = [], ?string $image = null): array
    {
        $startedAt = hrtime(true);
        $context = $this->buildContext($member);
        $redFlags = $this->detectRedFlags($message);
        $urgentFlags = $this->detectFlags($message, self::URGENT_FLAGS);
        $protocol = $this->matchProtocol($message.' '.collect($history)->where('role', 'user')->pluck('content')->implode(' '))
            ?? ($image !== null ? collect($this->nasaData->onboardProtocols())->firstWhere('id', 'skin') : null);

        $answer = $this->askGemini($member, $message, $history, $context, $protocol, $image);
        $engine = $answer ? 'gemini' : 'offline';

        if ($answer !== null && $protocol !== null && $this->isWeak($answer)) {
            $answer = $this->reinforceWithProtocol($answer, $protocol, $context);
            $engine = 'gemini+protocol';
        } elseif ($answer !== null && $protocol !== null) {
            $answer = $this->completeTreatment($answer, $this->protocolAnswer($protocol, $context));
        }

        $answer ??= $this->offlineAnswer($message, $context);
        $answer['treatment_plan'] ??= ['now' => [], 'next_24h' => [], 'avoid' => [], 'expected_course' => ''];

        if ($urgentFlags !== [] && in_array($answer['urgency'], ['routine', 'monitor'], true)) {
            $answer['urgency'] = 'urgent';
            array_unshift($answer['immediate_steps'], 'ÖNCELİKLİ: '.implode(', ', $urgentFlags).' — hemen muayene et.');
        }

        if ($redFlags !== []) {
            $answer['urgency'] = 'emergency';
            $answer['red_flags'] = $redFlags;
            array_unshift($answer['immediate_steps'], 'KIRMIZI BAYRAK: '.implode(', ', $redFlags).' — tüm mürettebatı uyar, tıbbi kiti aç, CMO\'yu çağır.');
        }

        $answer['protocol'] = $protocol ? ['id' => $protocol['id'], 'title' => $protocol['title']] : null;
        $answer['medications'] = $this->attachInventory($answer['medications']);
        $answer['image_findings'] ??= null;
        $answer['history_insight'] ??= null;
        $answer['image_analyzed'] = $image !== null && $engine !== 'offline';

        if ($image !== null && $engine === 'offline') {
            $answer['image_findings'] = 'Görüntü kaydedildi ancak görsel analiz için yapay zekâ bağlantısı gerekli; değerlendirme metin şikâyetine göre yapıldı.';
        }

        $relatedCondition = collect($this->nasaData->spaceConditions())->firstWhere('id', $answer['related_condition_id'] ?? null);

        return $answer + [
            'engine' => $engine,
            'model' => str_starts_with($engine, 'gemini') ? config('services.gemini.model') : 'Aegis Offline Rule Engine',
            'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1e6),
            'earth_round_trip_seconds' => $context['mission']['round_trip_seconds'],
            'related_condition' => $relatedCondition
                ? ['id' => $relatedCondition['id'], 'name' => $relatedCondition['name'], 'icon' => $relatedCondition['icon']]
                : null,
            'context_snapshot' => $context['vitals'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function detectRedFlags(string $message): array
    {
        return $this->detectFlags($message, self::RED_FLAGS);
    }

    /**
     * @param  array<string, string>  $flags
     * @return array<int, string>
     */
    protected function detectFlags(string $message, array $flags): array
    {
        $normalized = $this->normalize($message);

        return collect($flags)
            ->filter(fn (string $label, string $needle): bool => preg_match('/(^|\P{L})'.preg_quote($needle, '/').'/u', $normalized) === 1)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Turkish-aware lowercase (İ → i, I → ı).
     */
    public function normalize(string $text): string
    {
        return mb_strtolower(strtr($text, ['İ' => 'i', 'I' => 'ı']), 'UTF-8');
    }

    /**
     * Best-matching on-board protocol for the complaint (keyword retrieval).
     *
     * @return array<string, mixed>|null
     */
    public function matchProtocol(string $text): ?array
    {
        $normalized = $this->normalize($text);

        return collect($this->nasaData->onboardProtocols())
            ->map(fn (array $protocol): array => $protocol + ['score' => collect($protocol['keywords'])->filter(fn (string $keyword): bool => str_contains($normalized, $this->normalize($keyword)))->count()])
            ->filter(fn (array $protocol): bool => $protocol['score'] > 0)
            ->sortByDesc('score')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    protected function isWeak(array $answer): bool
    {
        return count($answer['possible_causes']) < 2
            || count($answer['immediate_steps']) < 2
            || $answer['treatment_plan']['now'] === []
            || preg_match(self::EVASIVE_PATTERN, $answer['spoken_reply'].' '.$answer['summary']) === 1;
    }

    /**
     * Fills gaps in a vague/evasive model answer with the on-board protocol.
     *
     * @param  array<string, mixed>  $answer
     * @param  array<string, mixed>  $protocol
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function reinforceWithProtocol(array $answer, array $protocol, array $context): array
    {
        $offline = $this->protocolAnswer($protocol, $context);

        if (preg_match(self::EVASIVE_PATTERN, $answer['spoken_reply']) === 1 || mb_strlen($answer['spoken_reply']) < 40) {
            $answer['spoken_reply'] = $offline['spoken_reply'];
        }

        foreach (['possible_causes', 'immediate_steps', 'medications', 'follow_up_questions', 'monitoring'] as $key) {
            if (count($answer[$key]) < 2) {
                $answer[$key] = $offline[$key];
            }
        }

        $answer['summary'] = $answer['summary'] ?: $offline['summary'];
        $answer['related_condition_id'] ??= $offline['related_condition_id'];
        $answer['urgency'] = $this->maxUrgency($answer['urgency'], $offline['urgency']);

        return $this->completeTreatment($answer, $offline);
    }

    /**
     * Guarantees every answer carries an actionable solution: medication, now/24h plan, what to avoid, escalation.
     *
     * @param  array<string, mixed>  $answer
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    protected function completeTreatment(array $answer, array $fallback): array
    {
        if ($answer['medications'] === []) {
            $answer['medications'] = $fallback['medications'];
        }

        foreach (['now', 'next_24h', 'avoid'] as $key) {
            if ($answer['treatment_plan'][$key] === []) {
                $answer['treatment_plan'][$key] = $fallback['treatment_plan'][$key];
            }
        }

        $answer['treatment_plan']['expected_course'] = $answer['treatment_plan']['expected_course'] ?: $fallback['treatment_plan']['expected_course'];
        $answer['escalate_when'] = $answer['escalate_when'] ?: $fallback['escalate_when'];
        $answer['monitoring'] = $answer['monitoring'] ?: $fallback['monitoring'];

        return $answer;
    }

    protected function maxUrgency(string $first, string $second): string
    {
        $order = ['routine' => 0, 'monitor' => 1, 'urgent' => 2, 'emergency' => 3];

        return ($order[$first] ?? 1) >= ($order[$second] ?? 1) ? $first : $second;
    }

    /**
     * @param  array<string, mixed>  $protocol
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function protocolAnswer(array $protocol, array $context): array
    {
        $name = Str::before($context['astronaut']['name'], ' ');
        $vitals = $context['vitals'];
        $firstMedication = $protocol['medications'][0] ?? null;

        return [
            'treatment_plan' => [
                'now' => array_values(array_filter([
                    $firstMedication ? "{$firstMedication['name']} al: {$firstMedication['dose']}".($firstMedication['note'] ? " ({$firstMedication['note']})" : '') : null,
                    ...array_slice($protocol['steps'], 0, 2),
                ])),
                'next_24h' => [
                    ...array_slice($protocol['steps'], 2),
                    'Belirtileri ve ağrı skorunu 6 saatte bir Aegis\'e bildir',
                ],
                'avoid' => $protocol['avoid'] ?? ['Ağrı kesiciyi önerilen dozun üzerinde alma', 'Belirti kötüleşirse beklemeden haber ver'],
                'expected_course' => $protocol['expected_course'] ?? 'Uygun tedaviyle 24-72 saat içinde belirgin rahatlama beklenir; rahatlama yoksa protokol yeniden değerlendirilir.',
            ],
            'spoken_reply' => str_replace('{name}', $name, $protocol['spoken']),
            'summary' => "Gemi protokolü '{$protocol['title']}' uygulandı. Anlık vitaller: nabız {$vitals['heart_rate_bpm']} BPM, SpO₂ %{$vitals['spo2_pct']}, ısı {$vitals['body_temp_c']} °C, triage {$vitals['triage_status']}.",
            'urgency' => $protocol['urgency'],
            'possible_causes' => $protocol['causes'],
            'immediate_steps' => $protocol['steps'],
            'medications' => $protocol['medications'],
            'follow_up_questions' => array_slice($protocol['questions'], 0, 4),
            'monitoring' => $protocol['monitoring'],
            'escalate_when' => $protocol['escalate'],
            'related_condition_id' => $protocol['related_condition_id'],
            'red_flags' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    public function buildContext(array $member): array
    {
        $mission = $this->healthEngine->missionStatus();
        $telemetry = $this->healthEngine->telemetry($member);
        $triage = $this->healthEngine->triage($telemetry, $mission['comm_delay_seconds']);
        $assessment = $this->spaceMedicine->assess($member, $mission['mission_day'], 0.6);

        return [
            'astronaut' => [
                'name' => $member['name'],
                'role' => $member['role'],
                'age' => $member['age'],
                'blood_type' => $member['blood_type'],
                'location' => $member['habitat'],
            ],
            'mission' => [
                'day' => $mission['mission_day'],
                'phase' => $assessment['phase']['label'],
                'comm_delay_seconds' => $mission['comm_delay_seconds'],
                'round_trip_seconds' => $mission['round_trip_seconds'],
                'solar_storm_active' => $mission['emergency'],
            ],
            'vitals' => [
                'heart_rate_bpm' => $telemetry['heart_rate'],
                'hrv_ms' => $telemetry['hrv_ms'],
                'spo2_pct' => $telemetry['spo2'],
                'blood_pressure' => $telemetry['blood_pressure'],
                'body_temp_c' => $telemetry['body_temp_c'],
                'respiratory_rate' => $telemetry['respiratory_rate'],
                'hydration_pct' => $telemetry['hydration_pct'],
                'radiation_msv_day' => $telemetry['radiation_msv'],
                'cumulative_dose_msv' => $telemetry['cumulative_dose_msv'],
                'cognitive_index' => $telemetry['cognitive_index'],
                'cabin_co2_pct' => $telemetry['cabin_co2'],
                'triage_status' => $triage['status'],
            ],
            'top_spaceflight_risks' => collect($assessment['conditions'])->take(6)->map(fn (array $condition): array => [
                'id' => $condition['id'],
                'name' => $condition['name'],
                'risk' => $condition['projection']['risk'],
                'value' => $condition['projection']['value'],
            ])->all(),
            'pharmacy_stock' => Medication::query()->orderBy('name')->get()->map(fn (Medication $medication): string => sprintf(
                '%s %s — %d %s%s',
                $medication->name,
                $medication->strength,
                $medication->quantity,
                $medication->unit,
                match ($medication->stockStatus()) {
                    'out' => ' (TÜKENDİ — önerme)',
                    'low' => ' (KRİTİK STOK — idareli kullan)',
                    default => '',
                },
            ))->all(),
            'equipment' => ['O2 maskesi', '12 kanallı EKG', 'ultrason', 'otoskop', 'oftalmoskop/OCT', 'kamera'],
            'health_history' => HealthEvent::query()
                ->where('member_id', $member['id'])
                ->whereIn('category', ['consult', 'alert', 'triage', 'cognitive', 'countermeasure'])
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (HealthEvent $event): string => $event->created_at->diffForHumans().' · '.$event->title.': '.Str::limit((string) $event->message, 160))
                ->all(),
        ];
    }

    /**
     * Links recommended medications to on-board stock so the crew can dispense with one tap.
     *
     * @param  array<int, array<string, mixed>>  $medications
     * @return array<int, array<string, mixed>>
     */
    protected function attachInventory(array $medications): array
    {
        $stock = Medication::all();

        return array_map(function (array $recommendation) use ($stock): array {
            $name = $this->normalize((string) ($recommendation['name'] ?? ''));
            $match = $stock->first(fn (Medication $medication): bool => $name !== '' && (
                str_contains($name, $this->normalize(Str::before($medication->name, ' ')))
                || str_contains($this->normalize($medication->name), Str::before($name, ' '))
            ));

            return $recommendation + [
                'inventory_id' => $match?->id,
                'in_stock' => $match ? $match->quantity : null,
                'stock_status' => $match?->stockStatus(),
                'unit' => $match?->unit,
            ];
        }, $medications);
    }

    /**
     * @param  array<string, mixed>  $member
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    protected function askGemini(array $member, string $message, array $history, array $context, ?array $protocol = null, ?string $image = null): ?array
    {
        if (! $this->gemini->isConfigured()) {
            return null;
        }

        $messages = [['role' => 'system', 'content' => $this->systemPrompt($context, $protocol)]];

        foreach (array_slice($history, -8) as $turn) {
            if (in_array($turn['role'] ?? null, ['user', 'assistant'], true) && filled($turn['content'] ?? null)) {
                $messages[] = ['role' => $turn['role'], 'content' => Str::limit((string) $turn['content'], 1500)];
            }
        }

        $prompt = "{$member['name']} diyor ki: \"{$message}\"";

        if ($image !== null) {
            $prompt .= "\n\nAstronot şikâyetiyle ilgili bir FOTOĞRAF ekledi. Görüntüyü dikkatle incele; gördüğün bulguları (renk, boyut, sınır, şişlik, akıntı, simetri vb.) image_findings alanında klinik dille tarif et ve değerlendirmene kat.";
        }

        $messages[] = $this->gemini->userMessage($prompt, $image);

        $content = $this->gemini->chat($messages, $image !== null ? 2000 : 1600);

        return $content === null ? null : $this->parseAnswer($content);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function systemPrompt(array $context, ?array $protocol = null): string
    {
        $conditionIds = collect($this->nasaData->spaceConditions())->pluck('name', 'id')->map(fn ($name, $id) => "{$id} ({$name})")->implode(', ');
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $protocolJson = $protocol
            ? json_encode(collect($protocol)->except(['keywords', 'spoken', 'score'])->all(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : 'Eşleşen protokol yok — şikâyeti OPQRST ile kendin değerlendir.';

        return <<<PROMPT
        Sen "Aegis", Mars yolundaki Orion aracında görev yapan yapay zekâ uçuş cerrahısın (flight surgeon).
        Gemide doktor yok ve Dünya ile iletişim tek yön {$context['mission']['comm_delay_seconds']} saniye gecikmeli. Astronot sana sesli olarak şikâyetini anlatıyor.
        Bu, profesyonel bir tıbbi karar destek ortamıdır; karşındaki kişi senin hastandır ve başka hekime erişimi yoktur.

        KESİN KURALLAR:
        - Her şikâyete — genital/penis/testis/vajina, idrar yolu, makat, meme, cinsel sağlık dahil — bir hekim gibi doğrudan, saygılı ve klinik bir dille yanıt ver. ASLA geçiştirme, utanma, konuyu değiştirme ya da "bir doktora başvurun" deme: o doktor SENSİN.
        - Genel laf etme; şikâyete ÖZGÜ ol. En az 3 olası neden, en az 3 somut ve uygulanabilir adım ver.
        - Sadece teşhis/test sayma: HER cevapta mutlaka ÇÖZÜM ver — şikâyeti hafifletecek tedavi (kitteki ilaç adı, dozu, sıklığı), rahatlatıcı önlemler, kaçınılacaklar ve zaman çizelgeli plan (treatment_plan). Test sonucu beklenirken bile semptomatik tedaviyi hemen başlat.
        - Değerlendirmeyi OPQRST mantığıyla yap (başlangıç, tetikleyen, karakter, yer/yayılım, şiddet, süre). Eksik bilgi varsa follow_up_questions içinde sor ama yine de şimdiki bilgiyle en iyi ön değerlendirmeyi yap.
        - Aşağıdaki GEMİ PROTOKOLÜ'nü klinik temel olarak kullan; vitallere ve konuşma geçmişine göre kişiselleştir, gerekirse genişlet.
        - Türkçe, sakin, net ve güven veren bir dille konuş. Astronotun adını kullan.
        - Uzay tıbbına özgü nedenleri öncelikle değerlendir: mikroyerçekimi sıvı kayması, kemik/kas kaybı, SANS, uzay adaptasyon sendromu, radyasyon, kabin CO2, omurga uzaması, izolasyon. Ama Dünya'daki yaygın nedenleri de (enfeksiyon, kas zorlanması, barotravma) atlama.
        - Canlı vitalleri ve görev bağlamını MUTLAKA yorumuna kat; ilgili bir değer varsa sayısıyla belirt.
        - Sadece pharmacy_stock listesinde STOKTA olan ilaçları öner; TÜKENDİ olanı önerme, alternatif ver. KRİTİK STOK ilaçlarda idareli kullanımı belirt. Doz yazarken standart yetişkin dozunu kullan.
        - health_history'yi incele: şikâyet tekrarlıyorsa, kötüleşiyorsa ya da önceki bir olayla (güneş fırtınası, düşük PVT, egzersiz) ilişkiliyse bunu history_insight alanında açıkça belirt ve değerlendirmene kat.
        - Kırmızı bayrak (göğüs ağrısı, nefes darlığı, bilinç değişikliği, inme bulguları, ciddi kanama) varsa urgency "emergency" olsun.
        - Tanı koymadığını, karar destek sağladığını unutma; ama gereksiz uzun uyarı yazma.

        CANLI BAĞLAM (sensörlerden):
        {$contextJson}

        GEMİ PROTOKOLÜ (şikâyetle eşleşen):
        {$protocolJson}

        İlgili durum kimlikleri: {$conditionIds}

        YALNIZCA aşağıdaki şemada geçerli JSON döndür, başka metin ekleme:
        {
          "spoken_reply": "Sesli okunacak 3-4 cümle: (1) en olası neden, (2) ŞİMDİ ne yapacağı — ilaç adı ve dozu dahil somut çözüm, (3) ne zaman tekrar haber vermesi gerektiği",
          "treatment_plan": {
            "now": ["İlk 30 dakikada yapılacaklar — rahatlatıcı tedavi, ilaç adı + doz + saat dahil"],
            "next_24h": ["Sonraki 24 saatte uygulanacak tedavi ve takip adımları"],
            "avoid": ["Kaçınması gereken şeyler (aktivite, ilaç, davranış)"],
            "expected_course": "Tedaviyle beklenen iyileşme süresi ve ne zaman yeniden değerlendirileceği"
          },
          "summary": "Klinik değerlendirme özeti (2-3 cümle)",
          "urgency": "routine | monitor | urgent | emergency",
          "possible_causes": [{"name": "...", "likelihood": 0-100, "space_related": true, "reason": "kısa gerekçe"}],
          "immediate_steps": ["şimdi yapılacak adımlar"],
          "medications": [{"name": "...", "dose": "...", "note": "..."}],
          "follow_up_questions": ["netleştirmek için astronota sorulacak kısa sorular"],
          "monitoring": ["izlenecek vital veya bulgu"],
          "escalate_when": "Hangi durumda Dünya'ya acil rapor / acil müdahale",
          "related_condition_id": "yukarıdaki kimliklerden biri veya null",
          "history_insight": "Sağlık geçmişiyle bağlantı (tekrarlama, kötüleşme, ilişkili olay) veya null",
          "image_findings": "Fotoğraf varsa görsel bulguların klinik tarifi, yoksa null"
        }
        PROMPT;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseAnswer(string $content): ?array
    {
        $json = Str::of($content)->replaceMatches('/^```(?:json)?|```$/m', '')->trim()->toString();

        if (! str_starts_with($json, '{') && preg_match('/\{.*\}/s', $json, $match)) {
            $json = $match[0];
        }

        $data = json_decode($json, true);

        if (! is_array($data) || blank($data['spoken_reply'] ?? $data['summary'] ?? null)) {
            return null;
        }

        return [
            'spoken_reply' => (string) ($data['spoken_reply'] ?? $data['summary']),
            'summary' => (string) ($data['summary'] ?? ''),
            'urgency' => in_array($data['urgency'] ?? null, ['routine', 'monitor', 'urgent', 'emergency'], true) ? $data['urgency'] : 'monitor',
            'possible_causes' => array_slice(array_values(array_filter((array) ($data['possible_causes'] ?? []), 'is_array')), 0, 5),
            'immediate_steps' => array_values(array_map('strval', (array) ($data['immediate_steps'] ?? []))),
            'medications' => array_values(array_filter((array) ($data['medications'] ?? []), 'is_array')),
            'follow_up_questions' => array_slice(array_values(array_map('strval', (array) ($data['follow_up_questions'] ?? []))), 0, 4),
            'monitoring' => array_values(array_map('strval', (array) ($data['monitoring'] ?? []))),
            'escalate_when' => (string) ($data['escalate_when'] ?? ''),
            'related_condition_id' => is_string($data['related_condition_id'] ?? null) ? $data['related_condition_id'] : null,
            'treatment_plan' => [
                'now' => array_values(array_map('strval', (array) ($data['treatment_plan']['now'] ?? []))),
                'next_24h' => array_values(array_map('strval', (array) ($data['treatment_plan']['next_24h'] ?? []))),
                'avoid' => array_values(array_map('strval', (array) ($data['treatment_plan']['avoid'] ?? []))),
                'expected_course' => (string) ($data['treatment_plan']['expected_course'] ?? ''),
            ],
            'history_insight' => filled($data['history_insight'] ?? null) && is_string($data['history_insight']) ? $data['history_insight'] : null,
            'image_findings' => filled($data['image_findings'] ?? null) && is_string($data['image_findings']) ? $data['image_findings'] : null,
            'red_flags' => [],
        ];
    }

    /**
     * On-board fallback: matches the complaint against the NASA HRP condition atlas.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function offlineAnswer(string $message, array $context): array
    {
        $protocol = $this->matchProtocol($message);

        if ($protocol !== null) {
            return $this->protocolAnswer($protocol, $context);
        }

        $normalized = $this->normalize($message);
        $conditions = collect($this->nasaData->spaceConditions())->keyBy('id');
        $scores = collect(self::KEYWORDS)
            ->map(fn (array $needles): int => collect($needles)->filter(fn (string $needle): bool => str_contains($normalized, $needle))->count())
            ->filter()
            ->sortDesc();

        $matches = $scores->keys()->take(3)->map(fn (string $id): array => $conditions[$id])->values();
        $primary = $matches->first();
        $name = Str::before($context['astronaut']['name'], ' ');

        if ($primary === null) {
            return [
                'spoken_reply' => "{$name}, şikâyetini tam eşleştiremedim. Ağrının yerini, ne zaman başladığını ve şiddetini 1'den 10'a kadar söyler misin?",
                'summary' => 'Çevrimdışı motor şikâyeti atlas kategorilerinden biriyle eşleştiremedi; ek bilgi gerekiyor.',
                'urgency' => 'monitor',
                'possible_causes' => [],
                'immediate_steps' => ['Vitaller izleniyor; yeni belirti olursa hemen bildir.'],
                'medications' => [],
                'follow_up_questions' => ['Ağrı/şikâyet tam olarak nerede?', 'Ne zaman başladı?', 'Şiddeti 1-10 arası kaç?'],
                'monitoring' => ['Kalp hızı', 'Vücut ısısı'],
                'escalate_when' => 'Belirtiler artarsa veya yeni belirti eklenirse.',
                'related_condition_id' => null,
                'red_flags' => [],
            ];
        }

        $isEar = str_contains($normalized, 'kulak');

        return [
            'spoken_reply' => $isEar
                ? "{$name}, kulak ağrısı ve basınç hissi uzayda sık görülür; sıvı başa kaydığı için iç kulak ve sinüsler etkilenir. Valsalva manevrası dene, psödoefedrin alabilirsin, işitme kaybı ya da akıntı olursa hemen söyle."
                : "{$name}, şikâyetin en çok {$primary['name']} tablosuyla uyumlu. {$primary['countermeasures'][0]} öneriyorum; vitallerini izlemeye devam ediyorum.",
            'summary' => "Çevrimdışı Aegis motoru şikâyeti NASA HRP atlasındaki '{$primary['name']}' riskiyle eşleştirdi. Kalp hızı {$context['vitals']['heart_rate_bpm']} BPM, SpO₂ %{$context['vitals']['spo2_pct']}, triage durumu {$context['vitals']['triage_status']}.",
            'urgency' => $context['vitals']['triage_status'] === 'CRITICAL' ? 'urgent' : 'monitor',
            'possible_causes' => $matches->map(fn (array $condition, int $index): array => [
                'name' => $condition['name'],
                'likelihood' => max(20, 70 - $index * 20),
                'space_related' => true,
                'reason' => Str::limit($condition['fact'], 120),
            ])->all(),
            'immediate_steps' => $isEar
                ? ['Valsalva manevrası (burun kapalı hafif üfleme)', 'Kabin basıncı kontrolü', 'Otoskop ile kulak zarı muayenesi']
                : array_slice($primary['countermeasures'], 0, 3),
            'medications' => $isEar ? [['name' => 'Psödoefedrin', 'dose' => '60 mg', 'note' => 'Günde en fazla 4 kez']] : [],
            'follow_up_questions' => ['Şiddeti 1-10 arası kaç?', 'Ne zamandan beri var?', 'Başka belirti eşlik ediyor mu?'],
            'monitoring' => $primary['sensors'],
            'escalate_when' => 'Ağrı 24 saatte geçmezse, ateş, uyuşma veya güç kaybı eklenirse.',
            'related_condition_id' => $primary['id'],
            'red_flags' => [],
        ];
    }
}
