<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Services\SmtpConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmtpAccountTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Main', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'encryption' => 'tls',
            'smtp_username' => 'user@example.com', 'smtp_password' => 'Sup3r-Secret-Pass',
            'from_name' => 'Acme Ltd', 'from_email' => 'hello@example.com',
        ], $override);
    }

    public function test_password_is_encrypted_hidden_and_not_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('smtp-accounts.store'), $this->payload())->assertRedirect(route('smtp-accounts.index'));

        $account = SmtpAccount::firstOrFail();
        $raw = DB::table('smtp_accounts')->where('id', $account->id)->value('smtp_password_encrypted');

        $this->assertStringNotContainsString('Sup3r-Secret-Pass', $raw);
        $this->assertSame('Sup3r-Secret-Pass', $account->decryptedPassword());
        $this->assertArrayNotHasKey('smtp_password_encrypted', $account->toArray());

        $this->actingAs($user)->get(route('smtp-accounts.edit', $account))
            ->assertOk()->assertDontSee('Sup3r-Secret-Pass')->assertDontSee($raw, false);
    }

    public function test_blank_password_on_update_keeps_the_saved_one(): void
    {
        $account = SmtpAccount::factory()->create(['smtp_password_encrypted' => 'original-pass']);

        $this->actingAs($account->user)->put(route('smtp-accounts.update', $account), $this->payload([
            'smtp_password' => '', 'name' => 'Renamed',
        ]))->assertRedirect(route('smtp-accounts.index'));

        $account->refresh();
        $this->assertSame('Renamed', $account->name);
        $this->assertSame('original-pass', $account->decryptedPassword());
    }

    public function test_private_and_loopback_hosts_are_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['127.0.0.1', '10.0.0.5', '169.254.169.254', 'localhost'] as $host) {
            $this->actingAs($user)->post(route('smtp-accounts.store'), $this->payload(['smtp_host' => $host]))
                ->assertSessionHasErrors('smtp_host');
        }

        $this->assertSame(0, SmtpAccount::count());
    }

    public function test_validation_rules(): void
    {
        $this->actingAs(User::factory()->create())->post(route('smtp-accounts.store'), $this->payload([
            'smtp_port' => 70000, 'encryption' => 'none', 'from_email' => 'nope', 'smtp_password' => '',
        ]))->assertSessionHasErrors(['smtp_port', 'encryption', 'from_email', 'smtp_password']);
    }

    public function test_other_users_cannot_edit_or_delete_an_account(): void
    {
        $account = SmtpAccount::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('smtp-accounts.edit', $account))->assertForbidden();
        $this->actingAs($intruder)->put(route('smtp-accounts.update', $account), $this->payload())->assertForbidden();
        $this->actingAs($intruder)->delete(route('smtp-accounts.destroy', $account))->assertForbidden();
    }

    public function test_campaign_accepts_own_account_only(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();
        $own = SmtpAccount::factory()->for($user)->create();
        $foreign = SmtpAccount::factory()->create();

        $this->actingAs($user)->post(route('campaigns.smtp.update', $campaign), ['smtp_account_id' => $foreign->id])
            ->assertSessionHasErrors('smtp_account_id');

        $this->actingAs($user)->post(route('campaigns.smtp.update', $campaign), ['smtp_account_id' => $own->id])->assertRedirect();
        $this->assertSame($own->id, $campaign->fresh()->smtp_account_id);
    }

    public function test_campaign_step_can_create_and_attach_a_new_account(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();

        $this->actingAs($user)->post(route('campaigns.smtp.update', $campaign), $this->payload())->assertRedirect();

        $this->assertSame(1, $user->smtpAccounts()->count());
        $this->assertNotNull($campaign->fresh()->smtp_account_id);
    }

    public function test_test_endpoint_returns_only_status_and_message(): void
    {
        $account = SmtpAccount::factory()->create(['smtp_password_encrypted' => 'very-secret-pw']);

        $this->partialMock(SmtpConnectionService::class, function ($mock) {
            $mock->shouldReceive('test')->once()->andReturn(['ok' => true, 'message' => 'Connected.']);
        });

        $response = $this->actingAs($account->user)->postJson(route('smtp-accounts.test'), [
            'mode' => 'connection', 'smtp_account_id' => $account->id,
        ])->assertOk()->assertExactJson(['ok' => true, 'message' => 'Connected.']);

        $this->assertStringNotContainsString('very-secret-pw', $response->getContent());
        $this->assertTrue($account->fresh()->last_test_ok);
    }

    public function test_test_endpoint_validates_input_and_blocks_internal_hosts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('smtp-accounts.test'), ['mode' => 'email'])->assertStatus(422);

        $this->actingAs($user)->postJson(route('smtp-accounts.test'), $this->payload([
            'mode' => 'connection', 'smtp_host' => '127.0.0.1',
        ]))->assertStatus(422)->assertJsonValidationErrors('smtp_host');
    }

    public function test_test_endpoint_rejects_another_users_account_id(): void
    {
        $account = SmtpAccount::factory()->create();

        $this->actingAs(User::factory()->create())->postJson(route('smtp-accounts.test'), [
            'mode' => 'connection', 'smtp_account_id' => $account->id,
        ])->assertStatus(422)->assertJsonValidationErrors('smtp_account_id');
    }
}
