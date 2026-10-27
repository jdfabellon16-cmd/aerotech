<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifecycleReview extends Model
{
    protected $fillable = ['asset_id', 'review_date', 'status', 'incident_count', 'review_notes', 'reviewed_by_id', 'reviewed_at'];

    protected function casts(): array
    {
        return ['review_date' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
