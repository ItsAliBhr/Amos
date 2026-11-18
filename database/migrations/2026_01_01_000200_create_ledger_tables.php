<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 48)->index();
            $table->nullableMorphs('reference');
            $table->date('transaction_date')->index();
            $table->string('description', 500)->nullable();
            $table->string('status', 16)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('account_code', 32)->index();
            $table->foreignId('ownership_account_id')->nullable()->constrained('ownership_accounts')->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->decimal('quantity', 30, 8)->default(0);
            $table->decimal('debit_irr', 24, 2)->default(0);
            $table->decimal('credit_irr', 24, 2)->default(0);
            $table->string('description', 500)->nullable();
            $table->timestamps();
            $table->index(['account_code', 'asset_id']);
            $table->index(['account_code', 'cash_account_id']);
        });

        Schema::create('investment_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ownership_account_id')->constrained('ownership_accounts')->restrictOnDelete();
            $table->decimal('units', 24, 8);
            $table->decimal('nav_per_unit_irr', 24, 8);
            $table->decimal('amount_irr', 24, 2);
            $table->timestamp('effective_at');
            $table->morphs('source');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('status', 16)->default('posted');
            $table->timestamps();
        });

        Schema::create('nav_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->decimal('cash_irr', 24, 2);
            $table->decimal('assets_market_irr', 24, 2);
            $table->decimal('assets_cost_irr', 24, 2);
            $table->decimal('receivables_irr', 24, 2)->default(0);
            $table->decimal('payables_irr', 24, 2)->default(0);
            $table->decimal('nav_irr', 24, 2);
            $table->decimal('total_units', 24, 8);
            $table->decimal('nav_per_unit_irr', 24, 8);
            $table->decimal('realized_pnl_irr', 24, 2)->default(0);
            $table->decimal('unrealized_pnl_irr', 24, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_snapshots');
        Schema::dropIfExists('investment_units');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
    }
};
