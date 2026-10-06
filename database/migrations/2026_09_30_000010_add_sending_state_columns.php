<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            // queued_at: claimed by a batch, waiting for a worker. sending_at: SMTP attempt in progress.
            $table->timestamp('queued_at')->nullable()->after('last_attempt_at');
            $table->timestamp('sending_at')->nullable()->after('queued_at');
            $table->index(['campaign_id', 'queued_at']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->text('last_error')->nullable()->after('status');   // safe message shown when a campaign pauses/fails
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->index(['status', 'sent_at']);                      // daily-limit counting
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'queued_at']);
            $table->dropColumn(['queued_at', 'sending_at']);
        });
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn('last_error'));
        Schema::table('email_logs', fn (Blueprint $t) => $t->dropIndex(['status', 'sent_at']));
    }
};
