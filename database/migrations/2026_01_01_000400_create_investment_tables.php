<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->string('type', 16)->index();
            $table->decimal('quantity', 30, 8)->default(0);
            $table->decimal('unit_price_irr', 24, 8)->default(0);
            $table->decimal('gross_amount_irr', 24, 2)->default(0);
            $table->decimal('fee_amount_irr', 24, 2)->default(0);
            $table->decimal('net_amount_irr', 24, 2)->default(0);
            $table->date('transaction_date');
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('to_cash_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->string('expense_category', 32)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->unsignedBigInteger('idea_id')->nullable()->index();
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('cost_basis_irr', 24, 2)->nullable();
            $table->decimal('realized_pnl_irr', 24, 2)->nullable();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('reversal_ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('asset_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->date('valuation_date')->index();
            $table->decimal('unit_price_irr', 24, 8);
            $table->decimal('total_quantity', 30, 8);
            $table->decimal('total_value_irr', 24, 2);
            $table->string('source')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_valuations');
        Schema::dropIfExists('investment_transactions');
    }
};
