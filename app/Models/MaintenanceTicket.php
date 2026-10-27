<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'asset_id',
        'reported_by_id',
        'assigned_to_id',
        'issue_description',
        'assessment_checklist',
        'priority',
        'sla_due_at',
        'status',
        'warranty_covered',
        'warranty_checked_at',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'assessment_checklist' => 'array',
            'sla_due_at' => 'datetime',
            'warranty_covered' => 'boolean',
            'warranty_checked_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }
}
