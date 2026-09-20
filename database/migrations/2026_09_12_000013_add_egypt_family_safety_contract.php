<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('guardians')
            ->select(['tenant_id', 'phone_e164'])
            ->groupBy('tenant_id', 'phone_e164')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Resolve duplicate tenant guardian phones before applying the Egypt M2 contract.');
        }

        Schema::table('guardians', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'phone_e164']);
            $table->unique(['tenant_id', 'phone_e164']);
        });

        Schema::table('children', function (Blueprint $table): void {
            $table->string('emergency_contact_name', 190)->nullable()->after('date_of_birth');
            $table->string('emergency_contact_phone_e164', 20)->nullable()->after('emergency_contact_name');
            $table->text('safety_notes_encrypted')->nullable()->after('emergency_contact_phone_e164');
        });

        Schema::table('guardian_child', function (Blueprint $table): void {
            $table->boolean('can_consent')->default(false)->after('relationship_type');
            $table->boolean('can_check_out')->default(false)->after('can_consent');
            $table->boolean('is_primary')->default(false)->after('can_check_out');
            $table->string('verification_method', 40)->nullable()->after('is_primary');
            $table->timestamp('verified_at')->nullable()->after('verification_method');
            $table->unsignedBigInteger('verified_by_user_id')->nullable()->after('verified_at');
            $table->timestamp('revoked_at')->nullable()->after('verified_by_user_id');
            $table->unsignedBigInteger('revoked_by_user_id')->nullable()->after('revoked_at');

            $table->foreign(['tenant_id', 'verified_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'revoked_by_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::table('guardian_child')->where('relationship_type', 'parent')->update(['relationship_type' => 'legal_guardian']);
        DB::table('guardian_child')
            ->whereIn('relationship_type', ['mother', 'father', 'legal_guardian'])
            ->update(['can_consent' => true, 'can_check_out' => true]);

        Schema::create('family_consent_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id');
            $table->foreignId('child_id');
            $table->string('consent_type', 30);
            $table->string('status', 20);
            $table->string('notice_version', 80);
            $table->text('purpose_snapshot');
            $table->text('data_categories_snapshot');
            $table->string('locale', 2);
            $table->string('method', 20);
            $table->unsignedBigInteger('actor_user_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->uuid('request_id');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'request_id', 'consent_type']);
            $table->index(['tenant_id', 'child_id', 'consent_type', 'occurred_at'], 'family_consent_child_type_time_idx');
            $table->foreign(['tenant_id', 'guardian_id'])
                ->references(['tenant_id', 'id'])->on('guardians')->restrictOnDelete();
            $table->foreign(['tenant_id', 'child_id'])
                ->references(['tenant_id', 'id'])->on('children')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_user_id'])
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])
                ->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_consent_events');

        Schema::table('guardian_child', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'verified_by_user_id']);
            $table->dropForeign(['tenant_id', 'revoked_by_user_id']);
            $table->dropColumn([
                'can_consent',
                'can_check_out',
                'is_primary',
                'verification_method',
                'verified_at',
                'verified_by_user_id',
                'revoked_at',
                'revoked_by_user_id',
            ]);
        });

        Schema::table('children', function (Blueprint $table): void {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone_e164', 'safety_notes_encrypted']);
        });

        Schema::table('guardians', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'phone_e164']);
            $table->index(['tenant_id', 'phone_e164']);
        });
    }
};
