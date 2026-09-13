<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_session_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('actor_user_id');
            $table->integer('extension_units')->default(0);
            $table->bigInteger('adjustment_minor')->default(0);
            $table->string('reason', 500);
            $table->unsignedInteger('expected_lock_version');
            $table->unsignedInteger('applied_lock_version');
            $table->uuid('request_id');
            $table->dateTime('created_at', 6);

            $table->unique(['tenant_id', 'id'], 'play_session_adjustments_tenant_id_unique');
            $table->index(['tenant_id', 'session_id', 'id'], 'play_session_adjustments_session_idx');
            $table->foreign(['tenant_id', 'session_id'])
                ->references(['tenant_id', 'id'])->on('play_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_session_adjustments');
    }
};
