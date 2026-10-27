<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestApproval;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_request_requires_all_three_approval_stages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$requester, $purchaseRequest] = $this->createPurchaseRequest();
        $departmentManager = $this->userWithRole('Department Manager', $requester->department_id);

        $this->actingAs($departmentManager)
            ->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/decision", ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_finance_approval');

        $financeManager = $this->userWithRole('Finance Manager');
        $this->actingAs($financeManager)
            ->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/decision", ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_general_approval');

        $generalManager = $this->userWithRole('General Manager');
        $this->actingAs($generalManager)
            ->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/decision", ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('purchase_request_approvals', [
            'purchase_request_id' => $purchaseRequest->id,
            'stage' => 'general',
            'decision' => 'approved',
            'approver_id' => $generalManager->id,
        ]);
    }

    public function test_rejection_requires_a_reason_and_requester_can_resubmit(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$requester, $purchaseRequest] = $this->createPurchaseRequest();
        $departmentManager = $this->userWithRole('Department Manager', $requester->department_id);

        $this->actingAs($departmentManager)
            ->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/decision", [
                'decision' => 'rejected',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'pending_department_approval',
        ]);

        $this->actingAs($departmentManager)
            ->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/decision", [
                'decision' => 'rejected',
                'reason' => 'Please provide a cost comparison.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->actingAs($requester)
            ->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/resubmissions", [
                'asset_category_id' => $purchaseRequest->asset_category_id,
                'justification' => 'Updated justification with vendor comparison.',
                'urgency' => 'high',
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_department_approval')
            ->assertJsonPath('data.submission_round', 2);

        $this->assertDatabaseHas('purchase_request_approvals', [
            'purchase_request_id' => $purchaseRequest->id,
            'submission_round' => 1,
            'stage' => 'department',
            'decision' => 'rejected',
            'reason' => 'Please provide a cost comparison.',
        ]);
        $this->assertDatabaseHas('purchase_request_approvals', [
            'purchase_request_id' => $purchaseRequest->id,
            'submission_round' => 2,
            'stage' => 'department',
            'decision' => 'pending',
        ]);
    }

    public function test_a_role_cannot_approve_another_stage(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$requester, $purchaseRequest] = $this->createPurchaseRequest();
        $financeManager = $this->userWithRole('Finance Manager');

        $this->actingAs($financeManager)
            ->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/decision", ['decision' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'pending_department_approval',
        ]);
    }

    /**
     * @return array{0: User, 1: PurchaseRequest}
     */
    private function createPurchaseRequest(): array
    {
        $department = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $category = AssetCategory::create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'retirement_age_years' => 4,
        ]);
        $requester = User::factory()->create(['department_id' => $department->id]);
        $requester->assignRole('Employee/Requestor');

        $response = $this->actingAs($requester)
            ->postJson('/api/v1/purchase-requests', [
                'asset_category_id' => $category->id,
                'justification' => 'New hires need secure laptops.',
                'urgency' => 'normal',
                'quantity' => 2,
            ]);

        $response->assertCreated();

        $purchaseRequest = PurchaseRequest::query()->findOrFail($response->json('data.id'));
        $this->assertSame('pending', $purchaseRequest->approvals->first()->decision);
        $this->assertInstanceOf(PurchaseRequestApproval::class, $purchaseRequest->approvals->first());

        return [$requester, $purchaseRequest];
    }

    private function userWithRole(string $role, ?int $departmentId = null): User
    {
        $user = User::factory()->create(['department_id' => $departmentId]);
        $user->assignRole($role);

        return $user;
    }
}
