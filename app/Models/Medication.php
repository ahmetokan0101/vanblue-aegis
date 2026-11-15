<?php

namespace App\Models;

use Database\Factories\MedicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * On-board pharmacy item (no resupply on a Mars transit).
 */
#[Fillable(['name', 'category', 'form', 'strength', 'unit', 'quantity', 'initial_quantity', 'min_threshold', 'expires_on', 'storage_note'])]
class Medication extends Model
{
    /** @use HasFactory<MedicationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'initial_quantity' => 'integer',
            'min_threshold' => 'integer',
            'expires_on' => 'date',
        ];
    }

    public function isLow(): bool
    {
        return $this->quantity <= $this->min_threshold;
    }

    public function stockStatus(): string
    {
        return match (true) {
            $this->quantity === 0 => 'out',
            $this->isLow() => 'low',
            default => 'ok',
        };
    }

    /**
     * @param  Builder<Medication>  $query
     */
    public function scopeInStock(Builder $query): void
    {
        $query->where('quantity', '>', 0);
    }
}
