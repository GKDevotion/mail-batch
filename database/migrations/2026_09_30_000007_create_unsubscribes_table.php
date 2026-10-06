<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the sender the recipient opted out of
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unsubscribes');
    }
};
