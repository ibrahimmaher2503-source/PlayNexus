<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('guardian_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('channel', 20)->default('database');
            $table->string('purpose', 30);
            $table->string('template_key', 100);
            $table->string('template_version', 40)->default('1');
            $table->string('locale', 10)->default('ar');
            $table->text('destination_encrypted')->nullable();
            $table->string('destination_masked', 100)->nullable();
            $table->json('payload_json');
            $table->string('status', 30)->default('queued');
            $table->string('dedupe_key', 190);
            $table->dateTime('scheduled_at', 6);
            $table->dateTime('sent_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->dateTime('failed_at', 6)->nullable();
            $table->dateTime('stale_at', 6)->nullable();
            $table->string('provider_message_id', 190)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'dedupe_key']);
            $table->index(['tenant_id', 'status', 'scheduled_at']);
            $table->index(['tenant_id', 'branch_id', 'created_at']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'guardian_id'])->references(['tenant_id', 'id'])->on('guardians')->restrictOnDelete();
            $table->foreign(['tenant_id', 'session_id'])->references(['tenant_id', 'id'])->on('play_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
        });

        Schema::create('notification_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('notification_message_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->string('provider', 80)->default('local_database');
            $table->dateTime('started_at', 6);
            $table->dateTime('finished_at', 6);
            $table->string('outcome', 30);
            $table->string('provider_status_code', 80)->nullable();
            $table->string('provider_message_id', 190)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->string('error_summary_masked', 500)->nullable();

            $table->unique(['tenant_id', 'notification_message_id', 'attempt_number'], 'notification_attempt_number_unique');
            $table->index(['tenant_id', 'outcome', 'started_at']);
            $table->foreign(['tenant_id', 'notification_message_id'])
                ->references(['tenant_id', 'id'])->on('notification_messages')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
        Schema::dropIfExists('notification_messages');
    }
};
