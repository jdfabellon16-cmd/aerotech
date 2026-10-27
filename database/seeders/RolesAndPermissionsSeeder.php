<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'assets.view',
            'assets.manage',
            'purchase_requests.create',
            'purchase_requests.view_all',
            'purchase_requests.review.department',
            'purchase_requests.review.finance',
            'purchase_requests.review.general',
            'purchase_orders.view',
            'purchase_orders.manage',
            'receiving.manage',
            'asset_requests.create',
            'asset_requests.view_all',
            'transfers.create',
            'transfers.manage',
            'maintenance.create',
            'maintenance.manage',
            'maintenance.view',
            'repairs.approve',
            'licenses.request',
            'licenses.approve.manager',
            'licenses.manage',
            'licenses.assign',
            'licenses.review.renewals',
            'lifecycle.manage',
            'disposals.create',
            'disposals.view',
            'disposals.approve.finance',
            'disposals.approve.management',
            'disposals.complete',
            'reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $fullAccess = $permissions;
        $roles = [
            'IT Asset Manager' => $fullAccess,
            'Help Desk Technician' => [
                'assets.view',
                'assets.manage',
                'purchase_requests.view_all',
                'asset_requests.view_all',
                'transfers.manage',
                'maintenance.create',
                'maintenance.manage',
                'maintenance.view',
                'licenses.assign',
                'lifecycle.manage',
                'disposals.create',
                'disposals.complete',
                'reports.view',
            ],
            'IT Operations Manager' => [
                'assets.view',
                'assets.manage',
                'purchase_requests.view_all',
                'asset_requests.view_all',
                'transfers.manage',
                'maintenance.create',
                'maintenance.manage',
                'maintenance.view',
                'repairs.approve',
                'licenses.manage',
                'licenses.assign',
                'licenses.review.renewals',
                'lifecycle.manage',
                'disposals.create',
                'disposals.complete',
                'reports.view',
            ],
            'Department Manager' => [
                'assets.view',
                'purchase_requests.create',
                'purchase_requests.view_all',
                'purchase_requests.review.department',
                'asset_requests.create',
                'transfers.create',
                'maintenance.create',
                'licenses.request',
                'licenses.approve.manager',
                'disposals.create',
            ],
            'Finance Manager' => [
                'assets.view',
                'purchase_requests.view_all',
                'purchase_requests.review.finance',
                'repairs.approve',
                'disposals.approve.finance',
                'licenses.review.renewals',
                'disposals.view',
                'reports.view',
            ],
            'General Manager' => [
                'assets.view',
                'purchase_requests.view_all',
                'purchase_requests.review.general',
                'disposals.approve.management',
                'licenses.review.renewals',
                'disposals.view',
                'reports.view',
            ],
            'Purchasing Officer' => [
                'assets.view',
                'purchase_requests.view_all',
                'purchase_orders.view',
                'purchase_orders.manage',
                'licenses.manage',
                'reports.view',
            ],
            'Warehouse Supervisor' => [
                'assets.view',
                'purchase_orders.view',
                'receiving.manage',
                'asset_requests.view_all',
            ],
            'HR' => ['assets.view', 'repairs.approve', 'reports.view'],
            'Procurement Staff' => ['assets.view', 'reports.view'],
            'Employee/Requestor' => [
                'purchase_requests.create',
                'asset_requests.create',
                'transfers.create',
                'maintenance.create',
                'licenses.request',
                'disposals.create',
            ],
        ];

        foreach ($roles as $name => $rolePermissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
