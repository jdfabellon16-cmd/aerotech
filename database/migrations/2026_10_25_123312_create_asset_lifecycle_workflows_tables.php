<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->text('justification');
            $table->string('urgency', 20)->default('normal');
            $table->string('status', 30)->default('open')->index();
            $table->boolean('policy_checked')->default(false);
            $table->foreignId('policy_checked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('policy_notes')->nullable();
            $table->foreignId('assigned_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->timestamps();

            $table->index(['requested_by_id', 'created_at']);
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('handoff_method', 20);
            $table->string('evidence_path')->nullable();
            $table->string('accountability_form_path')->nullable();
            $table->string('status', 30)->default('pending_acknowledgement')->index();
            $table->string('signature_name')->nullable();
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'assigned_at']);
        });

        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->string('transfer_type', 20);
            $table->text('reason');
            $table->string('status', 30)->default('pending_approval')->index();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('loan_started_at')->nullable();
            $table->date('due_at')->nullable();
            $table->date('replacement_expected_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('location_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained('asset_transfers')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('changed_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('accepted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('issue_description');
            $table->json('assessment_checklist')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->timestamp('sla_due_at')->index();
            $table->string('status', 30)->default('open')->index();
            $table->boolean('warranty_covered')->default(false);
            $table->timestamp('warranty_checked_at')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('performed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->boolean('paid')->default(false);
            $table->boolean('cost_approved')->default(false);
            $table->foreignId('cost_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('hr_cost_deduction_flag')->default(false);
            $table->boolean('warranty_used')->default(false);
            $table->text('repair_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('license_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('software_name');
            $table->text('business_justification');
            $table->string('status', 35)->default('pending_manager_approval')->index();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('purchasing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->foreignId('software_license_id')->nullable();
            $table->timestamps();
        });

        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor');
            $table->text('license_key')->nullable();
            $table->unsignedInteger('total_seats');
            $table->decimal('cost', 12, 2)->default(0);
            $table->date('expires_at')->nullable()->index();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['name', 'vendor']);
        });

        Schema::table('license_requests', function (Blueprint $table) {
            $table->foreign('software_license_id')->references('id')->on('software_licenses')->nullOnDelete();
        });

        Schema::create('license_renewal_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_license_id')->constrained()->cascadeOnDelete();
            $table->date('expires_at');
            $table->unsignedSmallInteger('days_before_expiry');
            $table->string('status', 35)->default('pending_it_review')->index();
            $table->foreignId('it_reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('it_reviewed_at')->nullable();
            $table->text('it_notes')->nullable();
            $table->foreignId('management_reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('management_reviewed_at')->nullable();
            $table->text('management_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['software_license_id', 'expires_at', 'days_before_expiry'],
                'license_renewal_expiry_review_unique',
            );
        });

        Schema::create('license_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_license_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['software_license_id', 'revoked_at']);
        });

        Schema::create('license_compliance_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30);
            $table->unsignedInteger('seats_purchased')->default(0);
            $table->unsignedInteger('seats_assigned')->default(0);
            $table->json('findings')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
        });

        Schema::create('lifecycle_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_category_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('retirement_age_years');
            $table->unsignedSmallInteger('incident_threshold')->default(3);
            $table->unsignedSmallInteger('incident_window_months')->default(12);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('lifecycle_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('review_date');
            $table->string('status', 30)->default('due')->index();
            $table->unsignedSmallInteger('incident_count')->default(0);
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'review_date']);
        });

        Schema::create('disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->string('disposal_type', 20)->default('disposal');
            $table->string('status', 35)->default('pending_finance_approval')->index();
            $table->foreignId('finance_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('management_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('data_wipe_done')->default(false);
            $table->boolean('company_files_removed')->default(false);
            $table->string('certificate_path')->nullable();
            $table->string('recipient_or_vendor')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposals');
        Schema::dropIfExists('lifecycle_reviews');
        Schema::dropIfExists('lifecycle_rules');
        Schema::dropIfExists('license_compliance_checks');
        Schema::dropIfExists('license_assignments');
        Schema::dropIfExists('license_renewal_reviews');
        Schema::table('license_requests', function (Blueprint $table) {
            $table->dropForeign(['software_license_id']);
        });
        Schema::dropIfExists('software_licenses');
        Schema::dropIfExists('license_requests');
        Schema::dropIfExists('repairs');
        Schema::dropIfExists('maintenance_tickets');
        Schema::dropIfExists('location_change_logs');
        Schema::dropIfExists('asset_transfers');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('asset_requests');
    }
};
