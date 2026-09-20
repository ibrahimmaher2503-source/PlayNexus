<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->unsignedBigInteger('rejected_by_user_id')->nullable();
            $table->unsignedBigInteger('consumed_by_user_id')->nullable();
            $table->unsignedBigInteger('discount_minor');
            $table->char('currency', 3)->default('EGP');
            $table->string('reason', 500);
            $table->string('rejection_reason', 500)->nullable();
            $table->json('request_payload_json');
            $table->char('payload_fingerprint', 64);
            $table->unsignedInteger('expected_order_lock_version');
            $table->string('status', 20)->default('requested');
            $table->dateTime('expires_at', 6);
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('rejected_at', 6)->nullable();
            $table->dateTime('consumed_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'branch_id', 'order_id', 'status'], 'approval_records_order_status_idx');
            $table->index(['tenant_id', 'expires_at', 'status'], 'approval_records_expiry_idx');
            $table->foreign(['tenant_id', 'branch_id'], 'approval_records_branch_fk')
                ->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id', 'order_id'], 'approval_records_order_fk')
                ->references(['tenant_id', 'branch_id', 'id'])->on('orders')->restrictOnDelete();
            foreach (['requested_by_user_id', 'approved_by_user_id', 'rejected_by_user_id', 'consumed_by_user_id'] as $column) {
                $table->foreign(['tenant_id', $column], 'approval_records_'.$column.'_fk')
                    ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_records');
    }
};
