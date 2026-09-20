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
            $table->string('legal_name', 190)->nullable()->after('name');
            $table->string('default_locale', 2)->default('en')->after('legal_name');
            $table->string('timezone', 64)->default('Africa/Cairo')->after('default_locale');
            $table->char('currency', 3)->default('EGP')->after('timezone');
            $table->unsignedInteger('lock_version')->default(1)->after('currency');
        });

        DB::table('tenants')->whereNull('legal_name')->update(['legal_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['legal_name', 'default_locale', 'timezone', 'currency', 'lock_version']);
        });
    }
};
