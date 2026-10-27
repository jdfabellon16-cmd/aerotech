<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseRenewalReview extends Model
{
    protected $fillable = [
        'software_license_id',
        'expires_at',
        'days_before_expiry',
        'status',
        'it_reviewed_by_id',
        'it_reviewed_at',
        'it_notes',
        'management_reviewed_by_id',
        'management_reviewed_at',
        'management_notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'it_reviewed_at' => 'datetime',
            'management_reviewed_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(SoftwareLicense::class, 'software_license_id');
    }
}
