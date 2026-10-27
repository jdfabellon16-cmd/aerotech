<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_incident_captures_warranty_coverage_from_asset_expiry(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $reporter = User::factory()->create();
        $reporter->assignRole('Employee/Requestor');
        $category = AssetCategory::create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'retirement_age_years' => 4,
        ]);
        $asset = Asset::create([
            'asset_tag' => 'AT-WARRANTY-01',
            'serial_number' => 'SN-WARRANTY-01',
            'asset_category_id' => $category->id,
            'model' => 'ThinkPad T14',
            'warranty_expires_at' => now()->addDays(20)->toDateString(),
            'assignee_id' => $reporter->id,
            'status' => 'available',
        ]);

        $this->actingAs($reporter)
            ->postJson('/api/v1/maintenance-tickets', [
                'asset_id' => $asset->id,
                'issue_description' => 'Device will not boot after a system update.',
                'priority' => 'normal',
            ])
            ->assertCreated()
            ->assertJsonPath('data.warranty_covered', true);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => 'in_repair',
        ]);
    }

    public function test_expired_warranty_is_not_reported_as_covered(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $reporter = User::factory()->create();
        $reporter->assignRole('Employee/Requestor');
        $category = AssetCategory::create([
            'name' => 'Tablets',
            'slug' => 'tablets',
            'retirement_age_years' => 4,
        ]);
        $asset = Asset::create([
            'asset_tag' => 'AT-WARRANTY-02',
            'serial_number' => 'SN-WARRANTY-02',
            'asset_category_id' => $category->id,
            'model' => 'iPad Air',
            'warranty_expires_at' => now()->subDay()->toDateString(),
            'assignee_id' => $reporter->id,
            'status' => 'available',
        ]);

        $this->actingAs($reporter)
            ->postJson('/api/v1/maintenance-tickets', [
                'asset_id' => $asset->id,
                'issue_description' => 'Screen does not respond to touch input.',
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.warranty_covered', false);
    }
}
