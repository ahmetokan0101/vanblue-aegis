<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live solar flare / CME feed from NASA DONKI (Space Weather Database Of Notifications, Knowledge, Information),
 * with an offline fallback built from the MSL RAD solar particle event archive.
 */
class SpaceWeatherService
{
    public const string CACHE_KEY = 'nasa.donki.summary';

    public function __construct(protected NasaDataRepository $nasaData) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(int $days = 30): array
    {
        $cacheKey = self::CACHE_KEY.".{$days}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $live = $this->fetchFromDonki($days);
        $summary = $live ?? $this->offlineFallback();
        Cache::put($cacheKey, $summary, $live ? now()->addMinutes(30) : now()->addMinutes(5));

        return $summary;
    }

    /**
     * Tries the api.nasa.gov gateway first, then the CCMC origin server (no key required).
     *
     * @return array<string, mixed>|null
     */
    protected function fetchFromDonki(int $days): ?array
    {
        $dates = [
            'startDate' => now()->subDays($days)->toDateString(),
            'endDate' => now()->toDateString(),
        ];
        $sources = [
            [rtrim(config('services.nasa.donki_url'), '/'), $dates + ['api_key' => config('services.nasa.api_key')]],
            [rtrim(config('services.nasa.donki_ccmc_url'), '/'), $dates],
        ];

        foreach ($sources as [$baseUrl, $query]) {
            $summary = $this->fetchFromSource($baseUrl, $query);

            if ($summary !== null) {
                return $summary;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>|null
     */
    protected function fetchFromSource(string $baseUrl, array $query): ?array
    {
        $timeout = config('services.nasa.timeout');

        try {
            $flareResponse = Http::timeout($timeout)->acceptJson()->get("{$baseUrl}/FLR", $query);

            if (! $flareResponse->successful() || ! is_array($flareResponse->json())) {
                return null;
            }

            $cmeResponse = Http::timeout($timeout)->acceptJson()->get("{$baseUrl}/CME", $query);
        } catch (Throwable) {
            return null;
        }

        $flares = collect($flareResponse->json() ?? [])
            ->map(fn (array $flare): array => [
                'id' => $flare['flrID'] ?? null,
                'class' => $flare['classType'] ?? '—',
                'peak_time' => $flare['peakTime'] ?? $flare['beginTime'] ?? null,
                'source' => $flare['sourceLocation'] ?? '—',
                'region' => $flare['activeRegionNum'] ?? null,
                'rank' => $this->flareRank($flare['classType'] ?? ''),
                'link' => $flare['link'] ?? null,
            ])
            ->sortByDesc('peak_time')
            ->values();

        $cmeCount = $cmeResponse->successful() ? count($cmeResponse->json() ?? []) : null;

        return $this->buildSummary($flares->all(), $cmeCount, 'NASA DONKI (CCMC) — canlı', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function offlineFallback(): array
    {
        $events = $this->nasaData->mslRadiation()['radiation_environment']['solar_particle_events_spe'] ?? [];

        $flares = collect($events)->map(fn (array $event): array => [
            'id' => $event['event_id'],
            'class' => $event['solar_flare_class'],
            'peak_time' => substr($event['event_id'], 4).'T00:00Z',
            'source' => 'MSL RAD arşivi',
            'region' => null,
            'rank' => $this->flareRank($event['solar_flare_class']),
            'link' => null,
        ])->all();

        return $this->buildSummary($flares, null, 'NASA MSL RAD arşivi (çevrimdışı mod)', false);
    }

    /**
     * @param  array<int, array<string, mixed>>  $flares
     * @return array<string, mixed>
     */
    protected function buildSummary(array $flares, ?int $cmeCount, string $source, bool $isLive): array
    {
        $flareCollection = collect($flares);
        $strongest = $flareCollection->sortByDesc('rank')->first();
        $classCounts = ['A' => 0, 'B' => 0, 'C' => 0, 'M' => 0, 'X' => 0];

        foreach ($flareCollection as $flare) {
            $letter = strtoupper(substr((string) $flare['class'], 0, 1));
            if (array_key_exists($letter, $classCounts)) {
                $classCounts[$letter]++;
            }
        }

        $threat = match (true) {
            ($strongest['rank'] ?? 0) >= 40 => 'HIGH',
            ($strongest['rank'] ?? 0) >= 30 => 'ELEVATED',
            default => 'LOW',
        };

        return [
            'is_live' => $isLive,
            'source' => $source,
            'fetched_at' => now()->toIso8601String(),
            'flare_count' => $flareCollection->count(),
            'cme_count' => $cmeCount,
            'class_counts' => $classCounts,
            'strongest' => $strongest,
            'threat_level' => $threat,
            'flares' => $flareCollection->take(12)->values()->all(),
        ];
    }

    /**
     * Converts a GOES class (e.g. "X1.2", "M5.1") into a sortable numeric rank.
     */
    protected function flareRank(string $classType): float
    {
        $scale = ['A' => 0, 'B' => 10, 'C' => 20, 'M' => 30, 'X' => 40];
        $letter = strtoupper(substr($classType, 0, 1));

        return ($scale[$letter] ?? 0) + min(9.9, (float) substr($classType, 1));
    }
}
