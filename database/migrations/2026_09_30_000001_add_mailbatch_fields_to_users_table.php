<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('password')->index();
            $table->boolean('is_active')->default(true)->after('role');
            // NULL = use system default (config mailbatch.default_daily_limit / system setting)
            $table->unsignedInteger('daily_send_limit')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'daily_send_limit']);
        });
    }
};
