<?php

namespace Tests\Feature;

use App\Models\SoftwareLicense;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseSeatLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_assignment_is_blocked_when_all_seats_are_in_use(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $purchasingUser = User::factory()->create();
        $purchasingUser->assignRole('IT Asset Manager');
        $firstEmployee = User::factory()->create();
        $secondEmployee = User::factory()->create();
        $license = SoftwareLicense::create([
            'name' => 'Design Suite',
            'vendor' => 'AeroTech Vendor',
            'total_seats' => 1,
            'cost' => 300,
            'recorded_by_id' => $purchasingUser->id,
        ]);

        $this->actingAs($purchasingUser)
            ->postJson("/api/v1/licenses/{$license->id}/assignments", ['user_id' => $firstEmployee->id])
            ->assertCreated();

        $this->actingAs($purchasingUser)
            ->postJson("/api/v1/licenses/{$license->id}/assignments", ['user_id' => $secondEmployee->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license');

        $this->assertDatabaseCount('license_assignments', 1);
    }
}
