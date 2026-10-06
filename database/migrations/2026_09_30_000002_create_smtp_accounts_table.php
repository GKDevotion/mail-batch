<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port');
            $table->string('smtp_username');
            // Laravel Crypt payload (APP_KEY). Never returned to the frontend.
            $table->text('smtp_password_encrypted');
            $table->string('encryption', 10);            // ssl | tls
            $table->string('from_name');
            $table->string('from_email');
            $table->string('status', 20)->default('active')->index(); // active | disabled
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_accounts');
    }
};
