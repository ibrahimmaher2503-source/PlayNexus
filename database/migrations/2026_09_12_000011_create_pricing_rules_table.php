<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->string('code', 50);
            $table->string('name', 190);
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('billing_mode', 30)->default('fixed_duration');
            $table->unsignedInteger('base_duration_seconds');
            $table->unsignedBigInteger('base_price_minor');
            $table->unsignedInteger('grace_period_seconds')->default(600);
            $table->unsignedInteger('overtime_unit_seconds')->default(1800);
            $table->unsignedBigInteger('overtime_price_minor');
            $table->char('currency', 3)->default('EGP');
            $table->unsignedInteger('tax_rate_bps');
            $table->string('tax_mode', 20);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'code', 'version']);
            $table->index(['tenant_id', 'branch_id', 'status', 'code']);
            $table->index(['tenant_id', 'created_by_user_id']);
            $table->foreign(['tenant_id', 'branch_id'])
                ->references(['tenant_id', 'id'])
                ->on('branches')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'created_by_user_id'])
                ->references(['tenant_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
