<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('type_id')->constrained('contribution_types')->restrictOnDelete();
            $table->foreignId('ownership_account_id')->nullable()->constrained('ownership_accounts')->restrictOnDelete();
            $table->decimal('amount_irr', 24, 2);
            $table->date('contribution_date');
            $table->unsignedSmallInteger('commitment_year')->nullable();
            $table->unsignedTinyInteger('commitment_month')->nullable();
            $table->foreignId('destination_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->string('payment_method', 32)->nullable();
            $table->string('tracking_code', 100)->nullable();
            $table->foreignId('receipt_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->boolean('exceeds_cap')->default(false);
            $table->boolean('cap_override')->default(false);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->decimal('units_issued', 24, 8)->nullable();
            $table->decimal('nav_per_unit_irr', 24, 8)->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('reversal_ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
            $table->index(['member_id', 'commitment_year', 'commitment_month']);
        });

        Schema::create('monthly_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('commitment_amount_irr', 24, 2);
            $table->decimal('exemption_amount_irr', 24, 2)->default(0);
            $table->string('status', 16)->default('unpaid');
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['member_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_commitments');
        Schema::dropIfExists('contributions');
    }
};
