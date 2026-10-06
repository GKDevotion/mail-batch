<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Permanent per-user, per-day counter. Unlike email_logs it survives campaign deletion,
    // so deleting a campaign can never reset (bypass) the daily sending limit.
    public function up(): void
    {
        Schema::create('daily_send_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('sent')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_send_counts');
    }
};
