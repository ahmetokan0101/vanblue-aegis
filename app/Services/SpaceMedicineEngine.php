<?php

namespace App\Services;

/**
 * Projects NASA HRP spaceflight health risks over the mission timeline, per crew member,
 * as a function of mission day and countermeasure compliance.
 */
class SpaceMedicineEngine
{
    public const int MARS_ARRIVAL_DAY = 210;

    public const int MARS_DEPARTURE_DAY = 710;

    public const int EARTH_RETURN_DAY = 920;

    public function __construct(
        protected NasaDataRepository $nasaData,
        protected CrewHealthEngine $healthEngine,
    ) {}

    /**
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    public function assess(array $member, int $day, float $compliance): array
    {
        $day = max(0, min(self::EARTH_RETURN_DAY, $day));
        $compliance = max(0.0, min(1.0, $compliance));
        $isEmergency = $this->healthEngine->isEmergencyActive();

        $conditions = collect($this->nasaData->spaceConditions())
            ->map(fn (array $condition): array => $condition + ['projection' => $this->project($condition['id'], $member, $day, $compliance, $isEmergency)])
            ->sortByDesc(fn (array $condition): int => $condition['projection']['risk'])
            ->values();

        $timelineDays = range(0, self::EARTH_RETURN_DAY, 20);

        return [
            'member_id' => $member['id'],
            'day' => $day,
            'phase' => $this->phase($day),
            'compliance' => $compliance,
            'overall_risk' => (int) round($conditions->avg(fn (array $condition): int => $condition['projection']['risk'])),
            'high_risk_count' => $conditions->filter(fn (array $condition): bool => $condition['projection']['level'] === 'critical')->count(),
            'conditions' => $conditions->all(),
            'timeline' => [
                'days' => $timelineDays,
                'series' => $conditions->mapWithKeys(fn (array $condition): array => [
                    $condition['id'] => array_map(fn (int $timelineDay): int => $this->project($condition['id'], $member, $timelineDay, $compliance, false)['risk'], $timelineDays),
                ])->all(),
            ],
            'milestones' => [
                ['day' => 0, 'label' => 'Fırlatma'],
                ['day' => self::MARS_ARRIVAL_DAY, 'label' => 'Mars inişi'],
                ['day' => self::MARS_DEPARTURE_DAY, 'label' => 'Mars kalkışı'],
                ['day' => self::EARTH_RETURN_DAY, 'label' => 'Dünya dönüşü'],
            ],
        ];
    }

    /**
     * @return array{key: string, label: string}
     */
    public function phase(int $day): array
    {
        return match (true) {
            $day < self::MARS_ARRIVAL_DAY => ['key' => 'transit_out', 'label' => 'Mars yolculuğu (mikroyerçekimi)'],
            $day < self::MARS_DEPARTURE_DAY => ['key' => 'surface', 'label' => 'Mars yüzeyi (0.38 g)'],
            default => ['key' => 'transit_back', 'label' => 'Dünya dönüşü (mikroyerçekimi)'],
        };
    }

