<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_settings', function (Blueprint $table) {
            $table->id();
            $table->string('fund_name')->default('AMOS');
            $table->text('fund_description')->nullable();
            $table->string('base_currency', 8)->default('IRR');
            $table->string('display_currency', 8)->default('IRT');
            $table->decimal('initial_unit_price_irr', 24, 8)->default(1);
            $table->decimal('default_monthly_commitment_irr', 24, 2)->default(0);
            $table->decimal('monthly_contribution_cap_irr', 24, 2)->nullable();
            $table->string('cap_scope', 32)->default('commitment_month');
            $table->string('cap_mode', 32)->default('require_override');
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->boolean('allow_partial_payment')->default(true);
            $table->boolean('allow_early_payment')->default(true);
            $table->boolean('allow_late_payment')->default(true);
            $table->decimal('min_contribution_irr', 24, 2)->default(0);
            $table->string('member_dashboard_visibility', 16)->default('full');
            $table->boolean('withdrawal_enabled')->default(true);
            $table->decimal('withdrawal_fee_percent', 7, 4)->default(0);
            $table->decimal('withdrawal_min_irr', 24, 2)->default(0);
            $table->unsignedInteger('max_upload_kb')->default(5120);
            $table->unsignedInteger('valuation_stale_days')->default(30);
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->json('settings_json')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contribution_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('ownership_type', 16);
            $table->boolean('counts_toward_cap')->default(false);
            $table->boolean('counts_toward_commitment')->default(false);
            $table->boolean('admin_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ownership_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 16)->index();
            $table->foreignId('owner_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_type', 32)->default('bank');
            $table->string('bank_name')->nullable();
            $table->text('account_number_encrypted')->nullable();
            $table->decimal('opening_balance_irr', 24, 2)->default(0);
            $table->unsignedBigInteger('opening_ledger_transaction_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('symbol', 32)->unique();
            $table->foreignId('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->string('unit_name', 32)->default('واحد');
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name');
            $table->string('stored_name')->unique();
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->nullableMorphs('related');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('ownership_accounts');
        Schema::dropIfExists('contribution_types');
        Schema::dropIfExists('fund_settings');
    }
};
