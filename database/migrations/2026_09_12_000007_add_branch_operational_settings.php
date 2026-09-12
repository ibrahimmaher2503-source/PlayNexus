<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('code', 30)->nullable()->after('tenant_id');
            $table->text('address_text')->nullable()->after('name');
            $table->string('timezone', 64)->default('Africa/Cairo')->after('address_text');
            $table->unsignedInteger('capacity')->default(1)->after('timezone');
            $table->char('currency', 3)->default('EGP')->after('capacity');
            $table->unsignedInteger('tax_rate_bps')->default(0)->after('currency');
            $table->string('tax_mode', 20)->default('exclusive')->after('tax_rate_bps');
            $table->string('receipt_prefix', 20)->default('PN')->after('tax_mode');
            $table->json('payment_methods')->nullable()->after('receipt_prefix');
            $table->unsignedInteger('lock_version')->default(1)->after('payment_methods');
        });

        $branches = DB::table('branches')
            ->select(['id', 'tenant_id'])
            ->orderBy('id')
            ->get();

        foreach ($branches as $branch) {
            DB::table('branches')
                ->where('id', $branch->id)
                ->where('tenant_id', $branch->tenant_id)
                ->whereNull('code')
                ->update([
                    'code' => 'BR-'.$branch->id,
                    'payment_methods' => json_encode(['cash'], JSON_THROW_ON_ERROR),
                ]);
        }

        Schema::table('branches', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('branch_opening_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'branch_id', 'weekday']);
            $table->foreign(['tenant_id', 'branch_id'])
                ->references(['tenant_id', 'id'])
                ->on('branches')
                ->cascadeOnDelete();
        });

        $now = now('UTC');
        $hours = [];
        foreach ($branches as $branch) {
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $hours[] = [
                    'tenant_id' => $branch->tenant_id,
                    'branch_id' => $branch->id,
                    'weekday' => $weekday,
                    'opens_at' => null,
                    'closes_at' => null,
                    'is_closed' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($hours !== []) {
            DB::table('branch_opening_hours')->insert($hours);
        }
    }

    public function down(): void
    {
        // This migration is forward-only because the added settings are data-bearing.
    }
};
