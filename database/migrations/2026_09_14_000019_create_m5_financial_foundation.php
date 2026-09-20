<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->unsignedInteger('discount_approval_bps')->default(0)->after('tax_rate_bps');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('sku', 80);
            $table->string('name', 190);
            $table->string('type', 30);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('EGP');
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->string('tax_mode', 20)->default('exclusive');
            $table->string('status', 20)->default('active');
            $table->dateTime('archived_at', 6)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'branch_id', 'sku']);
            $table->index(['tenant_id', 'status', 'type']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->foreign(['tenant_id', 'branch_id'], 'products_branch_fk')
                ->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('guardian_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('receipt_number', 50)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->char('currency', 3)->default('EGP');
            $table->string('discount_reason', 500)->nullable();
            $table->json('receipt_snapshot_json')->nullable();
            $table->dateTime('receipt_issued_at', 6)->nullable();
            $table->unsignedBigInteger('opened_by_user_id');
            $table->unsignedBigInteger('paid_by_user_id')->nullable();
            $table->dateTime('paid_at', 6)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'branch_id', 'id'], 'orders_tenant_branch_id_unique');
            $table->unique(['tenant_id', 'branch_id', 'receipt_number']);
            $table->unique(['tenant_id', 'session_id']);
            $table->index(['tenant_id', 'branch_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'guardian_id', 'created_at']);
            $table->index(['tenant_id', 'paid_at']);
            $table->foreign(['tenant_id', 'branch_id'], 'orders_branch_fk')
                ->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'guardian_id'], 'orders_guardian_fk')
                ->references(['tenant_id', 'id'])->on('guardians')->restrictOnDelete();
            $table->foreign(['tenant_id', 'session_id'], 'orders_session_fk')
                ->references(['tenant_id', 'id'])->on('play_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'opened_by_user_id'], 'orders_opened_by_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'paid_by_user_id'], 'orders_paid_by_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedSmallInteger('line_number');
            $table->string('item_kind', 30);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('ticket_type_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('description_snapshot', 255);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->char('currency', 3)->default('EGP');
            $table->json('metadata_json')->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'order_id', 'line_number']);
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'ticket_type_id']);
            $table->index(['tenant_id', 'session_id']);
            $table->foreign(['tenant_id', 'order_id'], 'order_items_order_fk')
                ->references(['tenant_id', 'id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'product_id'], 'order_items_product_fk')
                ->references(['tenant_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['tenant_id', 'ticket_type_id'], 'order_items_ticket_type_fk')
                ->references(['tenant_id', 'id'])->on('ticket_types')->restrictOnDelete();
            $table->foreign(['tenant_id', 'session_id'], 'order_items_session_fk')
                ->references(['tenant_id', 'id'])->on('play_sessions')->restrictOnDelete();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('order_id');
            $table->enum('method', ['cash'])->default('cash');
            $table->string('status', 20)->default('posted');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('EGP');
            $table->string('external_reference', 190)->nullable();
            $table->unsignedBigInteger('posted_by_user_id');
            $table->dateTime('posted_at', 6);
            $table->string('idempotency_key', 100);
            $table->char('request_fingerprint', 64);
            $table->unsignedBigInteger('voided_by_user_id')->nullable();
            $table->dateTime('voided_at', 6)->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'branch_id', 'order_id', 'id'], 'payments_tenant_branch_order_id_unique');
            $table->unique(['tenant_id', 'order_id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'posted_at']);
            $table->index(['tenant_id', 'external_reference']);
            $table->foreign(['tenant_id', 'branch_id'], 'payments_branch_fk')
                ->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id', 'order_id'], 'payments_order_branch_fk')
                ->references(['tenant_id', 'branch_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'posted_by_user_id'], 'payments_posted_by_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'voided_by_user_id'], 'payments_voided_by_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('branch_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->string('sequence_name', 50);
            $table->unsignedBigInteger('current_value')->default(0);
            $table->string('prefix', 20);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'branch_id', 'sequence_name']);
            $table->foreign(['tenant_id', 'branch_id'], 'branch_sequences_branch_fk')
                ->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('branch_sequences');

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('discount_approval_bps');
        });
    }
};
