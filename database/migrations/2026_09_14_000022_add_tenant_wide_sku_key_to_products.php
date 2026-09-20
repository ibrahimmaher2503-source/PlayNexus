<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('tenant_wide_sku', 80)->nullable()
                ->virtualAs('CASE WHEN branch_id IS NULL THEN UPPER(sku) ELSE NULL END')
                ->after('sku');
            $table->unique(['tenant_id', 'tenant_wide_sku'], 'products_tenant_wide_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_tenant_wide_sku_unique');
            $table->dropColumn('tenant_wide_sku');
        });
    }
};
