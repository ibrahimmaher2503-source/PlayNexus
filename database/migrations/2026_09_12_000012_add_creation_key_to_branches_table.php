<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->uuid('creation_key')->nullable()->after('tenant_id');
            $table->unique(['tenant_id', 'creation_key']);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'creation_key']);
            $table->dropColumn('creation_key');
        });
    }
};
