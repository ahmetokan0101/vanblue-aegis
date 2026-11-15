<?php

namespace App\Http\Controllers;

use App\Models\HealthEvent;
use App\Services\AiFlightSurgeon;
use App\Services\CrewBriefingService;
use App\Services\NasaDataRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    /**
     * Voice/text (+ optional photo) consultation with the AI flight surgeon.
     */
    public function consult(Request $request, AiFlightSurgeon $surgeon, NasaDataRepository $nasaData): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => ['nullable', 'string', 'max:32'],
            'message' => ['required', 'string', 'min:2', 'max:1000'],
            'image' => ['nullable', 'string', 'max:3000000', 'regex:/^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+\/=]+$/'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $member = $nasaData->crewMember($validated['member_id'] ?? 'astronot1') ?? $nasaData->crewMember('astronot1');
        $answer = $surgeon->consult($member, $validated['message'], $validated['history'] ?? [], $validated['image'] ?? null);

        HealthEvent::create([
            'member_id' => $member['id'],
            'category' => 'consult',
            'severity' => match ($answer['urgency']) {
                'emergency' => 'critical',
                'urgent' => 'warning',
                default => 'info',
            },
            'title' => isset($validated['image']) ? 'AI uçuş doktoru danışması (📷 fotoğraflı)' : 'AI uçuş doktoru danışması',
            'message' => '"'.str($validated['message'])->limit(120).'" → '.str($answer['summary'] ?: $answer['spoken_reply'])->limit(160),
            'payload' => [
                'urgency' => $answer['urgency'],
                'engine' => $answer['engine'],
                'related_condition_id' => $answer['related_condition_id'],
                'protocol' => $answer['protocol']['id'] ?? null,
                'had_image' => isset($validated['image']),
            ],
        ]);

        return response()->json(['success' => true, 'member' => ['id' => $member['id'], 'name' => $member['name']], 'answer' => $answer]);
    }

    /**
     * AI-generated whole-crew medical briefing.
     */
    public function briefing(Request $request, CrewBriefingService $briefing): JsonResponse
    {
        return response()->json(['success' => true, 'briefing' => $briefing->generate($request->boolean('refresh'))]);
    }
}
