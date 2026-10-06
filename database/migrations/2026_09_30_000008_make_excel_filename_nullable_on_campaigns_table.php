<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A campaign is created first; the Excel file is attached in Phase 3.
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('excel_filename')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('excel_filename')->nullable(false)->change();
        });
    }
};
