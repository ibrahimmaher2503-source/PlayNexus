<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('actor_user_id');
            $table->string('actor_type', 20);
            $table->string('action', 120);
            $table->string('subject_type', 80);
            $table->string('subject_id', 26);
            $table->string('outcome', 20);
            $table->string('reason_code', 80);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->uuid('request_id');
            $table->dateTime('occurred_at', 6);
            $table->foreign(['tenant_id', 'actor_user_id'])->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
