<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\LifecycleRule;
use App\Models\Location;
use App\Models\LicenseAssignment;
use App\Models\SoftwareLicense;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AssetManagementSeeder extends Seeder
{
    public function run(): void
    {
        $locations = collect([
            ['name' => 'HQ', 'code' => 'HQ', 'type' => 'office'],
            ['name' => 'Cebu', 'code' => 'CEB', 'type' => 'office'],
            ['name' => 'Davao', 'code' => 'DVO', 'type' => 'office'],
            ['name' => 'Remote', 'code' => 'REMOTE', 'type' => 'remote'],
            ['name' => 'Field', 'code' => 'FIELD', 'type' => 'field'],
            ['name' => 'HQ Broken Storage', 'code' => 'HQ-BROKEN', 'type' => 'storage'],
            ['name' => 'HQ Disposal Hold', 'code' => 'HQ-DISPOSAL', 'type' => 'storage'],
            ['name' => 'Cebu Broken Storage', 'code' => 'CEB-BROKEN', 'type' => 'storage'],
            ['name' => 'Davao Broken Storage', 'code' => 'DVO-BROKEN', 'type' => 'storage'],
        ])->mapWithKeys(fn (array $data): array => [
            $data['code'] => Location::updateOrCreate(['code' => $data['code']], $data),
        ]);

        $departments = collect([
            'Information Technology',
            'Finance',
            'Human Resources',
            'Engineering',
            'Operations',
            'Customer Support',
            'Sales',
            'Purchasing',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Department::updateOrCreate(
                ['code' => Str::upper(Str::substr(Str::slug($name), 0, 8))],
                ['name' => $name],
            ),
        ]);

        $categories = collect([
            ['name' => 'Laptops', 'slug' => 'laptops', 'retirement_age_years' => 4],
            ['name' => 'Desktops', 'slug' => 'desktops', 'retirement_age_years' => 5],
            ['name' => 'Tablets', 'slug' => 'tablets', 'retirement_age_years' => 4],
            ['name' => 'Mobile Phones', 'slug' => 'mobile-phones', 'retirement_age_years' => 3],
            ['name' => 'Monitors', 'slug' => 'monitors', 'retirement_age_years' => 6],
            ['name' => 'Docking Stations', 'slug' => 'docking-stations', 'retirement_age_years' => 5],
            ['name' => 'Peripherals', 'slug' => 'peripherals', 'retirement_age_years' => 4],
            ['name' => 'Printers/Scanners', 'slug' => 'printers-scanners', 'retirement_age_years' => 6],
            ['name' => 'Network Equipment', 'slug' => 'network-equipment', 'retirement_age_years' => 6],
            ['name' => 'Specialized Equipment', 'slug' => 'specialized-equipment', 'retirement_age_years' => 5],
        ])->mapWithKeys(fn (array $data): array => [
            $data['slug'] => AssetCategory::updateOrCreate(['slug' => $data['slug']], $data),
        ]);

        foreach ($categories as $category) {
            LifecycleRule::updateOrCreate(
                ['asset_category_id' => $category->id],
                [
                    'retirement_age_years' => $category->retirement_age_years,
                    'incident_threshold' => 3,
                    'incident_window_months' => 12,
                    'active' => true,
                ],
            );
        }

        $supplier = Supplier::updateOrCreate(
            ['name' => 'AeroTech Preferred Supply'],
            [
                'contact_name' => 'Demo Account',
                'email' => 'supply@example.test',
                'phone' => '+63 2 8000 0000',
                'address' => 'Makati City, Philippines',
            ],
        );

        $roleUsers = [
            ['IT Asset Manager', 'Alex Santos', 'Information Technology'],
            ['Help Desk Technician', 'Jamie Reyes', 'Information Technology'],
            ['IT Operations Manager', 'Taylor Cruz', 'Operations'],
            ['Department Manager', 'Morgan Garcia', 'Engineering'],
            ['Finance Manager', 'Riley Dela Cruz', 'Finance'],
            ['General Manager', 'Jordan Bautista', 'Operations'],
            ['Purchasing Officer', 'Casey Mendoza', 'Purchasing'],
            ['Warehouse Supervisor', 'Avery Flores', 'Operations'],
            ['HR', 'Sam Navarro', 'Human Resources'],
            ['Procurement Staff', 'Drew Lim', 'Purchasing'],
            ['Employee/Requestor', 'Kai Villanueva', 'Engineering'],
        ];

        $users = collect($roleUsers)->mapWithKeys(function (array $record, int $index) use ($locations, $departments): array {
            [$role, $name, $department] = $record;
            $email = Str::slug($role).'@aerotech.example';
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'employee_number' => sprintf('AT-%04d', $index + 1),
                    'department_id' => $departments[$department]->id,
                    'location_id' => $locations[array_values($locations->all())[$index % $locations->count()]->code]->id,
                    'job_title' => $role,
                ],
            );
            $user->syncRoles([$role]);

            return [$role => $user];
        });

        $assetExamples = [
            ['AT-LT-1001', 'AET-LT-1001', 'laptops', 'ThinkPad T14 Gen 5', 'in_use', 0],
            ['AT-LT-1002', 'AET-LT-1002', 'laptops', 'Dell Latitude 5450', 'available', 1],
            ['AT-DT-2001', 'AET-DT-2001', 'desktops', 'OptiPlex 7020', 'in_use', 0],
            ['AT-TB-3001', 'AET-TB-3001', 'tablets', 'iPad Air', 'in_use', 2],
            ['AT-MN-4001', 'AET-MN-4001', 'monitors', 'Dell P2425H', 'available', 3],
            ['AT-NW-5001', 'AET-NW-5001', 'network-equipment', 'Cisco Catalyst 9200', 'in_repair', 2],
        ];

        foreach ($assetExamples as [$tag, $serial, $category, $model, $status, $locationIndex]) {
            Asset::firstOrCreate(
                ['asset_tag' => $tag],
                [
                    'serial_number' => $serial,
                    'asset_category_id' => $categories[$category]->id,
                    'model' => $model,
                    'supplier_id' => $supplier->id,
                    'purchase_date' => now()->subYears(1)->toDateString(),
                    'warranty_expires_at' => now()->addYears(2)->toDateString(),
                    'condition' => $status === 'in_repair' ? 'damaged' : 'good',
                    'encryption_status' => 'encrypted',
                    'assigned_department_id' => $status === 'in_use' ? $departments['Engineering']->id : null,
                    'location_id' => array_values($locations->all())[$locationIndex]->id,
                    'assignee_id' => $status === 'in_use' ? $users['Employee/Requestor']->id : null,
                    'status' => $status,
                ],
            );
        }

        $license = SoftwareLicense::updateOrCreate(
            ['name' => 'AeroCAD Standard', 'vendor' => 'Autodesk'],
            [
                'total_seats' => 25,
                'cost' => 145000,
                'expires_at' => now()->addDays(90)->toDateString(),
                'recorded_by_id' => $users['IT Asset Manager']->id,
            ],
        );
        LicenseAssignment::firstOrCreate(
            [
                'software_license_id' => $license->id,
                'user_id' => $users['Employee/Requestor']->id,
                'revoked_at' => null,
            ],
            [
                'assigned_by_id' => $users['IT Asset Manager']->id,
                'assigned_at' => now()->subDays(10),
            ],
        );
    }
}
