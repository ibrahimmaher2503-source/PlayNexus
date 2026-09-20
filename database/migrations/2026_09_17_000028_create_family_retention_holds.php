<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_retention_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('guardian_id');
            $table->string('category', 30);
            $table->string('reason', 500);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('placed_by_user_id');
            $table->timestamp('placed_at');
            $table->unsignedBigInteger('released_by_user_id')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 500)->nullable();
            $table->uuid('request_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'guardian_id', 'status'], 'family_retention_holds_subject_status_idx');
            $table->index(['tenant_id', 'status', 'placed_at'], 'family_retention_holds_status_time_idx');
            $table->foreign(['tenant_id', 'guardian_id'])
                ->references(['tenant_id', 'id'])->on('guardians')->restrictOnDelete();
            $table->foreign(['tenant_id', 'placed_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'released_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_retention_holds');
    }
};
