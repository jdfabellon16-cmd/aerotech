<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssetRequest extends Model
{
    protected $fillable = [
        'request_number',
        'requested_by_id',
        'asset_category_id',
        'justification',
        'urgency',
        'status',
        'policy_checked',
        'policy_checked_by_id',
        'policy_notes',
        'assigned_asset_id',
    ];

    protected function casts(): array
    {
        return ['policy_checked' => 'boolean'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function assignedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'assigned_asset_id');
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class);
    }
}
