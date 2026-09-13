<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_sessions', function (Blueprint $table): void {
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('ended_by_user_id')->nullable();

            $table->index(['tenant_id', 'branch_id', 'status', 'expected_end_at'], 'play_sessions_lifecycle_idx');
            $table->foreign(['tenant_id', 'ended_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('play_session_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('actor_user_id');
            $table->uuid('idempotency_key');
            $table->string('command', 40);
            $table->string('fingerprint', 64);
            $table->string('result_status', 20);
            $table->unsignedInteger('result_lock_version');
            $table->json('response_json');
            $table->dateTime('executed_at', 6);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'idempotency_key'], 'play_session_commands_key_unique');
            $table->index(['tenant_id', 'session_id', 'command'], 'play_session_commands_session_idx');
            $table->foreign(['tenant_id', 'session_id'])
                ->references(['tenant_id', 'id'])->on('play_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_session_commands');

        Schema::table('play_sessions', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'ended_by_user_id']);
            $table->dropIndex('play_sessions_lifecycle_idx');
            $table->dropColumn(['cancellation_reason', 'ended_by_user_id']);
        });
    }
};
