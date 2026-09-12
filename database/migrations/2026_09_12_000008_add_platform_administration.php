<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('internal_identifier', 50)->nullable()->after('name');
            $table->string('plan_reference', 190)->nullable()->after('internal_identifier');
            $table->string('status', 20)->default('pending')->index()->after('is_active');
            $table->string('provisioning_key', 100)->nullable()->after('status');
            $table->char('provisioning_payload_hash', 64)->nullable()->after('provisioning_key');
        });

        foreach (DB::table('tenants')->select(['id', 'is_active'])->orderBy('id')->get() as $tenant) {
            DB::table('tenants')->where('id', $tenant->id)->update([
                'internal_identifier' => 'TEN-'.$tenant->id,
                'status' => (bool) $tenant->is_active ? 'active' : 'suspended',
            ]);
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->unique('internal_identifier');
            $table->unique('provisioning_key');
        });

        Schema::create('platform_admins', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->string('action', 120);
            $table->string('subject_type', 80);
            $table->string('subject_id', 26)->nullable();
            $table->string('outcome', 20);
            $table->string('reason_code', 80)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->uuid('request_id');
            $table->dateTime('occurred_at', 6);
            $table->index(['target_tenant_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_admins');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropUnique('tenants_internal_identifier_unique');
            $table->dropUnique('tenants_provisioning_key_unique');
            $table->dropIndex('tenants_status_index');
            $table->dropColumn([
                'internal_identifier',
                'plan_reference',
                'status',
                'provisioning_key',
                'provisioning_payload_hash',
            ]);
        });
    }
};
