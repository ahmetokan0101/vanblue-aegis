<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Simulates crew bio-telemetry and runs the autonomous, rule-based triage against NASA HRP / NASA-STD-3001 thresholds.
 */
class CrewHealthEngine
{
    public const string EMERGENCY_CACHE_KEY = 'vanblue.emergency_started_at';

    public const int SHELTER_DEADLINE_SECONDS = 480;

    protected const float SPEED_OF_LIGHT_KM_S = 299792.458;

    public function __construct(protected NasaDataRepository $nasaData) {}

    public function isEmergencyActive(): bool
    {
        return Cache::has(self::EMERGENCY_CACHE_KEY);
    }

    public function setEmergency(bool $isActive): void
    {
        if ($isActive) {
            Cache::put(self::EMERGENCY_CACHE_KEY, now()->timestamp, now()->addHour());

            return;
        }

        Cache::forget(self::EMERGENCY_CACHE_KEY);
    }

    public function emergencySecondsRemaining(): ?int
    {
        $startedAt = Cache::get(self::EMERGENCY_CACHE_KEY);

        if ($startedAt === null) {
            return null;
        }

        return max(0, self::SHELTER_DEADLINE_SECONDS - (now()->timestamp - (int) $startedAt));
    }

    /**
     * Mission clock, Earth distance and one-way light-time communication delay.
     *
     * @return array<string, mixed>
     */
    public function missionStatus(?Carbon $at = null): array
    {
        $at ??= now();
        $manifest = $this->nasaData->missionManifest();
        $launch = Carbon::parse($manifest['launch_date'])->startOfDay();
        $elapsedSeconds = max(0, $launch->diffInSeconds($at, false));
        $missionDay = (int) floor($elapsedSeconds / 86400) + 1;
        $progress = min(1, $elapsedSeconds / ($manifest['transit_days'] * 86400));
        $distanceKm = $manifest['max_earth_distance_km'] * ($progress ** 1.2);
        $mslDaily = $this->nasaData->mslRadiation()['radiation_environment']['galactic_cosmic_rays_gcr']['average_daily_dose_equivalent_msv_d'] ?? 1.84;

        return [
            'name' => $manifest['name'],
            'vehicle' => $manifest['vehicle'],
            'launch_date' => $launch->toDateString(),
            'mission_day' => $missionDay,
            'transit_days' => $manifest['transit_days'],
            'days_to_mars' => max(0, $manifest['transit_days'] - $missionDay),
            'progress_pct' => round($progress * 100, 2),
            'earth_distance_km' => round($distanceKm),
            'mars_distance_km' => round(max(0, (1 - $progress) * 255_000_000)),
            'velocity_km_s' => round(24.1 + 2.6 * sin($progress * M_PI), 2),
            'comm_delay_seconds' => (int) round($distanceKm / self::SPEED_OF_LIGHT_KM_S),
            'round_trip_seconds' => (int) round(2 * $distanceKm / self::SPEED_OF_LIGHT_KM_S),
            'met_seconds' => $elapsedSeconds,
            'msl_gcr_daily_msv' => $mslDaily,
            'emergency' => $this->isEmergencyActive(),
            'shelter_seconds_remaining' => $this->emergencySecondsRemaining(),
        ];
    }

