<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'branch_id', 'id'], 'tickets_tenant_branch_id_unique');
        });

        Schema::create('play_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('child_id');
            $table->unsignedBigInteger('guardian_id');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('pricing_rule_id');
            $table->string('status', 20)->default('active');
            $table->dateTime('started_at', 6);
            $table->dateTime('expected_end_at', 6);
            $table->dateTime('ended_at', 6)->nullable();
            $table->json('pricing_snapshot_json');
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id'], 'play_sessions_tenant_id_unique');
            $table->unique(['tenant_id', 'ticket_id'], 'play_sessions_ticket_unique');
            $table->index(['tenant_id', 'branch_id', 'status', 'started_at'], 'play_sessions_branch_status_started_idx');
            $table->index(['tenant_id', 'child_id', 'status'], 'play_sessions_child_status_idx');
            $table->index(['tenant_id', 'status', 'expected_end_at'], 'play_sessions_status_expected_end_idx');
            $table->index(['tenant_id', 'started_at'], 'play_sessions_started_idx');

            $table->foreign(['tenant_id', 'branch_id'])
                ->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'child_id'])
                ->references(['tenant_id', 'id'])->on('children')->restrictOnDelete();
            $table->foreign(['tenant_id', 'guardian_id'])
                ->references(['tenant_id', 'id'])->on('guardians')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id', 'ticket_id'])
                ->references(['tenant_id', 'branch_id', 'id'])->on('tickets')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id', 'pricing_rule_id'])
                ->references(['tenant_id', 'branch_id', 'id'])->on('pricing_rules')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('play_session_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('session_id');
            $table->string('event_type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->json('metadata_json')->nullable();
            $table->uuid('request_id');
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id'], 'play_session_events_tenant_id_unique');
            $table->index(['tenant_id', 'session_id', 'occurred_at'], 'play_session_events_session_time_idx');
            $table->index(['tenant_id', 'event_type', 'occurred_at'], 'play_session_events_type_time_idx');
            $table->foreign(['tenant_id', 'session_id'])
                ->references(['tenant_id', 'id'])->on('play_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_session_events');
        Schema::dropIfExists('play_sessions');

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropUnique('tickets_tenant_branch_id_unique');
        });
    }
};
