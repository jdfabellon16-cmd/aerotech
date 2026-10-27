<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\AssetAudit;

class AssetObserver
{
    public function created(Asset $asset): void
    {
        AssetAudit::create([
            'asset_id' => $asset->id,
            'changed_by_id' => auth()->id(),
            'action' => 'created',
            'field_changes' => collect($asset->getAttributes())
                ->except(['id', 'created_at', 'updated_at'])
                ->map(fn (mixed $value): array => ['old' => null, 'new' => $value])
                ->all(),
        ]);
    }

    public function updated(Asset $asset): void
    {
        $fieldChanges = collect($asset->getChanges())
            ->except(['updated_at'])
            ->map(fn (mixed $value, string $field): array => [
                'old' => $asset->getRawOriginal($field),
                'new' => $value,
            ])
            ->all();

        if ($fieldChanges === []) {
            return;
        }

        AssetAudit::create([
            'asset_id' => $asset->id,
            'changed_by_id' => auth()->id(),
            'action' => 'updated',
            'field_changes' => $fieldChanges,
        ]);
    }
}
