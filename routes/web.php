<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\DetectiveController;
use App\Http\Controllers\HealthEventController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TelemetryController::class, 'index'])->name('dashboard');

Route::prefix('api')->group(function () {
    Route::get('/telemetry/live', [TelemetryController::class, 'getLiveTelemetry'])->name('telemetry.live');
    Route::get('/nasa/datasets', [TelemetryController::class, 'getNasaDatasets'])->name('nasa.datasets');
    Route::get('/nasa/astronauts/stats', [MissionController::class, 'astronautStats'])->name('nasa.astronauts.stats');

    Route::get('/mission', [MissionController::class, 'status'])->name('mission.status');
    Route::get('/crew', [MissionController::class, 'crew'])->name('crew.index');
    Route::post('/emergency', [MissionController::class, 'toggleEmergency'])->name('mission.emergency');
    Route::post('/assistant/consult', [AssistantController::class, 'consult'])->middleware('throttle:20,1')->name('assistant.consult');
    Route::get('/assistant/briefing', [AssistantController::class, 'briefing'])->middleware('throttle:10,1')->name('assistant.briefing');
    Route::get('/medications', [MedicationController::class, 'index'])->name('medications.index');
    Route::post('/medications/{medication}/dispense', [MedicationController::class, 'dispense'])->name('medications.dispense');
    Route::get('/detective', [DetectiveController::class, 'show'])->name('detective.show');
    Route::post('/detective/scenario', [DetectiveController::class, 'scenario'])->name('detective.scenario');
    Route::post('/detective/sensor-fault', [DetectiveController::class, 'sensorFault'])->name('detective.sensor-fault');
    Route::post('/detective/check', [DetectiveController::class, 'check'])->name('detective.check');
    Route::get('/conditions', [MissionController::class, 'conditions'])->name('conditions.index');
    Route::get('/space-weather', [MissionController::class, 'spaceWeather'])->name('space-weather');

    Route::get('/events', [HealthEventController::class, 'index'])->name('events.index');
    Route::post('/events', [HealthEventController::class, 'store'])->name('events.store');
});
