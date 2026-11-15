<?php

namespace App\Http\Controllers;

use App\Models\HealthEvent;
use App\Services\MissionDetective;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DetectiveController extends Controller
{
    public function __construct(protected MissionDetective $detective) {}

    /**
     * Current investigation picture.
     */
    public function show(): JsonResponse
    {
        return response()->json(['success' => true] + $this->detective->investigate());
    }

    /**
     * Start a mission scenario (resets performed checks).
     */
    public function scenario(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scenario' => ['required', Rule::in(MissionDetective::SCENARIOS)],
            'sensor_fault' => ['nullable', 'boolean'],
        ]);

        $this->detective->setScenario($validated['scenario'], (bool) ($validated['sensor_fault'] ?? false));

        if ($validated['scenario'] !== 'nominal') {
            HealthEvent::create([
                'category' => 'alert',
                'severity' => 'warning',
                'title' => 'Sağlık Dedektifi: beklenmedik sağlık değişimi',
                'message' => 'Senaryo başlatıldı — kök neden analizi çalışıyor.',
                'payload' => ['scenario' => $validated['scenario']],
            ]);
        }

        return $this->show();
    }

    /**
     * Inject or clear a sensor fault mid-investigation.
     */
    public function sensorFault(Request $request): JsonResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);
        $this->detective->setSensorFault((bool) $validated['active']);

        return $this->show();
    }

    /**
     * Perform a diagnostic check and fold its result into the analysis.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate(['check' => ['required', Rule::in(array_keys(MissionDetective::CHECKS))]]);
        $this->detective->runCheck($validated['check']);

        HealthEvent::create([
            'category' => 'triage',
            'severity' => 'info',
            'title' => 'Dedektif kontrolü: '.MissionDetective::CHECKS[$validated['check']]['title'],
            'message' => 'Kontrol sonucu analize eklendi.',
        ]);

        return $this->show();
    }
}
