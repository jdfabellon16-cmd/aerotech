<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetReceivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_a_purchase_order_creates_audited_asset_and_rejects_duplicate_tags(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $warehouseUser = User::factory()->create();
        $warehouseUser->assignRole('Warehouse Supervisor');
        $requester = User::factory()->create();
        $department = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $category = AssetCategory::create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'retirement_age_years' => 4,
        ]);
        $supplier = Supplier::create(['name' => 'AeroTech Supply']);
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'type' => 'office']);
        $purchaseRequest = PurchaseRequest::create([
            'request_number' => 'PR-TEST-100',
            'requested_by_id' => $requester->id,
            'department_id' => $department->id,
            'asset_category_id' => $category->id,
            'justification' => 'Replace the ageing engineering laptops.',
            'urgency' => 'normal',
            'quantity' => 2,
            'status' => 'approved',
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'purchase_order_number' => 'PO-TEST-100',
            'purchase_request_id' => $purchaseRequest->id,
            'supplier_id' => $supplier->id,
            'created_by_id' => $requester->id,
            'ordered_at' => today(),
        ]);
        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'asset_category_id' => $category->id,
            'model' => 'ThinkPad T14',
            'quantity' => 2,
        ]);
        $this->actingAs($warehouseUser);

        $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receiving", [
            'units' => [[
                'purchase_order_item_id' => $item->id,
                'asset_tag' => 'AT-NEW-100',
                'serial_number' => 'SN-NEW-100',
                'purchase_date' => today()->toDateString(),
                'warranty_expires_at' => today()->addYear()->toDateString(),
                'location_id' => $location->id,
            ]],
        ])->assertOk()->assertJsonPath('data.status', 'partially_received');

        $asset = Asset::query()->where('asset_tag', 'AT-NEW-100')->firstOrFail();
        $this->assertModelExists($asset);
        $this->assertDatabaseHas('receiving_records', [
            'purchase_order_id' => $purchaseOrder->id,
            'asset_id' => $asset->id,
            'received_by_id' => $warehouseUser->id,
        ]);
        $this->assertDatabaseHas('asset_audits', [
            'asset_id' => $asset->id,
            'action' => 'created',
        ]);
        $this->assertSame(1, AssetAudit::query()->where('asset_id', $asset->id)->count());

        $this->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receiving", [
            'units' => [[
                'purchase_order_item_id' => $item->id,
                'asset_tag' => 'AT-NEW-100',
                'serial_number' => 'SN-NEW-101',
                'purchase_date' => today()->toDateString(),
                'location_id' => $location->id,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('units.0.asset_tag');

        $this->assertDatabaseCount('assets', 1);
    }
}
