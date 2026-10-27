<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 20)->unique();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 20)->unique();
            $table->string('type', 20)->default('office');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 30)->nullable()->unique();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_title')->nullable();
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('retirement_age_years')->default(4);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->text('justification');
            $table->string('urgency', 20);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('status', 40)->default('pending_department_approval')->index();
            $table->unsignedSmallInteger('submission_round')->default(1);
            $table->timestamps();

            $table->index(['requested_by_id', 'created_at']);
        });

        Schema::create('purchase_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('submission_round');
            $table->string('stage', 30);
            $table->string('decision', 20)->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['purchase_request_id', 'submission_round', 'stage'],
                'pr_approvals_round_stage_unique',
            );
            $table->index(['purchase_request_id', 'decision']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order_number')->unique();
            $table->foreignId('purchase_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->date('ordered_at');
            $table->date('expected_at')->nullable();
            $table->string('status', 30)->default('ordered')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->string('model');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('received_quantity')->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 50)->unique();
            $table->string('serial_number', 100)->nullable()->unique();
            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->string('model');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expires_at')->nullable()->index();
            $table->string('condition', 30)->default('new');
            $table->string('encryption_status', 30)->default('unknown');
            $table->foreignId('assigned_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('available')->index();
            $table->timestamps();

            $table->index(['asset_category_id', 'status']);
            $table->index(['location_id', 'status']);
            $table->index(['assigned_department_id', 'status']);
        });

        Schema::create('receiving_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('received_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('field_changes');
            $table->timestamps();

            $table->index(['asset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_audits');
        Schema::dropIfExists('receiving_records');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_approvals');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('asset_categories');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropUnique(['employee_number']);
            $table->dropColumn(['employee_number', 'job_title']);
        });

        Schema::dropIfExists('locations');
        Schema::dropIfExists('departments');
    }
};
