<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\SkipReason;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\DailySendCount;
use App\Models\EmailLog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends ONE recipient's email with database-level duplicate protection.
 *
 * Order of events (this is what makes duplicates very unlikely):
 *  1. row lock; refuse if already sent / skipped / invalid / retry limit / unsubscribed / another attempt running
 *  2. mark sending_at BEFORE contacting SMTP
 *  3. send
 *  4. record the outcome (status=1 + sent_at on success; error on failure)
 * If a worker dies between 3 and 4, the row keeps sending_at and is later marked as failed with
 * "result unknown". It is never re-sent automatically: the user decides after checking the mailbox.
 */
class EmailSendingService
{
    public const INTERRUPTED = 'Delivery result unknown: sending was interrupted. Check your sent mailbox before retrying.';

    /** Problems with the SMTP account/connection, not with one recipient: pause the campaign instead of failing rows. */
    private const SYSTEMIC = ['auth', 'connection', 'timeout', 'tls', 'host_blocked'];

    public function __construct(
        private readonly SmtpConnectionService $smtp,
        private readonly EmailTemplateService $templates,
        private readonly UnsubscribeService $unsubscribe,
    ) {
    }

    /**
     * @return array{outcome:string, message:?string}  outcome: sent|failed|skipped|systemic|cancelled|busy
     */
    public function send(Campaign $campaign, CampaignRecipient $recipient): array
    {
        // value() would return the CAST attribute (an enum), so read the model and compare enums.
        $current = Campaign::query()->whereKey($campaign->id)->first(['id', 'status'])?->status;

        if ($current !== CampaignStatus::Processing) {
            CampaignRecipient::query()->whereKey($recipient->id)->whereNull('sending_at')->update(['queued_at' => null]);

            return $this->result('cancelled');
        }

        [$state, $row] = DB::transaction(function () use ($campaign, $recipient): array {
            $row = CampaignRecipient::query()->whereKey($recipient->id)->lockForUpdate()->first();

            if (! $row) {
                return ['busy', null];
            }

            if ($row->sending_at !== null) {
                return ['busy', $row];                       // another worker is on it
            }

            if ($row->alreadySent() || $row->skip_reason !== null || ! $row->is_valid_email
                || $row->retry_count >= (int) config('mailbatch.max_retries')) {
                $row->update(['queued_at' => null]);

                return ['skipped', $row];
            }

            if ($this->unsubscribe->isUnsubscribed($campaign->user_id, (string) $row->email)) {
                $row->update(['skip_reason' => SkipReason::Unsubscribed->value, 'queued_at' => null]);

                return ['skipped', $row];
            }

            $row->update(['sending_at' => now(), 'last_attempt_at' => now()]);

            return ['go', $row];
        });

        if ($state !== 'go') {
            return $this->result($state);
        }

        $cfg = [];
        $subject = null;

        try {
            $account = $campaign->smtpAccount;
            $cfg = $this->smtp->configFromAccount($account);

            $unsubscribeUrl = $campaign->shouldIncludeUnsubscribe() ? $this->unsubscribe->url($campaign, (string) $row->email) : null;

            $rendered = $this->templates->render(
                $campaign,
                $this->templates->recipientVariables($campaign, $row),
                (string) $campaign->subject, (string) $campaign->body_html, $campaign->body_text, $unsubscribeUrl
            );
            $subject = $rendered['subject'];

            $sent = $this->smtp->mailer($cfg)->to($row->email)->send(new CampaignMail(
                $rendered['subject'], $rendered['html'], $rendered['text'],
                $account->from_email, $account->from_name, $unsubscribeUrl
            ));

            DB::transaction(function () use ($campaign, $row, $subject, $sent) {
                $row->forceFill([
                    'status' => 1, 'sent_at' => now(), 'error_message' => null, 'sending_at' => null, 'queued_at' => null,
                ])->save();

                EmailLog::create([
                    'campaign_id' => $campaign->id, 'recipient_id' => $row->id, 'email' => $row->email, 'subject' => $subject,
                    'status' => 'sent', 'response' => 'Accepted by SMTP server', 'sent_at' => now(),
                ]);
            });

            DailySendCount::bump((int) $campaign->user_id);

            return $this->result('sent');
        } catch (DecryptException) {
            return $this->fail($campaign, $row, $subject, 'The saved SMTP password could not be read. Re-enter it on the SMTP account.', 'config', true, 'DecryptException');
        } catch (Throwable $e) {
            [$category, $message] = $this->smtp->describe($e, $cfg);

            return $this->fail($campaign, $row, $subject, $message, $category, in_array($category, self::SYSTEMIC, true), $e::class);
        }
    }

    private function fail(Campaign $campaign, CampaignRecipient $row, ?string $subject, string $message, string $category, bool $systemic, string $errorType): array
    {
        Log::warning('Email send failed', [
            'campaign_id' => $campaign->id, 'recipient_id' => $row->id, 'email' => $row->email,
            'error_type' => $errorType, 'category' => $category,
        ]);

        DB::transaction(function () use ($campaign, $row, $subject, $message, $systemic) {
            if ($systemic) {
                // Not this recipient's fault: stays pending, no retry counted, the campaign pauses.
                $row->forceFill(['sending_at' => null, 'queued_at' => null])->save();
            } else {
                $row->forceFill([
                    'error_message' => $message, 'retry_count' => $row->retry_count + 1,
                    'sending_at' => null, 'queued_at' => null,
                ])->save();                                  // status stays 0, sent_at stays NULL
            }

            EmailLog::create([
                'campaign_id' => $campaign->id, 'recipient_id' => $row->id, 'email' => $row->email, 'subject' => $subject,
                'status' => 'failed', 'error_message' => $message,
            ]);
        });

        return $this->result($systemic ? 'systemic' : 'failed', $message);
    }

    private function result(string $outcome, ?string $message = null): array
    {
        return ['outcome' => $outcome, 'message' => $message];
    }
}
