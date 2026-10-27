<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Repair extends Model
{
    protected $fillable = [
        'maintenance_ticket_id',
        'asset_id',
        'performed_by_id',
        'started_at',
        'completed_at',
        'cost',
        'paid',
        'cost_approved',
        'cost_approved_by_id',
        'hr_cost_deduction_flag',
        'warranty_used',
        'repair_summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cost' => 'decimal:2',
            'paid' => 'boolean',
            'cost_approved' => 'boolean',
            'hr_cost_deduction_flag' => 'boolean',
            'warranty_used' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTicket::class, 'maintenance_ticket_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
