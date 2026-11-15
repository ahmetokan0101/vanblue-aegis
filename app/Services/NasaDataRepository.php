<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Reads the locally mirrored NASA datasets (HRP, MSL RAD, OSDR, astronaut registry) and the VanBlue crew manifest.
 */
class NasaDataRepository
{
    public function __construct(protected ?string $baseDirectory = null)
    {
        $this->baseDirectory ??= database_path('nasa_data');
    }

    /**
     * @return array<string, mixed>
     */
    public function hrpThresholds(): array
    {
        return $this->readJson('nasa_hrp_clinical_thresholds.json');
    }

    /**
     * @return array<string, mixed>
     */
    public function mslRadiation(): array
    {
        return $this->readJson('nasa_msl_radiation_dosimetry.json');
    }

    /**
     * @return array<string, mixed>
     */
    public function osdrStudies(): array
    {
        return $this->readJson('nasa_osdr_studies.json');
    }

    /**
     * Spaceflight medical conditions catalogue (NASA HRP risks).
     *
     * @return array<int, array<string, mixed>>
     */
    public function spaceConditions(): array
    {
        return $this->readJson('space_medical_conditions.json')['conditions'] ?? [];
    }

    /**
     * On-board symptom protocols used to ground the AI flight surgeon and as offline fallback.
     *
     * @return array<int, array<string, mixed>>
     */
    public function onboardProtocols(): array
    {
        return $this->readJson('onboard_medical_protocols.json')['protocols'] ?? [];
    }

    /**
     * @return array{name: string, vehicle: string, launch_date: string, transit_days: int, max_earth_distance_km: int}
     */
    public function missionManifest(): array
    {
        return $this->readJson('vanblue_crew.json')['mission'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function crew(): array
    {
        return $this->readJson('vanblue_crew.json')['crew'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function crewMember(string $memberId): ?array
    {
        return $this->crew()[$memberId] ?? null;
    }

    /**
     * Aggregated statistics computed from the 1,277-row astronaut mission registry CSV.
     *
     * @return array{total_missions: int, unique_astronauts: int, total_flight_hours: float, total_eva_hours: float, by_nationality: array<int, array{name: string, value: int}>, by_decade: array<int, array{decade: string, missions: int}>, sex_ratio: array<string, int>, top_flight_hours: array<int, array{name: string, nationality: string, hours: float}>, top_eva_hours: array<int, array{name: string, nationality: string, hours: float}>, file_size_kb: float}
     */
    public function astronautRegistryStats(): array
    {
        $path = $this->path('nasa_astronauts_registry.csv');

        return Cache::remember('nasa.astronaut_stats.'.filemtime($path), now()->addDay(), function () use ($path): array {
            $rows = $this->readCsv($path);
            $astronauts = $rows->unique('name');

            $byNationality = $astronauts->countBy('nationality')->sortDesc();
            $topNationalities = $byNationality->take(7)
                ->map(fn (int $count, string $nation): array => ['name' => $nation, 'value' => $count])
                ->values();
            $otherCount = $byNationality->slice(7)->sum();
            if ($otherCount > 0) {
                $topNationalities->push(['name' => 'Diğer', 'value' => $otherCount]);
            }

            $topBy = fn (string $column): array => $astronauts
                ->sortByDesc(fn (array $row): float => (float) $row[$column])
                ->take(8)
                ->map(fn (array $row): array => [
                    'name' => $row['name'],
                    'nationality' => $row['nationality'],
                    'hours' => round((float) $row[$column], 1),
                ])
                ->values()
                ->all();

            return [
                'total_missions' => $rows->count(),
                'unique_astronauts' => $astronauts->count(),
                'total_flight_hours' => round($astronauts->sum(fn (array $row): float => (float) $row['total_hrs_sum'])),
                'total_eva_hours' => round($astronauts->sum(fn (array $row): float => (float) $row['total_eva_hrs']), 1),
                'by_nationality' => $topNationalities->all(),
                'by_decade' => $rows->groupBy(fn (array $row): string => (intdiv((int) $row['year_of_mission'], 10) * 10).'s')
                    ->sortKeys()
                    ->map(fn (Collection $group, string $decade): array => ['decade' => $decade, 'missions' => $group->count()])
                    ->values()
                    ->all(),
                'sex_ratio' => $astronauts->countBy('sex')->all(),
                'top_flight_hours' => $topBy('total_hrs_sum'),
                'top_eva_hours' => $topBy('total_eva_hrs'),
                'file_size_kb' => round(filesize($path) / 1024, 1),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function readJson(string $file): array
    {
        $contents = @file_get_contents($this->path($file));

        return $contents === false ? [] : (json_decode($contents, true) ?? []);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    protected function readCsv(string $path): Collection
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '\\');
        $rows = [];

        while (($line = fgetcsv($handle, escape: '\\')) !== false) {
            if (count($line) === count($header)) {
                $rows[] = array_combine($header, $line);
            }
        }

        fclose($handle);

        return collect($rows);
    }

    protected function path(string $file): string
    {
        return $this->baseDirectory.DIRECTORY_SEPARATOR.$file;
    }
}
