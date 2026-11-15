<?php

namespace App\Http\Controllers;

use App\Models\HealthEvent;
use App\Models\Medication;
use App\Services\NasaDataRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicationController extends Controller
{
    /**
     * On-board pharmacy inventory with usage history.
     */
    public function index(): JsonResponse
    {
        $medications = Medication::query()->orderBy('category')->orderBy('name')->get();
        $dispenses = HealthEvent::query()->where('category', 'medication')->latest('id')->limit(15)->get();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_items' => $medications->count(),
                'low_count' => $medications->filter->isLow()->count(),
                'out_count' => $medications->where('quantity', 0)->count(),
                'stock_pct' => (int) round($medications->sum('quantity') / max(1, $medications->sum('initial_quantity')) * 100),
            ],
            'medications' => $medications->map(fn (Medication $medication): array => $medication->toArray() + [
                'status' => $medication->stockStatus(),
                'stock_pct' => (int) round($medication->quantity / max(1, $medication->initial_quantity) * 100),
            ]),
            'dispenses' => $dispenses,
        ]);
    }

    /**
     * Dispense a medication to a crew member (decrements stock, logs to the medical record).
     */
    public function dispense(Request $request, Medication $medication, NasaDataRepository $nasaData): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'string', 'max:32'],
            'amount' => ['nullable', 'integer', 'min:1', 'max:10'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $amount = $validated['amount'] ?? 1;

        if ($medication->quantity < $amount) {
            return response()->json(['success' => false, 'message' => "{$medication->name} stokta yetersiz ({$medication->quantity} {$medication->unit})."], 422);
        }

        DB::transaction(function () use ($medication, $amount, $validated, $nasaData): void {
            $medication->decrement('quantity', $amount);
            $memberName = $nasaData->crewMember($validated['member_id'])['name'] ?? $validated['member_id'];

            HealthEvent::create([
                'member_id' => $validated['member_id'],
                'category' => 'medication',
                'severity' => $medication->fresh()->isLow() ? 'warning' : 'success',
                'title' => "{$medication->name} verildi",
                'message' => "{$memberName} · {$amount} {$medication->unit}".(filled($validated['reason'] ?? null) ? " · {$validated['reason']}" : '')." · kalan {$medication->fresh()->quantity}",
                'payload' => ['medication_id' => $medication->id, 'amount' => $amount],
            ]);
        });

        $medication->refresh();

        return response()->json([
            'success' => true,
            'medication' => $medication->toArray() + ['status' => $medication->stockStatus()],
            'warning' => $medication->isLow() ? "{$medication->name} kritik stok seviyesinde ({$medication->quantity} {$medication->unit} kaldı). Mars görevinde ikmal yok!" : null,
        ]);
    }
}
