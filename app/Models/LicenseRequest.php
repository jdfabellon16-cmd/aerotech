<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseRequest extends Model
{
    protected $fillable = [
        'request_number',
        'requested_by_id',
        'department_id',
        'software_name',
        'business_justification',
        'status',
        'manager_id',
        'purchasing_user_id',
        'decision_reason',
        'software_license_id',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(SoftwareLicense::class, 'software_license_id');
    }
}
