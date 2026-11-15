<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live study metadata from the NASA Open Science Data Repository (OSDR) Biological Data API,
 * with a verified local snapshot as offline fallback.
 */
class OsdrService
{
    /**
     * Curated OSDR studies and the VanBlue condition each one informs.
     *
     * @var array<string, string>
     */
    public const array STUDIES = [
        'OSD-87' => 'sans',
        'OSD-242' => 'sans',
        'OSD-137' => 'immune',
    ];

    public function __construct(protected NasaDataRepository $nasaData) {}

    /**
     * @return array{is_live: bool, source: string, studies: array<int, array<string, mixed>>}
     */
    public function studies(): array
    {
        $cached = Cache::get('nasa.osdr.studies');

        if ($cached !== null) {
            return $cached;
        }

        $studies = [];

        foreach (self::STUDIES as $accession => $conditionId) {
            $study = $this->fetchStudy($accession);

            if ($study === null) {
                $snapshot = $this->nasaData->osdrStudies();

                return [
                    'is_live' => false,
                    'source' => 'NASA OSDR — yerel doğrulanmış kopya (çevrimdışı)',
                    'studies' => $snapshot['studies'] ?? [],
                ];
            }

            $studies[] = $study + ['condition_id' => $conditionId];
        }

        $result = [
            'is_live' => true,
            'source' => 'NASA OSDR Biological Data API — canlı',
            'studies' => $studies,
        ];

        Cache::put('nasa.osdr.studies', $result, now()->addDay());

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchStudy(string $accession): ?array
    {
        $baseUrl = rtrim(config('services.nasa.osdr_url'), '/');

        try {
            $response = Http::timeout(config('services.nasa.timeout') * 3)->acceptJson()->get("{$baseUrl}/dataset/{$accession}/metadata/");
        } catch (Throwable) {
            return null;
        }

        $metadata = $response->json("{$accession}.metadata");

        if (! $response->successful() || ! is_array($metadata)) {
            return null;
        }

        $mission = $metadata['mission'] ?? [];
        $description = (string) ($metadata['study description'] ?? '');

        return [
            'accession' => $accession,
            'title' => $metadata['study title'] ?? $accession,
            'project' => is_array($metadata['project title'] ?? null) ? implode(' ', $metadata['project title']) : ($metadata['project title'] ?? null),
            'mission' => is_array($mission) ? ($mission['name'] ?? null) : $mission,
            'mission_start' => is_array($mission) ? ($mission['start date'] ?? null) : null,
            'mission_end' => is_array($mission) ? ($mission['end date'] ?? null) : null,
            'flight_program' => $metadata['flight program'] ?? null,
            'organism' => $metadata['organism'] ?? null,
            'publication' => $metadata['study publication title'] ?: null,
            'summary' => mb_strimwidth($description, 0, 280, '…'),
            'link' => 'https://osdr.nasa.gov/bio/repo/data/studies/'.$accession,
        ];
    }
}
