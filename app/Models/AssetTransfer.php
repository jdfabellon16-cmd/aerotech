<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransfer extends Model
{
    protected $fillable = [
        'asset_id',
        'requested_by_id',
        'from_user_id',
        'to_user_id',
        'from_location_id',
        'to_location_id',
        'transfer_type',
        'reason',
        'status',
        'approved_by_id',
        'loan_started_at',
        'due_at',
        'replacement_expected_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'loan_started_at' => 'datetime',
            'due_at' => 'date',
            'replacement_expected_at' => 'date',
            'accepted_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
