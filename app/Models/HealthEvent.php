<?php

namespace App\Models;

use Database\Factories\HealthEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mission medical log entry (alerts, cognitive tests, countermeasures, triage decisions).
 */
#[Fillable(['member_id', 'category', 'severity', 'title', 'message', 'payload'])]
class HealthEvent extends Model
{
    /** @use HasFactory<HealthEventFactory> */
    use HasFactory;

    public const array CATEGORIES = ['alert', 'cognitive', 'countermeasure', 'triage', 'system', 'consult', 'medication', 'briefing'];

    public const array SEVERITIES = ['info', 'success', 'warning', 'critical'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
