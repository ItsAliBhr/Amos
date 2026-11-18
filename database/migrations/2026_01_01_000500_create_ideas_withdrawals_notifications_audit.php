<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->text('description');
            $table->decimal('required_capital_irr', 24, 2)->nullable();
            $table->string('time_horizon', 100)->nullable();
            $table->decimal('expected_return_min', 9, 2)->nullable();
            $table->decimal('expected_return_max', 9, 2)->nullable();
            $table->string('expected_return_period', 32)->nullable();
            $table->text('risk_description')->nullable();
            $table->text('assumptions')->nullable();
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decision_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('executed_investment_id')->nullable()->constrained('investment_transactions')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('idea_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->restrictOnDelete();
            $table->string('opinion', 16);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['idea_id', 'member_id']);
        });

        Schema::create('idea_review_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->restrictOnDelete();
            $table->string('opinion', 16);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('idea_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('ownership_account_id')->constrained('ownership_accounts')->restrictOnDelete();
            $table->decimal('requested_amount_irr', 24, 2);
            $table->decimal('approved_amount_irr', 24, 2)->nullable();
            $table->decimal('fee_irr', 24, 2)->nullable();
            $table->decimal('net_paid_irr', 24, 2)->nullable();
            $table->decimal('units_redeemed', 24, 8)->nullable();
            $table->decimal('nav_per_unit_irr', 24, 8)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->text('request_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('requested_at');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('title');
            $table->text('body')->nullable();
            $table->nullableMorphs('related');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values_json')->nullable();
            $table->json('new_values_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('idea_status_histories');
        Schema::dropIfExists('idea_review_histories');
        Schema::dropIfExists('idea_reviews');
        Schema::dropIfExists('ideas');
    }
};
