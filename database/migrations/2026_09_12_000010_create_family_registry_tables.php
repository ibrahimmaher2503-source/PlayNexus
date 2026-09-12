<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 190);
            $table->string('phone_e164', 20);
            $table->string('email', 190)->nullable();
            $table->string('preferred_locale', 2)->default('ar');
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('updated_by_user_id');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'phone_e164']);
            $table->index(['tenant_id', 'full_name']);
            $table->foreign(['tenant_id', 'created_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'updated_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('children', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 190);
            $table->date('date_of_birth')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('updated_by_user_id');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'full_name']);
            $table->index(['tenant_id', 'status']);
            $table->foreign(['tenant_id', 'created_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'updated_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('guardian_child', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id');
            $table->foreignId('child_id');
            $table->string('relationship_type', 50);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('updated_by_user_id');
            $table->timestamps();

            $table->primary(['tenant_id', 'guardian_id', 'child_id']);
            $table->index(['tenant_id', 'child_id', 'is_active']);
            $table->index(['tenant_id', 'guardian_id', 'is_active']);
            $table->foreign(['tenant_id', 'guardian_id'])
                ->references(['tenant_id', 'id'])->on('guardians')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'child_id'])
                ->references(['tenant_id', 'id'])->on('children')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'created_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'updated_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_child');
        Schema::dropIfExists('children');
        Schema::dropIfExists('guardians');
    }
};
