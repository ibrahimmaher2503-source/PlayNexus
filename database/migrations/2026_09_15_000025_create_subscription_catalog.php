<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('active');
            $table->json('limits_json');
            $table->json('features_json');
            // Plan prices are deliberately kept on the plan. A subscription
            // snapshots them when it is assigned, preserving commercial history.
            $table->unsignedBigInteger('monthly_price_minor')->nullable();
            $table->unsignedBigInteger('annual_price_minor')->nullable();
            $table->unsignedInteger('annual_discount_bps')->default(0);
            $table->char('currency', 3)->default('EGP');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps(6);

            $table->unique('code');
            $table->index('status');
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 20)->default('trialing');
            $table->string('billing_interval', 10)->default('monthly');
            $table->unsignedBigInteger('price_amount_minor')->nullable();
            $table->char('price_currency', 3)->default('EGP');
            $table->unsignedInteger('price_annual_discount_bps')->default(0);
            $table->json('custom_limits_json')->nullable();
            $table->dateTime('custom_limits_override_until', 6)->nullable();
            $table->string('custom_limits_reason', 500)->nullable();
            $table->foreignId('custom_limits_set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at', 6);
            $table->dateTime('trial_ends_at', 6)->nullable();
            $table->dateTime('grace_ends_at', 6)->nullable();
            $table->dateTime('current_period_starts_at', 6);
            $table->dateTime('current_period_ends_at', 6);
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'current_period_ends_at']);
            $table->index(['tenant_id', 'billing_interval']);
        });

        // This is SaaS commercial bookkeeping only. It never shares POS
        // receipts, payment records, or numbering.
        Schema::create('subscription_billing_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('subscription_id');
            $table->string('reference', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('EGP');
            $table->dateTime('billing_period_starts_at', 6);
            $table->dateTime('billing_period_ends_at', 6);
            $table->dateTime('paid_at', 6);
            $table->string('payment_method', 30);
            $table->string('notes', 1000)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'paid_at']);
            $table->index(
                ['tenant_id', 'subscription_id', 'billing_period_starts_at'],
                'saas_billing_tenant_subscription_period_idx'
            );
            $table->foreign(['tenant_id', 'subscription_id'], 'subscription_billing_records_subscription_fk')
                ->references(['tenant_id', 'id'])->on('subscriptions')->restrictOnDelete();
        });

        // A tenant points to exactly one authoritative commercial subscription;
        // all other subscription rows are immutable history. The composite FK
        // prevents pointing at a subscription belonging to another tenant.
        Schema::table('tenants', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_subscription_id')->nullable()->after('plan_reference');
            // Display/legacy integration denormalization only. Access always
            // resolves current_subscription_id, never this field.
            $table->string('billing_status', 20)->default('trial')->index()->after('current_subscription_id');
            $table->index(['id', 'current_subscription_id'], 'tenants_current_subscription_lookup');
            $table->foreign(['id', 'current_subscription_id'], 'tenants_current_subscription_fk')
                ->references(['tenant_id', 'id'])->on('subscriptions')->restrictOnDelete();
        });

        // Lifecycle maintenance is automated, not attributed to a human.
        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            $table->foreignId('actor_user_id')->nullable()->change();
            $table->string('actor_type', 20)->default('user')->after('actor_user_id');
        });
    }

    public function down(): void
    {
        // System lifecycle entries are owned by this migration and cannot be
        // represented once the legacy non-null actor contract is restored.
        DB::table('platform_audit_logs')->where('actor_type', 'system')->delete();

        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            $table->dropColumn('actor_type');
            $table->foreignId('actor_user_id')->nullable(false)->change();
        });

        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot drop a column that participates in a composite FK.
            // Rebuild only this table, preserving its pre-OQ-04 contract and
            // indexes, while foreign-key checking is suspended for the swap.
            Schema::disableForeignKeyConstraints();
            DB::unprepared(<<<'SQL'
CREATE TABLE tenants_oq04_rollback (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR NOT NULL,
    legal_name VARCHAR(190) NULL,
    default_locale VARCHAR(2) NOT NULL DEFAULT 'en',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Cairo',
    currency VARCHAR(3) NOT NULL DEFAULT 'EGP',
    lock_version INTEGER NOT NULL DEFAULT 1,
    internal_identifier VARCHAR(50) NULL,
    plan_reference VARCHAR(190) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT '1',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    provisioning_key VARCHAR(100) NULL,
    provisioning_payload_hash VARCHAR(64) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
)
SQL);
            DB::unprepared('INSERT INTO tenants_oq04_rollback (id, name, legal_name, default_locale, timezone, currency, lock_version, internal_identifier, plan_reference, is_active, status, provisioning_key, provisioning_payload_hash, created_at, updated_at) SELECT id, name, legal_name, default_locale, timezone, currency, lock_version, internal_identifier, plan_reference, is_active, status, provisioning_key, provisioning_payload_hash, created_at, updated_at FROM tenants');
            Schema::drop('tenants');
            DB::unprepared('ALTER TABLE tenants_oq04_rollback RENAME TO tenants');
            Schema::table('tenants', function (Blueprint $table): void {
                $table->index('is_active');
                $table->index('status');
                $table->unique('internal_identifier');
                $table->unique('provisioning_key');
            });
            Schema::enableForeignKeyConstraints();
        } else {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropForeign('tenants_current_subscription_fk');
                $table->dropIndex('tenants_current_subscription_lookup');
                $table->dropIndex('tenants_billing_status_index');
                $table->dropColumn(['current_subscription_id', 'billing_status']);
            });
        }

        Schema::dropIfExists('subscription_billing_records');
        Schema::dropIfExists('subscriptions');

        Schema::dropIfExists('plans');
    }
};
