<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('excel_preview')->nullable()->after('excel_headers');   // first rows, for the preview page
            $table->unsignedInteger('excel_size')->nullable()->after('excel_path'); // bytes
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['excel_preview', 'excel_size']);
        });
    }
};
