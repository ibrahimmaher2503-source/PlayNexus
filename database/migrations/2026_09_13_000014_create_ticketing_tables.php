<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'branch_id', 'id'], 'pricing_rules_tenant_branch_id_unique');
        });

        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('pricing_rule_id');
            $table->string('code', 50);
            $table->string('name', 190);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('EGP');
            $table->unsignedSmallInteger('max_uses')->default(1);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'branch_id', 'id'], 'ticket_types_tenant_branch_id_unique');
            $table->unique(['tenant_id', 'branch_id', 'code']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->foreign(['tenant_id', 'branch_id', 'pricing_rule_id'], 'ticket_types_rule_fk')
                ->references(['tenant_id', 'branch_id', 'id'])
                ->on('pricing_rules')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by_user_id'])
                ->references(['tenant_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedBigInteger('guardian_id');
            $table->unsignedBigInteger('child_id');
            $table->date('service_date');
            $table->string('status', 20)->default('issued');
            $table->char('code_hash', 64);
            $table->text('code_payload_encrypted');
            $table->string('display_code', 20);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('EGP');
            $table->json('price_snapshot_json');
            $table->dateTime('issued_at');
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->dateTime('assignment_locked_at')->nullable();
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->unsignedSmallInteger('uses_count')->default(0);
            $table->unsignedSmallInteger('max_uses')->default(1);
            $table->uuid('idempotency_key');
            $table->char('issue_fingerprint', 64);
            $table->unsignedBigInteger('issued_by_user_id');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code_hash']);
            $table->unique(['tenant_id', 'display_code']);
            $table->unique(['tenant_id', 'issued_by_user_id', 'idempotency_key'], 'tickets_issue_idempotency_unique');
            $table->index(['tenant_id', 'branch_id', 'service_date', 'status'], 'tickets_branch_day_status_idx');
            $table->index(['tenant_id', 'child_id', 'status']);
            $table->foreign(['tenant_id', 'branch_id', 'ticket_type_id'], 'tickets_type_fk')
                ->references(['tenant_id', 'branch_id', 'id'])
                ->on('ticket_types')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'guardian_id'])
                ->references(['tenant_id', 'id'])
                ->on('guardians')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'child_id'])
                ->references(['tenant_id', 'id'])
                ->on('children')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'issued_by_user_id'])
                ->references(['tenant_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'cancelled_by_user_id'])
                ->references(['tenant_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::create('ticket_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('scanned_by_user_id');
            $table->dateTime('scanned_at');
            $table->string('scan_purpose', 20)->default('validate');
            $table->string('result', 30);
            $table->char('code_hash', 64);
            $table->uuid('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->uuid('request_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'scanned_by_user_id', 'idempotency_key'], 'ticket_scans_idempotency_unique');
            $table->index(['tenant_id', 'ticket_id', 'scanned_at']);
            $table->index(['tenant_id', 'branch_id', 'scanned_at']);
            $table->index(['tenant_id', 'result', 'scanned_at']);
            $table->foreign(['tenant_id', 'ticket_id'])
                ->references(['tenant_id', 'id'])
                ->on('tickets')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])
                ->references(['tenant_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'scanned_by_user_id'])
                ->references(['tenant_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_scans');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_types');

        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->dropUnique('pricing_rules_tenant_branch_id_unique');
        });
    }
};