    /**
     * Generates a smooth, time-continuous telemetry frame for one crew member.
     *
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    public function telemetry(array $member, ?bool $isEmergency = null, ?int $timestamp = null): array
    {
        $isEmergency ??= $this->isEmergencyActive();
        $time = $timestamp ?? now()->timestamp;
        $seed = (crc32($member['id']) % 1000) / 100;
        $wave = fn (float $period, float $phase = 0): float => sin($time / $period + $seed + $phase);
        $jitter = fn (float $amplitude): float => (mt_rand(-100, 100) / 100) * $amplitude;

        [$systolic, $diastolic] = array_map('intval', explode('/', str_replace(' ', '', $member['base_bp'])));
        $missionDay = $this->missionStatus()['mission_day'];

        $heartRate = $member['base_hr'] + 2.5 * $wave(9) + 1.5 * $wave(3.7, 1.3) + $jitter(1);
        $spo2 = 98.4 + 0.5 * $wave(13, 0.4) + $jitter(0.2);
        $bodyTemp = $member['base_temp'] + 0.12 * $wave(40);
        $respiratoryRate = $member['base_rr'] + 1.2 * $wave(11, 2.1);
        $doseRate = $member['daily_dose_msv'] + 0.04 * $wave(17) + $jitter(0.02);

        if ($isEmergency) {
            $heartRate += 38 + 5 * $wave(2.3);
            $spo2 -= 3.6 + 0.6 * $wave(3.1);
            $bodyTemp += 0.5;
            $respiratoryRate += 7;
            $systolic += 18;
            $diastolic += 10;
            $doseRate = 6.6 + 0.9 * $wave(4.2) + $jitter(0.2);
        }

        $cognitive = $member['radar'][4] - ($isEmergency ? 14 : 0) + 2 * $wave(30);

        return [
            'heart_rate' => (int) round($heartRate),
            'hrv_ms' => (int) round(($isEmergency ? 24 : 58) + 6 * $wave(21, 0.7)),
            'spo2' => round(min(99.9, $spo2), 1),
            'body_temp_c' => round($bodyTemp, 2),
            'respiratory_rate' => (int) round($respiratoryRate),
            'systolic' => (int) round($systolic + 3 * $wave(15)),
            'diastolic' => (int) round($diastolic + 2 * $wave(15, 0.5)),
            'blood_pressure' => sprintf('%d / %d', round($systolic + 3 * $wave(15)), round($diastolic + 2 * $wave(15, 0.5))),
            'hydration_pct' => round($member['base_hydration'] + 0.4 * $wave(60), 1),
            'radiation_msv' => round($doseRate, 2),
            'cumulative_dose_msv' => round($member['daily_dose_msv'] * $missionDay, 1),
            'cabin_co2' => round(0.41 + 0.02 * $wave(25), 2),
            'cabin_pressure_kpa' => round(101.3 + 0.05 * $wave(45), 2),
            'cabin_o2_pct' => round(21.0 + 0.1 * $wave(50), 1),
            'cognitive_index' => (int) round(max(0, min(100, $cognitive))),
            'fatigue_risk_pct' => (int) round(max(2, min(95, ($isEmergency ? 41 : 8) + 3 * $wave(70)))),
            'sans_grade' => 0,
            'radar' => $isEmergency
                ? [max(40, $member['radar'][0] - 22), max(40, $member['radar'][1] - 9), max(20, $member['radar'][2] - 55), $member['radar'][3], max(40, $member['radar'][4] - 14)]
                : $member['radar'],
        ];
    }

    /**
     * Rule-based autonomous triage — runs locally, no Earth link required.
     *
     * @param  array<string, mixed>  $telemetry
     * @return array<string, mixed>
     */
    public function triage(array $telemetry, int $commDelaySeconds): array
    {
        $startedAt = hrtime(true);
        $standards = $this->nasaData->hrpThresholds()['biometric_reference_standards'] ?? [];
        $radiation = $this->nasaData->mslRadiation();

        $heart = $standards['heart_rate_bpm'] ?? ['normal_range_resting' => [60, 85], 'alert_tachycardia' => 105, 'alert_bradycardia' => 48];
        $oxygen = $standards['oxygen_saturation_spo2'] ?? ['hypoxia_caution' => 93.0, 'critical_hypoxia' => 89.0];
        $temperature = $standards['core_body_temperature'] ?? ['space_fever_onset_c' => 37.8];
        $pel = $standards['permissible_exposure_limit_pel'] ?? ['career_limit_msv' => 600, '30_day_limit_msv' => 50];
        $warningDose = $radiation['warning_threshold_msv'] ?? 3.0;
        $criticalDose = $radiation['critical_solar_flare_threshold_msv'] ?? 5.0;

        $findings = [];
        $actions = [];
        $riskScore = 0;

        $heartRate = $telemetry['heart_rate'];
        if ($heartRate >= $heart['alert_tachycardia']) {
            $findings[] = $this->finding('heart_rate', 'critical', "Taşikardi: {$heartRate} BPM ≥ {$heart['alert_tachycardia']} BPM eşiği", 'Genel klinik referans (AHA) — prototip eşiği');
            $actions[] = 'Göğüs biyopakından 12 kanallı EKG kaydı başlat; beta-bloker protokolünü hazırla.';
            $riskScore += 30;
        } elseif ($heartRate <= $heart['alert_bradycardia']) {
            $findings[] = $this->finding('heart_rate', 'critical', "Bradikardi: {$heartRate} BPM", 'Genel klinik referans (AHA) — prototip eşiği');
            $riskScore += 30;
        } elseif ($heartRate > $heart['normal_range_resting'][1] || $heartRate < $heart['normal_range_resting'][0]) {
            $findings[] = $this->finding('heart_rate', 'warning', "Kalp hızı istirahat aralığı dışında ({$heartRate} BPM)", 'Genel klinik referans (AHA)');
            $riskScore += 10;
        } else {
            $findings[] = $this->finding('heart_rate', 'nominal', "Sinüs ritmi {$heartRate} BPM — normal aralıkta", 'Genel klinik referans (AHA)');
        }

        $spo2 = $telemetry['spo2'];
        if ($spo2 <= $oxygen['critical_hypoxia']) {
            $findings[] = $this->finding('spo2', 'critical', "Kritik hipoksi: SpO₂ %{$spo2}", 'Genel klinik referans (SpO₂ ≥%95)');
            $actions[] = 'Acil O₂ maskesi; kabin O₂ kısmi basıncını yükselt.';
            $riskScore += 35;
        } elseif ($spo2 <= $oxygen['hypoxia_caution'] + 2) {
            $findings[] = $this->finding('spo2', 'warning', "SpO₂ düşüş eğiliminde (%{$spo2})", 'Genel klinik referans (SpO₂ ≥%95)');
            $actions[] = 'Pulse oksimetre ile doğrula; efor seviyesini düşür.';
            $riskScore += 12;
        } else {
            $findings[] = $this->finding('spo2', 'nominal', "Oksijenasyon %{$spo2} — hedef aralıkta", 'Genel klinik referans (SpO₂ ≥%95)');
        }

        if ($telemetry['body_temp_c'] >= $temperature['space_fever_onset_c']) {
            $findings[] = $this->finding('body_temp_c', 'warning', "Uzay ateşi: {$telemetry['body_temp_c']} °C", 'Stahn et al., Sci. Rep. 2017');
            $riskScore += 10;
        } else {
            $findings[] = $this->finding('body_temp_c', 'nominal', "Çekirdek ısı {$telemetry['body_temp_c']} °C", 'Stahn et al., Sci. Rep. 2017');
        }

        $doseRate = $telemetry['radiation_msv'];
        if ($doseRate >= $criticalDose) {
            $findings[] = $this->finding('radiation_msv', 'critical', "Güneş parçacık olayı (SPE): {$doseRate} mSv/gün ≥ {$criticalDose}", 'MSL RAD — Zeitlin et al., Science 2013');
            $actions[] = 'ACİL: Tüm mürettebat 8 dk içinde su/polietilen korumalı fırtına sığınağına (>20 g/cm²) geçsin.';
            $actions[] = 'EVA ve dış modül aktivitelerini derhal iptal et.';
            $riskScore += 40;
        } elseif ($doseRate >= $warningDose) {
            $findings[] = $this->finding('radiation_msv', 'warning', "Yüksek doz hızı: {$doseRate} mSv/gün", 'NASA MSL RAD');
            $actions[] = 'Aktif dozimetreyi izle, EVA planlarını ertele.';
            $riskScore += 15;
        } else {
            $findings[] = $this->finding('radiation_msv', 'nominal', "GCR doz hızı {$doseRate} mSv/gün — beklenen aralık", 'NASA MSL RAD');
        }

        $careerPct = round($telemetry['cumulative_dose_msv'] / $pel['career_limit_msv'] * 100, 1);
        $findings[] = $this->finding(
            'cumulative_dose_msv',
            $careerPct >= 80 ? 'critical' : ($careerPct >= 50 ? 'warning' : 'nominal'),
            "Kümülatif doz {$telemetry['cumulative_dose_msv']} mSv — kariyer limitinin %{$careerPct}'i",
            'NASA-STD-3001 Vol.1 Rev B (2022)'
        );

        if ($telemetry['cognitive_index'] < 80) {
            $findings[] = $this->finding('cognitive_index', 'warning', "Bilişsel uyanıklık düşük ({$telemetry['cognitive_index']}/100)", 'PVT-B (Basner & Dinges 2011)');
            $actions[] = 'Kritik görevleri ikinci mürettebat onayına bağla (two-person rule).';
            $riskScore += 8;
        }

        $riskScore = min(100, $riskScore);
        $status = match (true) {
            $riskScore >= 40 => 'CRITICAL',
            $riskScore >= 10 => 'CAUTION',
            default => 'NOMINAL',
        };

        if ($actions === []) {
            $actions[] = 'Sistem stabil. Rutin görev protokollerine ve günlük karşı-tedbir programına devam.';
        }

        $messages = [
            'CRITICAL' => 'Güneş fırtınası algılandı! İyonlaştırıcı radyasyon akısı ve kardiyak yük tehlikeli seviyede.',
            'CAUTION' => 'Bazı biyometrik parametreler sınırda — yakın izleme önerilir.',
            'NOMINAL' => 'Kardiyovasküler ritim, oksijenasyon ve doz hızı NASA HRP standartlarında dengeli.',
        ];

        return [
            'status' => $status,
            'risk_score' => $riskScore,
            'health_index' => 100 - $riskScore,
            'message' => $messages[$status],
            'recommended_action' => $actions[0],
            'actions' => $actions,
            'findings' => $findings,
            'earth_delay_seconds' => $commDelaySeconds,
            'decision_latency_ms' => round((hrtime(true) - $startedAt) / 1e6, 3),
            'time_saved_seconds' => $commDelaySeconds * 2,
            'engine' => 'VanBlue Aegis Rule Engine v2 (on-board, offline)',
        ];
    }

    /**
     * @return array{metric: string, level: string, text: string, citation: string}
     */
    protected function finding(string $metric, string $level, string $text, string $citation): array
    {
        return ['metric' => $metric, 'level' => $level, 'text' => $text, 'citation' => $citation];
    }
}
