<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('creation_idempotency_key')->nullable()->after('status');
            $table->char('creation_fingerprint', 64)->nullable()->after('creation_idempotency_key');
            $table->unique(['tenant_id', 'creation_idempotency_key'], 'orders_creation_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_creation_idempotency_unique');
            $table->dropColumn(['creation_idempotency_key', 'creation_fingerprint']);
        });
    }
};
