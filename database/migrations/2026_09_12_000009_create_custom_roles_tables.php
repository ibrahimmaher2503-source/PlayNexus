<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 50);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('custom_role_permissions', function (Blueprint $table): void {
            $table->foreignId('custom_role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 80);
            $table->primary(['custom_role_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_role_permissions');
        Schema::dropIfExists('custom_roles');
    }
};
