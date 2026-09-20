<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->unsignedBigInteger('executed_by_user_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reason', 500);
            $table->string('status', 20)->default('requested');
            $table->unsignedInteger('expected_order_lock_version');
            $table->string('request_idempotency_key', 100);
            $table->char('request_fingerprint', 64);
            $table->string('execution_idempotency_key', 100)->nullable();
            $table->char('execution_fingerprint', 64)->nullable();
            $table->dateTime('requested_at', 6);
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('executed_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'order_id']);
            $table->unique(['tenant_id', 'request_idempotency_key']);
            $table->unique(['tenant_id', 'execution_idempotency_key']);
            $table->foreign(['tenant_id', 'branch_id'], 'refunds_branch_fk')->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id', 'order_id'], 'refunds_order_fk')->references(['tenant_id', 'branch_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id', 'order_id', 'payment_id'], 'refunds_payment_order_fk')
                ->references(['tenant_id', 'branch_id', 'order_id', 'id'])->on('payments')->restrictOnDelete();
            foreach (['requested_by_user_id', 'approved_by_user_id', 'executed_by_user_id'] as $column) {
                $table->foreign(['tenant_id', $column], 'refunds_'.$column.'_fk')->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
