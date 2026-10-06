<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');          // original Excel row (for export ordering)

            $table->string('name')->nullable();
            $table->string('email')->nullable();            // raw value; may be invalid
            $table->string('website')->nullable();
            $table->string('contact')->nullable();

            // 1 = sent / do-not-send. 0 or NULL = eligible. Empty Excel values are normalised to 0.
            $table->unsignedTinyInteger('status')->nullable()->default(0);
            $table->boolean('is_valid_email')->default(true);
            // already_marked_sent | invalid_email | duplicate | unsubscribed | missing_email
            $table->string('skip_reason', 40)->nullable();

            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();

            // Lower-cased email for the FIRST occurrence only; duplicates keep NULL.
            // The unique index gives database-level duplicate protection per campaign.
            $table->string('dedupe_key')->nullable();

            // Full original Excel row (header => value) so export can preserve every column.
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'dedupe_key']);
            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'sent_at']);
            $table->index(['campaign_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
