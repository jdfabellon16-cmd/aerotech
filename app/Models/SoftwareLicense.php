<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoftwareLicense extends Model
{
    protected $fillable = ['name', 'vendor', 'license_key', 'total_seats', 'cost', 'expires_at', 'supplier_id', 'recorded_by_id'];

    protected function casts(): array
    {
        return [
            'license_key' => 'encrypted',
            'expires_at' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('revoked_at');
    }
}
