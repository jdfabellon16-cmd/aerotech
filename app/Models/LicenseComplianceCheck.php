<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseComplianceCheck extends Model
{
    protected $fillable = [
        'software_license_id',
        'performed_by_id',
        'status',
        'seats_purchased',
        'seats_assigned',
        'findings',
        'checked_at',
    ];

    protected function casts(): array
    {
        return ['findings' => 'array', 'checked_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(SoftwareLicense::class, 'software_license_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }
}
