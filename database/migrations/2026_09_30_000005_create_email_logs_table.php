<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            // NULL for test emails
            $table->foreignId('recipient_id')->nullable()->constrained('campaign_recipients')->nullOnDelete();
            $table->string('email');
            $table->string('subject')->nullable();
            $table->string('status', 20);                   // sent | failed | test
            $table->text('error_message')->nullable();       // sanitised, never credentials
            $table->text('response')->nullable();            // sanitised server response
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
