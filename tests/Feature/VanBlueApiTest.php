<?php

namespace Tests\Feature;

use App\Models\HealthEvent;
use App\Models\Medication;
use App\Services\AiFlightSurgeon;
use App\Services\CrewHealthEngine;
use App\Services\NasaDataRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VanBlueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_sidebar_panels(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-panel="overview"', false)
            ->assertSee('data-panel="radiation"', false);
    }

    public function test_live_telemetry_returns_nominal_triage_for_member(): void
    {
        $this->getJson('/api/telemetry/live?member=astronot1')
            ->assertOk()
            ->assertJsonPath('member.id', 'astronot1')
            ->assertJsonPath('autonomous_ai.status', 'NOMINAL')
            ->assertJsonStructure(['telemetry' => ['heart_rate', 'spo2', 'radiation_msv', 'cumulative_dose_msv'], 'autonomous_ai' => ['findings', 'actions', 'decision_latency_ms'], 'mission' => ['comm_delay_seconds']]);
    }

    public function test_emergency_toggle_drives_critical_triage_and_logs_event(): void
    {
        $this->postJson('/api/emergency', ['active' => true])
            ->assertOk()
            ->assertJsonPath('mission.emergency', true);

        $this->getJson('/api/telemetry/live?member=astronot2')
            ->assertOk()
            ->assertJsonPath('autonomous_ai.status', 'CRITICAL');

        $this->assertDatabaseHas('health_events', ['category' => 'alert', 'severity' => 'critical']);
        $this->assertDatabaseHas('health_events', ['category' => 'triage', 'member_id' => 'astronot2']);

        $this->postJson('/api/emergency', ['active' => false])->assertJsonPath('mission.emergency', false);
    }

    public function test_triage_flags_tachycardia_against_hrp_threshold(): void
    {
        $engine = app(CrewHealthEngine::class);
        $telemetry = $engine->telemetry(app(NasaDataRepository::class)->crewMember('astronot4'), false);
        $telemetry['heart_rate'] = 120;

        $triage = $engine->triage($telemetry, 600);

        $this->assertSame('critical', collect($triage['findings'])->firstWhere('metric', 'heart_rate')['level']);
        $this->assertNotSame('NOMINAL', $triage['status']);
        $this->assertSame(1200, $triage['time_saved_seconds']);
    }

    public function test_space_conditions_project_bone_loss_by_compliance(): void
    {
        $lowCompliance = $this->getJson('/api/conditions?member=astronot4&day=400&compliance=0')
            ->assertOk()
            ->assertJsonCount(12, 'assessment.conditions')
            ->assertJsonPath('assessment.phase.key', 'surface');

        $fullCompliance = $this->getJson('/api/conditions?member=astronot4&day=400&compliance=1')->assertOk();

        $boneRisk = fn ($response): int => collect($response->json('assessment.conditions'))->firstWhere('id', 'bone_loss')['projection']['risk'];

        $this->assertGreaterThan($boneRisk($fullCompliance), $boneRisk($lowCompliance));
        $this->getJson('/api/conditions?day=5000')->assertUnprocessable();
    }

    public function test_nasa_datasets_include_live_osdr_metadata(): void
    {
        Http::fake([
            'visualization.osdr.nasa.gov/*' => fn ($request) => Http::response([
                basename(dirname($request->url())) => ['metadata' => [
                    'study title' => 'Live OSDR title',
                    'mission' => ['name' => 'STS-135'],
                    'organism' => 'Mus musculus',
                    'study description' => 'desc',
                    'study publication title' => '',
                ]],
            ]),
        ]);
        Cache::flush();

        $this->getJson('/api/nasa/datasets')
            ->assertOk()
            ->assertJsonPath('osdr_studies.is_live', true)
            ->assertJsonPath('osdr_studies.studies.0.title', 'Live OSDR title');
    }

    public function test_nasa_datasets_fall_back_to_verified_osdr_snapshot(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        Cache::flush();

        $this->getJson('/api/nasa/datasets')
            ->assertOk()
            ->assertJsonPath('osdr_studies.is_live', false)
            ->assertJsonPath('osdr_studies.studies.0.accession', 'OSD-87');
    }

    public function test_ai_flight_surgeon_uses_gemini_with_live_context(): void
    {
        config(['services.gemini.api_key' => 'test-key', 'services.gemini.base_url' => 'http://ai.test/v1']);
        Http::fake([
            'ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => "```json\n".json_encode([
                'spoken_reply' => 'Astronot 1, eklem ağrın büyük olasılıkla kas-iskelet adaptasyonundan.',
                'summary' => 'Mikroyerçekimi kaynaklı eklem ağrısı.',
                'urgency' => 'monitor',
                'possible_causes' => [['name' => 'Kas atrofisi', 'likelihood' => 60, 'space_related' => true, 'reason' => 'x']],
                'immediate_steps' => ['ARED egzersizini hafiflet'],
                'related_condition_id' => 'muscle_atrophy',
            ])."\n```"]]]]),
        ]);

        $this->postJson('/api/assistant/consult', ['member_id' => 'astronot1', 'message' => 'Eklemlerimde ağrı var'])
            ->assertOk()
            ->assertJsonPath('answer.engine', 'gemini+protocol')
            ->assertJsonPath('answer.protocol.id', 'joint')
            ->assertJsonPath('answer.treatment_plan.now.0', fn ($step) => str_contains($step, 'İbuprofen'))
            ->assertJsonPath('answer.urgency', 'monitor')
            ->assertJsonPath('answer.related_condition.id', 'muscle_atrophy');

        Http::assertSent(fn ($request) => $request['model'] === config('services.gemini.model')
            && str_contains($request['messages'][0]['content'], 'heart_rate_bpm'));
        $this->assertDatabaseHas('health_events', ['category' => 'consult', 'member_id' => 'astronot1']);
    }

    public function test_ai_flight_surgeon_falls_back_offline_and_escalates_red_flags(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['*' => Http::response(null, 500)]);

        $this->postJson('/api/assistant/consult', ['member_id' => 'astronot4', 'message' => 'Kulaklarımda ağrı var'])
            ->assertOk()
            ->assertJsonPath('answer.engine', 'offline')
            ->assertJsonPath('answer.related_condition_id', 'sas');

        $this->postJson('/api/assistant/consult', ['member_id' => 'astronot4', 'message' => 'Göğsümde baskı var nefes alamıyorum'])
            ->assertOk()
            ->assertJsonPath('answer.urgency', 'emergency');

        $this->postJson('/api/assistant/consult', ['message' => ''])->assertUnprocessable();
    }

    public function test_ai_flight_surgeon_handles_intimate_complaints_with_protocol(): void
    {
        config(['services.gemini.api_key' => 'test-key', 'services.gemini.base_url' => 'http://ai.test/v1']);
        Http::fake([
            'ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'spoken_reply' => 'Bu konuda yardımcı olamam, bir doktora başvurun.',
                'urgency' => 'routine',
            ])]]]]),
        ]);

        $this->postJson('/api/assistant/consult', ['member_id' => 'astronot1', 'message' => 'Penisim ağrıyor'])
            ->assertOk()
            ->assertJsonPath('answer.engine', 'gemini+protocol')
            ->assertJsonPath('answer.protocol.id', 'urogenital')
            ->assertJsonPath('answer.urgency', 'urgent')
            ->assertJsonPath('answer.related_condition_id', 'renal_stones');

        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'Ürogenital'));
    }

    public function test_red_flag_detects_turkish_inflected_chest_complaint(): void
    {
        $this->assertNotEmpty(app(AiFlightSurgeon::class)->detectRedFlags('GÖĞSÜMDE baskı var'));
    }

    public function test_pharmacy_dispense_decrements_stock_and_warns_when_low(): void
    {
        $medication = Medication::factory()->create(['name' => 'Prometazin', 'quantity' => 3, 'initial_quantity' => 60, 'min_threshold' => 2]);

        $this->postJson("/api/medications/{$medication->id}/dispense", ['member_id' => 'astronot4', 'reason' => 'Bulantı'])
            ->assertOk()
            ->assertJsonPath('medication.quantity', 2)
            ->assertJsonPath('medication.status', 'low');

        $this->assertDatabaseHas('health_events', ['category' => 'medication', 'member_id' => 'astronot4']);

        $medication->update(['quantity' => 0]);
        $this->postJson("/api/medications/{$medication->id}/dispense", ['member_id' => 'astronot4'])->assertUnprocessable();

        $this->getJson('/api/medications')->assertOk()->assertJsonPath('summary.out_count', 1);
    }

    public function test_consult_links_recommended_medication_to_stock_and_sends_history_and_image(): void
    {
        $medication = Medication::factory()->create(['name' => 'Psödoefedrin', 'quantity' => 40]);
        HealthEvent::factory()->create(['member_id' => 'astronot1', 'category' => 'consult', 'title' => 'Önceki kulak ağrısı']);
        config(['services.gemini.api_key' => 'test-key', 'services.gemini.base_url' => 'http://ai.test/v1']);
        Http::fake([
            'ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'spoken_reply' => 'Astronot 1, kulak ağrın tekrarlıyor; psödoefedrin al.',
                'urgency' => 'monitor',
                'possible_causes' => [['name' => 'Östaki', 'likelihood' => 60], ['name' => 'Barotravma', 'likelihood' => 30]],
                'immediate_steps' => ['Valsalva', 'Otoskop'],
                'medications' => [['name' => 'Psödoefedrin', 'dose' => '60 mg']],
                'treatment_plan' => ['now' => ['Psödoefedrin 60 mg al']],
                'history_insight' => 'Kulak ağrısı ikinci kez görülüyor.',
                'image_findings' => 'Kulak kanalında hafif kızarıklık.',
            ])]]]]),
        ]);

        $this->postJson('/api/assistant/consult', [
            'member_id' => 'astronot1',
            'message' => 'Kulağım yine ağrıyor',
            'image' => 'data:image/jpeg;base64,'.base64_encode('fake-image'),
        ])
            ->assertOk()
            ->assertJsonPath('answer.medications.0.inventory_id', $medication->id)
            ->assertJsonPath('answer.history_insight', 'Kulak ağrısı ikinci kez görülüyor.')
            ->assertJsonPath('answer.image_analyzed', true);

        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'Önceki kulak ağrısı')
            && collect($request['messages'])->last()['content'][1]['type'] === 'image_url');

        $this->postJson('/api/assistant/consult', ['message' => 'test', 'image' => 'not-an-image'])->assertUnprocessable();
    }

    public function test_crew_briefing_falls_back_offline_and_is_cached(): void
    {
        config(['services.gemini.api_key' => null]);
        Http::fake(['*' => Http::response(null, 503)]);
        Cache::flush();

        $this->getJson('/api/assistant/briefing')
            ->assertOk()
            ->assertJsonPath('briefing.engine', 'offline')
            ->assertJsonCount(4, 'briefing.crew')
            ->assertJsonStructure(['briefing' => ['headline', 'overall_status', 'opening', 'priorities', 'schedule', 'space_weather', 'pharmacy', 'closing', 'vitals']]);

        $this->getJson('/api/assistant/briefing')->assertOk();
        $this->assertSame(1, HealthEvent::where('category', 'briefing')->count());
    }

    public function test_detective_identifies_shared_cabin_cause(): void
    {
        $this->postJson('/api/detective/scenario', ['scenario' => 'cabin'])
            ->assertOk()
            ->assertJsonPath('analysis.hypotheses.0.id', 'environment')
            ->assertJsonPath('analysis.focus_module', 'exercise')
            ->assertJsonPath('analysis.confident', true);
    }

    public function test_detective_revises_assessment_when_sensors_disagree_and_recovers_after_handheld_check(): void
    {
        $this->postJson('/api/detective/scenario', ['scenario' => 'cabin'])->assertOk();

        $this->postJson('/api/detective/sensor-fault', ['active' => true])
            ->assertOk()
            ->assertJsonPath('analysis.confident', false)
            ->assertJsonPath('analysis.next_check.id', 'cdm_handheld');

        $response = $this->postJson('/api/detective/check', ['check' => 'cdm_handheld'])->assertOk();

        $this->assertSame('environment', $response->json('analysis.hypotheses.0.id'));
        $this->assertGreaterThanOrEqual(90, $response->json('analysis.hypotheses.0.probability'));
    }

    public function test_detective_distinguishes_individual_illness_and_sensor_fault(): void
    {
        $this->postJson('/api/detective/scenario', ['scenario' => 'individual'])
            ->assertOk()
            ->assertJsonPath('analysis.hypotheses.0.id', 'individual');

        $this->postJson('/api/detective/scenario', ['scenario' => 'sensor'])
            ->assertOk()
            ->assertJsonPath('analysis.hypotheses.0.id', 'sensor');

        $this->postJson('/api/detective/check', ['check' => 'unknown'])->assertUnprocessable();
    }

    public function test_crew_overview_lists_all_members(): void
    {
        $this->getJson('/api/crew')
            ->assertOk()
            ->assertJsonCount(4, 'crew')
            ->assertJsonStructure(['crew_health_index', 'mission' => ['mission_day', 'progress_pct']]);
    }

    public function test_astronaut_registry_stats_are_computed_from_csv(): void
    {
        $response = $this->getJson('/api/nasa/astronauts/stats')->assertOk();

        $this->assertGreaterThan(1000, $response->json('stats.total_missions'));
        $this->assertNotEmpty($response->json('stats.top_flight_hours'));
    }

    public function test_space_weather_uses_donki_feed(): void
    {
        Http::fake([
            'api.nasa.gov/DONKI/FLR*' => Http::response([
                ['flrID' => 'F1', 'classType' => 'M2.3', 'peakTime' => '2026-11-10T10:00Z', 'sourceLocation' => 'N10E20'],
                ['flrID' => 'F2', 'classType' => 'X1.1', 'peakTime' => '2026-11-12T08:00Z', 'sourceLocation' => 'S05W10'],
            ]),
            'api.nasa.gov/DONKI/CME*' => Http::response([['activityID' => 'C1']]),
        ]);
        Cache::flush();

        $this->getJson('/api/space-weather')
            ->assertOk()
            ->assertJsonPath('space_weather.is_live', true)
            ->assertJsonPath('space_weather.strongest.class', 'X1.1')
            ->assertJsonPath('space_weather.threat_level', 'HIGH')
            ->assertJsonPath('space_weather.cme_count', 1);
    }

    public function test_space_weather_uses_ccmc_origin_when_gateway_fails(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response(['error' => 'Not Found'], 404),
            'kauai.ccmc.gsfc.nasa.gov/DONKI/WS/get/FLR*' => Http::response([
                ['flrID' => 'F9', 'classType' => 'C4.0', 'peakTime' => '2026-11-11T10:00Z', 'sourceLocation' => 'N01E01'],
            ]),
            'kauai.ccmc.gsfc.nasa.gov/DONKI/WS/get/CME*' => Http::response([]),
        ]);
        Cache::flush();

        $this->getJson('/api/space-weather')
            ->assertOk()
            ->assertJsonPath('space_weather.is_live', true)
            ->assertJsonPath('space_weather.threat_level', 'LOW')
            ->assertJsonPath('space_weather.class_counts.C', 1);
    }

    public function test_space_weather_falls_back_to_archive_when_donki_is_down(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        Cache::flush();

        $this->getJson('/api/space-weather')
            ->assertOk()
            ->assertJsonPath('space_weather.is_live', false)
            ->assertJsonPath('space_weather.strongest.class', 'X5.4');
    }

    public function test_health_events_can_be_stored_and_listed(): void
    {
        HealthEvent::factory()->critical()->create(['member_id' => 'astronot3']);

        $this->postJson('/api/events', [
            'member_id' => 'astronot4',
            'category' => 'cognitive',
            'severity' => 'success',
            'title' => 'PVT testi',
            'payload' => ['avg_ms' => 312],
        ])->assertCreated()->assertJsonPath('event.payload.avg_ms', 312);

        $this->getJson('/api/events?member=astronot4')
            ->assertOk()
            ->assertJsonCount(1, 'events');

        $this->postJson('/api/events', ['category' => 'invalid', 'title' => 'x'])->assertUnprocessable();
    }
}
