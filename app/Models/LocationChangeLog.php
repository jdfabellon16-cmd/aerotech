<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationChangeLog extends Model
{
    protected $fillable = [
        'asset_id',
        'transfer_id',
        'from_location_id',
        'to_location_id',
        'changed_by_id',
        'accepted_by_id',
        'accepted_at',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'changed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
