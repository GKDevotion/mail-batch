<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Services\SmtpConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(array $attrs = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'include_unsubscribe' => false,
            'excel_headers' => ['Company Name', 'Website', 'Email', 'Contact', 'Status'],
            'column_mapping' => ['name' => 'Company Name', 'email' => 'Email', 'website' => 'Website', 'contact' => 'Contact', 'status' => 'Status'],
        ], $attrs));
    }

    private function svc(): EmailTemplateService
    {
        return app(EmailTemplateService::class);
    }

    public function test_sanitizer_removes_unsafe_markup_but_keeps_formatting(): void
    {
        $clean = $this->svc()->sanitizeHtml(
            '<script>alert(1)</script><p style="color:red" onclick="x()">Hi <a href="javascript:alert(1)">bad</a> '
            .'<a href="https://example.com">ok</a></p><img src="x" onerror="alert(1)"><table><tr><td align="center">c</td></tr></table>'
        );

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('style="color:red"', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('<table>', $clean);
    }

    public function test_render_escapes_values_and_cleans_subject(): void
    {
        $out = $this->svc()->render(
            $this->campaign(),
            ['name' => '<script>alert(1)</script>', 'website' => 'javascript:alert(1)'],
            "Hi {{name}}\r\nBcc: evil@example.com",
            '<p>Hello {{name}}</p><a href="{{website}}">site</a>',
            null, null
        );

        $this->assertStringNotContainsString('<script>', $out['html']);
        $this->assertStringContainsString('&lt;script&gt;', $out['html']);
        $this->assertStringNotContainsString('javascript:', $out['html']);
        $this->assertStringNotContainsString("\n", $out['subject']);
        $this->assertStringNotContainsString("\r", $out['subject']);
    }

    public function test_fallback_text_and_auto_plain_text(): void
    {
        $out = $this->svc()->render($this->campaign(), ['name' => ''], 'Hi {{name|there}}', '<p>Hello {{name|friend}}</p><p>Bye</p>', null, null);

        $this->assertSame('Hi there', $out['subject']);
        $this->assertStringContainsString('Hello friend', $out['text']);
        $this->assertStringNotContainsString('<p>', $out['text']);
    }

    public function test_footer_has_sender_identification_and_unsubscribe(): void
    {
        $c = $this->campaign(['include_unsubscribe' => true, 'sender_identification' => "Acme Ltd\n1 Main St"]);
        $out = $this->svc()->render($c, [], 'S', '<p>Hi</p>', null, 'https://app.test/unsubscribe/abc');

        $this->assertStringContainsString('Acme Ltd', $out['html']);
        $this->assertStringContainsString('https://app.test/unsubscribe/abc', $out['html']);
        $this->assertStringContainsString('Unsubscribe: https://app.test/unsubscribe/abc', $out['text']);
    }

    public function test_variables_follow_mapping_and_headers(): void
    {
        $c = $this->campaign();
        $vars = array_keys($this->svc()->variableList($c));

        foreach (['name', 'email', 'website', 'contact', 'status', 'company_name', 'website_url', 'sender_name', 'unsubscribe_url'] as $v) {
            $this->assertContains($v, $vars);
        }

        $this->assertSame(['foo'], $this->svc()->unknownVariables($c, 'Hi {{name}} {{ FOO }} {{company_name|x}}'));
    }

    public function test_recipient_variables_and_website_url(): void
    {
        $c = $this->campaign();
        $r = CampaignRecipient::factory()->for($c)->create([
            'name' => 'Acme', 'email' => 'a@example.com', 'website' => 'acme.com',
            'metadata' => ['Company Name' => 'Acme', 'Website' => 'acme.com', 'Email' => 'a@example.com'],
        ]);

        $vars = $this->svc()->recipientVariables($c, $r);
        $this->assertSame('Acme', $vars['company_name']);
        $this->assertSame('https://acme.com', $vars['website_url']);

        $r->update(['website' => 'javascript:alert(1)']);
        $this->assertSame('', $this->svc()->recipientVariables($c, $r->fresh())['website_url']);
    }

    public function test_save_sanitises_blocks_unknown_variables_and_marks_ready(): void
    {
        $user = User::factory()->create();
        $account = SmtpAccount::factory()->for($user)->create();
        $c = $this->campaign(['user_id' => $user->id, 'smtp_account_id' => $account->id]);

        $this->actingAs($user)->post(route('campaigns.compose.update', $c), [
            'subject' => 'Hi {{nope}}', 'body_html' => '<p>x</p>',
        ])->assertSessionHasErrors('body_html');

        $this->actingAs($user)->post(route('campaigns.compose.update', $c), [
            'subject' => 'Hi {{name|there}}', 'body_html' => '<p>Hello</p><script>alert(1)</script>',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $c->refresh();
        $this->assertStringNotContainsString('<script', $c->body_html);
        $this->assertSame(CampaignStatus::Ready, $c->status);
    }

    public function test_subject_must_be_single_line_and_body_required(): void
    {
        $user = User::factory()->create();
        $c = $this->campaign(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('campaigns.compose.update', $c), ['subject' => "a\nb", 'body_html' => ''])
            ->assertSessionHasErrors(['subject', 'body_html']);
    }

    public function test_preview_renders_sample_recipient_without_saving(): void
    {
        $user = User::factory()->create();
        $c = $this->campaign(['user_id' => $user->id, 'subject' => null, 'body_html' => null]);
        CampaignRecipient::factory()->for($c)->create(['name' => 'Acme', 'metadata' => ['Company Name' => 'Acme']]);

        $this->actingAs($user)->postJson(route('campaigns.compose.preview', $c), [
            'subject' => 'Hello {{name}}', 'body_html' => '<p>Hi {{name}} {{zzz}}</p>',
        ])->assertOk()->assertJsonPath('subject', 'Hello Acme')->assertJsonPath('unknown', ['zzz']);

        $this->assertNull($c->fresh()->subject);
    }

    public function test_compose_requires_mapping(): void
    {
        $user = User::factory()->create();
        $c = Campaign::factory()->create(['user_id' => $user->id, 'column_mapping' => null]);

        $this->actingAs($user)->get(route('campaigns.compose', $c))->assertRedirect(route('campaigns.mapping', $c));
    }

    public function test_test_email_is_sent_through_the_campaign_account_and_logged(): void
    {
        $user = User::factory()->create();
        $account = SmtpAccount::factory()->for($user)->create();
        $c = $this->campaign(['user_id' => $user->id, 'smtp_account_id' => $account->id]);
        CampaignRecipient::factory()->for($c)->create(['name' => 'Acme']);

        $array = app('mail.manager')->mailer('array');
        $this->partialMock(SmtpConnectionService::class, fn ($m) => $m->shouldReceive('mailer')->once()->andReturn($array));

        $this->actingAs($user)->postJson(route('campaigns.test-email', $c), [
            'subject' => 'Hello {{name}}', 'body_html' => '<p>Hi {{name}}</p>', 'test_email' => 'me@example.com',
        ])->assertOk()->assertJsonPath('ok', true);

        $messages = $array->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('[TEST] Hello Acme', $messages[0]->getOriginalMessage()->getSubject());
        $this->assertDatabaseHas('email_logs', ['campaign_id' => $c->id, 'status' => 'test', 'email' => 'me@example.com']);
    }

    public function test_test_email_needs_an_smtp_account(): void
    {
        $user = User::factory()->create();
        $c = $this->campaign(['user_id' => $user->id, 'smtp_account_id' => null]);

        $this->actingAs($user)->postJson(route('campaigns.test-email', $c), [
            'subject' => 'S', 'body_html' => '<p>x</p>', 'test_email' => 'me@example.com',
        ])->assertOk()->assertJsonPath('ok', false);
    }

    public function test_other_users_are_forbidden(): void
    {
        $c = $this->campaign();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('campaigns.compose', $c))->assertForbidden();
        $this->actingAs($intruder)->postJson(route('campaigns.compose.preview', $c), [])->assertForbidden();
        $this->actingAs($intruder)->postJson(route('campaigns.test-email', $c), [])->assertForbidden();
        $this->actingAs($intruder)->post(route('campaigns.compose.update', $c), [])->assertForbidden();
    }
}
