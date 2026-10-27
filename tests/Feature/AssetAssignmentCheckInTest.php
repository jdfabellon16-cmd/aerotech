<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetRequest;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use App\Notifications\AssetOperationsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssetAssignmentCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignee_can_check_in_an_active_asset(): void
    {
        Notification::fake();
        [$assignment, $asset, $assignee, $assigner] = $this->createActiveAssignment();

        $this->actingAs($assignee)
            ->postJson("/api/v1/assignments/{$assignment->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', 'returned');

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => 'available',
            'assignee_id' => null,
            'assigned_department_id' => null,
        ]);
        $this->assertDatabaseHas('asset_assignments', [
            'id' => $assignment->id,
            'status' => 'returned',
        ]);
        $this->assertDatabaseMissing('asset_assignments', [
            'id' => $assignment->id,
            'returned_at' => null,
        ]);
        Notification::assertSentTo($assigner, AssetOperationsNotification::class);
    }

    public function test_only_the_assignee_or_an_asset_technician_can_check_in_an_asset(): void
    {
        Notification::fake();
        [$assignment, $asset] = $this->createActiveAssignment();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->postJson("/api/v1/assignments/{$assignment->id}/check-in")
            ->assertForbidden();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => 'in_use',
            'assignee_id' => $assignment->assigned_to_id,
        ]);
        $this->assertDatabaseHas('asset_assignments', [
            'id' => $assignment->id,
            'status' => 'active',
            'returned_at' => null,
        ]);
        Notification::assertNothingSent();
    }

    /**
     * @return array{AssetAssignment, Asset, User, User}
     */
    private function createActiveAssignment(): array
    {
        $department = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $category = AssetCategory::create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'retirement_age_years' => 4,
        ]);
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'type' => 'office']);
        $assignee = User::factory()->create(['department_id' => $department->id]);
        $assigner = User::factory()->create();
        $asset = Asset::create([
            'asset_tag' => 'AT-CHECKIN-100',
            'serial_number' => 'SN-CHECKIN-100',
            'asset_category_id' => $category->id,
            'model' => 'ThinkPad T14',
            'assigned_department_id' => $department->id,
            'location_id' => $location->id,
            'assignee_id' => $assignee->id,
            'status' => 'in_use',
        ]);
        $request = AssetRequest::create([
            'request_number' => 'AR-CHECKIN-100',
            'requested_by_id' => $assignee->id,
            'asset_category_id' => $category->id,
            'justification' => 'Required for engineering work.',
            'urgency' => 'normal',
            'status' => 'fulfilled',
            'assigned_asset_id' => $asset->id,
        ]);
        $assignment = AssetAssignment::create([
            'asset_request_id' => $request->id,
            'asset_id' => $asset->id,
            'assigned_to_id' => $assignee->id,
            'assigned_by_id' => $assigner->id,
            'department_id' => $department->id,
            'handoff_method' => 'physical',
            'status' => 'active',
            'assigned_at' => now(),
            'signature_name' => $assignee->name,
            'acknowledged_by_id' => $assignee->id,
            'acknowledged_at' => now(),
        ]);

        return [$assignment, $asset, $assignee, $assigner];
    }
}