    /**
     * Days spent in microgravity (Mars surface counts as partial 0.38 g unloading).
     */
    protected function unloadedDays(int $day): float
    {
        $outbound = min($day, self::MARS_ARRIVAL_DAY);
        $surface = max(0, min($day, self::MARS_DEPARTURE_DAY) - self::MARS_ARRIVAL_DAY);
        $inbound = max(0, $day - self::MARS_DEPARTURE_DAY);

        return $outbound + $inbound + $surface * 0.62;
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array{risk: int, level: string, value: string, metric: string, note: string}
     */
    protected function project(string $conditionId, array $member, int $day, float $compliance, bool $isEmergency): array
    {
        $unloaded = $this->unloadedDays($day);
        $months = $unloaded / 30.4;
        $saturate = fn (float $tau): float => 1 - exp(-$unloaded / $tau);

        [$risk, $value, $metric, $note] = match ($conditionId) {
            'bone_loss' => (function () use ($months, $compliance): array {
                $loss = $months * (1.5 - 1.3 * $compliance);

                return [$loss / 15 * 100, sprintf('-%%%.1f', $loss), 'Kalça kemik yoğunluğu', $loss >= 10 ? 'Osteoporoz eşiğine yaklaşıyor' : 'ARED uyumu kaybı yavaşlatıyor'];
            })(),
            'muscle_atrophy' => (function () use ($saturate, $compliance): array {
                $loss = 25 * $saturate(45) * (1 - 0.75 * $compliance);

                return [$loss / 25 * 100, sprintf('-%%%.1f', $loss), 'Baldır kas hacmi', 'İlk haftalarda en hızlı kayıp'];
            })(),
            'renal_stones' => (function () use ($months, $compliance, $member): array {
                $boneLoss = $months * (1.5 - 1.3 * $compliance);
                $hydrationPenalty = max(0, 97 - $member['base_hydration']) * 4;
                $risk = 8 + $boneLoss * 4.5 + $hydrationPenalty;

                return [$risk, sprintf('%d/100', min(100, $risk)), 'Taş oluşum skoru', 'Kemikten çıkan kalsiyum idrara geçiyor'];
            })(),
            'sans' => (function () use ($saturate, $compliance): array {
                $probability = 70 * $saturate(150) * (1 - 0.35 * $compliance);

                return [$probability, sprintf('%%%d olasılık', $probability), 'Optik disk ödemi', 'LBNP sıvıyı bacaklara geri çekiyor'];
            })(),
            'orthostatic' => (function () use ($saturate, $compliance): array {
                $probability = 20 + 63 * $saturate(120) * (1 - 0.3 * $compliance);

                return [$probability, sprintf('%%%d', $probability), 'İnişte ayakta durma güçlüğü', 'Mars inişinde yer ekibi yok'];
            })(),
            'radiation' => (function () use ($member, $day, $isEmergency): array {
                $outbound = min($day, self::MARS_ARRIVAL_DAY);
                $surface = max(0, min($day, self::MARS_DEPARTURE_DAY) - self::MARS_ARRIVAL_DAY);
                $inbound = max(0, $day - self::MARS_DEPARTURE_DAY);
                $dose = ($outbound + $inbound) * $member['daily_dose_msv'] + $surface * 0.64 * 0.7 + ($isEmergency ? 46.2 : 0);

                return [$dose / 600 * 100, sprintf('%d mSv', $dose), 'Kümülatif doz / 600 mSv', $dose > 600 ? 'NASA kariyer limiti aşıldı' : 'Limit altında'];
            })(),
            'immune' => (function () use ($saturate, $isEmergency): array {
                $probability = 15 + 46 * $saturate(60) + ($isEmergency ? 12 : 0);

                return [$probability, sprintf('%%%d', $probability), 'Latent virüs reaktivasyonu', 'Stres hormonları bağışıklığı baskılıyor'];
            })(),
            'vte' => (function () use ($saturate, $compliance): array {
                $probability = 5 + 50 * $saturate(90) * (1 - 0.4 * $compliance);

                return [$probability, sprintf('%%%d', $probability), 'Juguler akış durgunluğu', 'Haftalık boyun ultrasonu önerilir'];
            })(),
            'spine' => (function () use ($day, $saturate): array {
                $acutePain = 45 * exp(-$day / 40);
                $herniation = 25 * $saturate(200);
                $risk = max($acutePain, $herniation) + 10;

                return [$risk, sprintf('%d/100', $risk), $day < 60 ? 'Akut bel ağrısı' : 'Dönüşte fıtık riski', 'Diskler şişiyor, omurga uzuyor'];
            })(),
            'sas' => (function () use ($day): array {
                $probability = $day <= 3 ? 65 * exp(-$day / 2.5) : ($day >= self::MARS_ARRIVAL_DAY && $day <= self::MARS_ARRIVAL_DAY + 3 ? 40 : 3);

                return [$probability, sprintf('%%%d', $probability), 'Bulantı / yönelim kaybı', $day <= 3 ? 'Adaptasyon dönemi — EVA kilitli' : 'Vestibüler adaptasyon tamam'];
            })(),
            'sleep' => (function () use ($day, $isEmergency): array {
                $hours = $day >= self::MARS_ARRIVAL_DAY && $day < self::MARS_DEPARTURE_DAY ? 6.4 : 6.0;
                $hours -= $isEmergency ? 1.2 : 0;
                $risk = (7.5 - $hours) / 3 * 100;

                return [$risk, sprintf('%.1f saat', $hours), 'Ortalama uyku', $day >= self::MARS_ARRIVAL_DAY && $day < self::MARS_DEPARTURE_DAY ? 'Mars günü 24s 39dk' : 'Günde 16 gün doğumu'];
            })(),
            'behavioral' => (function () use ($day, $isEmergency): array {
                $delayFactor = $day < self::MARS_ARRIVAL_DAY ? $day / self::MARS_ARRIVAL_DAY : 1;
                $risk = 10 + 45 * (1 - exp(-$day / 260)) + 12 * $delayFactor + ($isEmergency ? 15 : 0);

                return [$risk, sprintf('%d/100', $risk), 'İzolasyon yükü', sprintf('%d gün evden uzak', $day)];
            })(),
            default => [0, '—', '—', ''],
        };

        $risk = (int) round(max(0, min(100, $risk)));

        return [
            'risk' => $risk,
            'level' => $risk >= 65 ? 'critical' : ($risk >= 35 ? 'warning' : 'nominal'),
            'value' => $value,
            'metric' => $metric,
            'note' => $note,
        ];
    }
}
