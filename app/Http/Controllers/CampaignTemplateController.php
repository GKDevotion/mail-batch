<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Http\Requests\PreviewTemplateRequest;
use App\Http\Requests\SaveTemplateRequest;
use App\Http\Requests\TestEmailRequest;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use App\Services\EmailTemplateService;
use App\Services\SmtpConnectionService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Throwable;

class CampaignTemplateController extends Controller
{
    private const DEFAULT_SUBJECT = 'Quick question for {{name|your team}}';

    private const DEFAULT_BODY = "<p>Hello {{name|there}},</p>\n<p>We visited your website {{website}} and would like to discuss our services.</p>\n<p>Regards,<br>{{sender_name}}</p>";

    public function __construct(private readonly EmailTemplateService $templates)
    {
    }

    public function edit(Request $request, Campaign $campaign): View|RedirectResponse
    {
        $this->authorize('view', $campaign);

        if (empty($campaign->column_mapping['email'] ?? null)) {
            return redirect()->route('campaigns.mapping', $campaign)->with('error', 'Upload your Excel file and map the columns first.');
        }

        $campaign->load('smtpAccount');

        return view('campaigns.compose', [
            'campaign' => $campaign,
            'canEdit' => $request->user()->can('configure', $campaign),
            'variables' => $this->templates->variableList($campaign),
            'samples' => $campaign->recipients()->eligible()->orderBy('row_number')->limit(10)->get(['id', 'row_number', 'name', 'email']),
            'subject' => old('subject', $campaign->subject ?? self::DEFAULT_SUBJECT),
            'bodyHtml' => old('body_html', $campaign->body_html ?? self::DEFAULT_BODY),
            'bodyText' => old('body_text', $campaign->body_text),
        ]);
    }

    public function update(SaveTemplateRequest $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validated();
        $html = $this->templates->sanitizeHtml($data['body_html']);

        if (trim(strip_tags($html)) === '' && ! str_contains($html, '<img')) {
            return back()->withInput()->withErrors(['body_html' => 'The body is empty after unsafe content (scripts, styles, event handlers) was removed.']);
        }

        $campaign->update([
            'subject' => $data['subject'],
            'body_html' => $html,
            'body_text' => $data['body_text'] ?? null,
        ]);

        if ($campaign->status === CampaignStatus::Draft && $campaign->isConfigured()) {
            $campaign->update(['status' => CampaignStatus::Ready]);
        }

        $next = Route::has('campaigns.confirm') ? 'campaigns.confirm' : 'campaigns.show';

        return redirect()->route($next, $campaign)->with('status', 'Email template saved.');
    }

    /** AJAX live preview (nothing is saved or sent). */
    public function preview(PreviewTemplateRequest $request, Campaign $campaign): JsonResponse
    {
        $campaign->load('smtpAccount');

        $subject = (string) $request->input('subject', '');
        $html = (string) $request->input('body_html', '');
        $text = $request->input('body_text');

        $rendered = $this->templates->render(
            $campaign,
            $this->templates->sampleVariables($campaign, $this->sampleRecipient($campaign, $request->integer('recipient_id') ?: null)),
            $subject, $html, $text, '#'
        );

        return response()->json([
            'subject' => $rendered['subject'],
            'html' => $rendered['html'],
            'text' => $rendered['text'],
            'unknown' => $this->templates->unknownVariables($campaign, $subject, $html, (string) $text),
            'auto_text' => $this->templates->htmlToText($this->templates->sanitizeHtml($html)),
        ]);
    }

    /** AJAX: send the current (even unsaved) template to a test address through the campaign's SMTP account. */
    public function sendTest(TestEmailRequest $request, Campaign $campaign, SmtpConnectionService $smtp): JsonResponse
    {
        $campaign->load('smtpAccount');
        $account = $campaign->smtpAccount;

        if (! $account || ! $account->isActive()) {
            return response()->json(['ok' => false, 'message' => 'Choose an SMTP account for this campaign first (SMTP step).']);
        }

        $data = $request->validated();
        $to = $data['test_email'];

        $rendered = $this->templates->render(
            $campaign,
            $this->templates->sampleVariables($campaign, $this->sampleRecipient($campaign, $request->integer('recipient_id') ?: null)),
            $data['subject'], $data['body_html'], $data['body_text'] ?? null,
            rtrim((string) config('app.url'), '/').'/'
        );

        $subject = '[TEST] '.$rendered['subject'];

        try {
            $cfg = $smtp->configFromAccount($account);
        } catch (DecryptException) {
            return response()->json(['ok' => false, 'message' => 'The saved SMTP password could not be read. Re-enter it on the SMTP account and try again.']);
        }

        try {
            $smtp->mailer($cfg)->to($to)->send(new CampaignMail(
                $subject, $rendered['html'], $rendered['text'], $account->from_email, $account->from_name,
            ));

            EmailLog::create([
                'campaign_id' => $campaign->id, 'recipient_id' => null, 'email' => $to, 'subject' => $subject,
                'status' => 'test', 'sent_at' => now(),
            ]);

            return response()->json(['ok' => true, 'message' => "Test email sent to {$to}. Check the inbox (and spam folder)."]);
        } catch (Throwable $e) {
            $message = $smtp->friendlyError($e, $cfg);

            EmailLog::create([
                'campaign_id' => $campaign->id, 'recipient_id' => null, 'email' => $to, 'subject' => $subject,
                'status' => 'test', 'error_message' => $message,
            ]);

            return response()->json(['ok' => false, 'message' => $message]);
        }
    }

    private function sampleRecipient(Campaign $campaign, ?int $id): ?CampaignRecipient
    {
        $query = $campaign->recipients()->orderBy('row_number');

        if ($id) {
            return (clone $query)->whereKey($id)->first();
        }

        return (clone $query)->eligible()->first() ?? $query->first();
    }
}
