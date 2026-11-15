<?php

namespace App\Http\Controllers;

use App\Models\HealthEvent;
use App\Services\CrewHealthEngine;
use App\Services\NasaDataRepository;
use App\Services\OsdrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TelemetryController extends Controller
{
    public function __construct(
        protected NasaDataRepository $nasaData,
        protected CrewHealthEngine $healthEngine,
    ) {}

    /**
     * VanBlue AegisHealth OS — mission control shell.
     */
    public function index(): View
    {
        return view('dashboard', [
            'crew' => $this->nasaData->crew(),
            'mission' => $this->healthEngine->missionStatus(),
        ]);
    }

    /**
     * Live bio-telemetry frame + autonomous triage for one crew member.
     */
    public function getLiveTelemetry(Request $request): JsonResponse
    {
        $memberId = (string) $request->query('member', 'astronot1');
        $member = $this->nasaData->crewMember($memberId) ?? $this->nasaData->crewMember('astronot1');
        $isEmergency = $request->has('emergency') ? $request->boolean('emergency') : null;

        $mission = $this->healthEngine->missionStatus();
        $telemetry = $this->healthEngine->telemetry($member, $isEmergency);
        $triage = $this->healthEngine->triage($telemetry, $mission['comm_delay_seconds']);

        if ($triage['status'] === 'CRITICAL' && Cache::add("vanblue.triage_logged.{$member['id']}", true, now()->addMinutes(2))) {
            HealthEvent::create([
                'member_id' => $member['id'],
                'category' => 'triage',
                'severity' => 'critical',
                'title' => 'Otonom triage: KRİTİK',
                'message' => $triage['recommended_action'],
                'payload' => ['risk_score' => $triage['risk_score'], 'heart_rate' => $telemetry['heart_rate'], 'radiation_msv' => $telemetry['radiation_msv']],
            ]);
        }

        return response()->json([
            'success' => true,
            'timestamp' => now()->format('H:i:s'),
            'member' => $member,
            'mission' => $mission,
            'telemetry' => $telemetry,
            'autonomous_ai' => $triage,
        ]);
    }

    /**
     * Locally mirrored official NASA datasets.
     */
    public function getNasaDatasets(OsdrService $osdr): JsonResponse
    {
        $stats = $this->nasaData->astronautRegistryStats();

        return response()->json([
            'success' => true,
            'source' => 'NASA OSDR (canlı API), MSL RAD (Zeitlin 2013 / Hassler 2014), NASA-STD-3001',
            'msl_radiation' => $this->nasaData->mslRadiation(),
            'hrp_clinical' => $this->nasaData->hrpThresholds(),
            'osdr_studies' => $osdr->studies(),
            'astronaut_csv_available' => true,
            'astronaut_csv_size_kb' => $stats['file_size_kb'],
        ]);
    }
}
