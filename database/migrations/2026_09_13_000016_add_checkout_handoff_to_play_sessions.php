<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_sessions', function (Blueprint $table): void {
            $table->uuid('checkout_idempotency_key')->nullable();
            $table->string('checkout_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('checkout_guardian_id')->nullable();
            $table->string('checkout_verification_method', 40)->nullable();
            $table->text('checkout_override_reason')->nullable();
            $table->unsignedBigInteger('checkout_verified_by_user_id')->nullable();
            $table->dateTime('checkout_verified_at', 6)->nullable();
            $table->dateTime('checkout_prepared_at', 6)->nullable();
            $table->json('checkout_snapshot_json')->nullable();
            $table->unsignedBigInteger('checkout_amount_due_minor')->nullable();

            $table->unique(['tenant_id', 'checkout_idempotency_key'], 'play_sessions_checkout_key_unique');
            $table->index(['tenant_id', 'branch_id', 'status', 'checkout_prepared_at'], 'play_sessions_checkout_queue_idx');
            $table->foreign(['tenant_id', 'checkout_guardian_id'])
                ->references(['tenant_id', 'id'])->on('guardians')->restrictOnDelete();
            $table->foreign(['tenant_id', 'checkout_verified_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('play_sessions', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'checkout_guardian_id']);
            $table->dropForeign(['tenant_id', 'checkout_verified_by_user_id']);
            $table->dropIndex('play_sessions_checkout_queue_idx');
            $table->dropUnique('play_sessions_checkout_key_unique');
            $table->dropColumn([
                'checkout_idempotency_key',
                'checkout_fingerprint',
                'checkout_guardian_id',
                'checkout_verification_method',
                'checkout_override_reason',
                'checkout_verified_by_user_id',
                'checkout_verified_at',
                'checkout_prepared_at',
                'checkout_snapshot_json',
                'checkout_amount_due_minor',
            ]);
        });
    }
};
