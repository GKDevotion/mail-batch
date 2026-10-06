<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('smtp_account_id')->nullable()->constrained('smtp_accounts')->nullOnDelete();

            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();

            // Original upload is stored privately and never modified.
            $table->string('excel_filename');
            $table->string('excel_path')->nullable();
            $table->json('excel_headers')->nullable();
            // {"name":"Name","email":"Email","website":"Website","contact":"Contact","status":"Status", ...}
            $table->json('column_mapping')->nullable();

            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('valid_records')->default(0);
            $table->unsignedInteger('invalid_records')->default(0);
            $table->unsignedInteger('duplicate_records')->default(0);
            $table->unsignedInteger('eligible_records')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);

            $table->unsignedSmallInteger('batch_size')->default(50);

            // Optional outreach compliance helpers
            $table->boolean('include_unsubscribe')->default(true);
            $table->text('sender_identification')->nullable();

            $table->string('status', 20)->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
