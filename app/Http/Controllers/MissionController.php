<?php

namespace App\Http\Controllers;

use App\Models\HealthEvent;
use App\Services\CrewHealthEngine;
use App\Services\NasaDataRepository;
use App\Services\SpaceMedicineEngine;
use App\Services\SpaceWeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(
        protected NasaDataRepository $nasaData,
        protected CrewHealthEngine $healthEngine,
    ) {}

    /**
     * Mission clock, trajectory progress and comms delay.
     */
    public function status(): JsonResponse
    {
        return response()->json(['success' => true, 'mission' => $this->healthEngine->missionStatus()]);
    }

    /**
     * Whole-crew snapshot: one telemetry frame + triage per member.
     */
    public function crew(): JsonResponse
    {
        $mission = $this->healthEngine->missionStatus();

        $crew = collect($this->nasaData->crew())->map(function (array $member) use ($mission): array {
            $telemetry = $this->healthEngine->telemetry($member);
            $triage = $this->healthEngine->triage($telemetry, $mission['comm_delay_seconds']);

            return [
                'id' => $member['id'],
                'name' => $member['name'],
                'initials' => $member['initials'],
                'role' => $member['role'],
                'habitat' => $member['habitat'],
                'telemetry' => $telemetry,
                'status' => $triage['status'],
                'health_index' => $triage['health_index'],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'mission' => $mission,
            'crew' => $crew,
            'crew_health_index' => (int) round($crew->avg('health_index')),
        ]);
    }

    /**
     * Toggle the solar particle event (SPE) emergency for the whole vehicle.
     */
    public function toggleEmergency(Request $request): JsonResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);
        $isActive = (bool) $validated['active'];

        if ($isActive !== $this->healthEngine->isEmergencyActive()) {
            $this->healthEngine->setEmergency($isActive);

            HealthEvent::create([
                'category' => 'alert',
                'severity' => $isActive ? 'critical' : 'success',
                'title' => $isActive ? 'Güneş parçacık olayı (SPE) alarmı' : 'SPE alarmı sonlandırıldı',
                'message' => $isActive
                    ? 'Doz hızı kritik eşiği aştı. Mürettebat fırtına sığınağına yönlendirildi.'
                    : 'Radyasyon akısı nominal seviyeye döndü. Normal operasyona geçildi.',
            ]);
        }

        return response()->json(['success' => true, 'mission' => $this->healthEngine->missionStatus()]);
    }

    /**
     * Live solar flare / CME feed from NASA DONKI.
     */
    public function spaceWeather(SpaceWeatherService $spaceWeather): JsonResponse
    {
        return response()->json(['success' => true, 'space_weather' => $spaceWeather->summary()]);
    }

    /**
     * Spaceflight medical risk projection (bone loss, SANS, radiation, ...) for a crew member at a mission day.
     */
    public function conditions(Request $request, SpaceMedicineEngine $spaceMedicine): JsonResponse
    {
        $validated = $request->validate([
            'member' => ['nullable', 'string', 'max:32'],
            'day' => ['nullable', 'integer', 'min:0', 'max:'.SpaceMedicineEngine::EARTH_RETURN_DAY],
            'compliance' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $member = $this->nasaData->crewMember($validated['member'] ?? 'astronot1') ?? $this->nasaData->crewMember('astronot1');
        $day = (int) ($validated['day'] ?? $this->healthEngine->missionStatus()['mission_day']);

        return response()->json([
            'success' => true,
            'assessment' => $spaceMedicine->assess($member, $day, (float) ($validated['compliance'] ?? 0.6)),
        ]);
    }

    /**
     * Statistics derived from the astronaut mission registry CSV.
     */
    public function astronautStats(): JsonResponse
    {
        return response()->json(['success' => true, 'stats' => $this->nasaData->astronautRegistryStats()]);
    }
}
