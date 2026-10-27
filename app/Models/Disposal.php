<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disposal extends Model
{
    protected $fillable = [
        'asset_id',
        'requested_by_id',
        'disposal_type',
        'status',
        'finance_approved_by_id',
        'management_approved_by_id',
        'data_wipe_done',
        'company_files_removed',
        'certificate_path',
        'recipient_or_vendor',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'data_wipe_done' => 'boolean',
            'company_files_removed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
