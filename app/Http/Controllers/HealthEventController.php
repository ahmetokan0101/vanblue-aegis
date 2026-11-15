<?php

namespace App\Http\Controllers;

use App\Models\HealthEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HealthEventController extends Controller
{
    /**
     * Latest mission medical log entries.
     */
    public function index(Request $request): JsonResponse
    {
        $memberId = $request->query('member');

        $events = HealthEvent::query()
            ->when($memberId, fn ($query) => $query->where(fn ($inner) => $inner->where('member_id', $memberId)->orWhereNull('member_id')))
            ->latest('id')
            ->limit(max(1, min(100, (int) $request->query('limit', 30))))
            ->get();

        return response()->json(['success' => true, 'events' => $events]);
    }

    /**
     * Record a cognitive test result, countermeasure completion or manual log entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => ['nullable', 'string', 'max:32'],
            'category' => ['required', Rule::in(HealthEvent::CATEGORIES)],
            'severity' => ['nullable', Rule::in(HealthEvent::SEVERITIES)],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'payload' => ['nullable', 'array'],
        ]);

        $event = HealthEvent::create(array_merge(['severity' => 'info'], array_filter($validated, fn ($value) => $value !== null)));

        return response()->json(['success' => true, 'event' => $event], 201);
    }
}
