<?php

namespace App\Services;

use App\Models\Asset;

class WarrantyService
{
    public function isCovered(Asset $asset): bool
    {
        return $asset->warranty_expires_at !== null
            && $asset->warranty_expires_at->greaterThanOrEqualTo(today());
    }
}
