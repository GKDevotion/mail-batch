<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use App\Models\SmtpAccount;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_the_admin_area(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect('/login');   // guest first: actingAs() persists for the rest of the test

        $user = User::factory()->create();

        foreach (['admin.dashboard', 'admin.users.index', 'admin.smtp.index', 'admin.campaigns.index', 'admin.logs.index', 'admin.logs.failed', 'admin.settings.edit'] as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()->assertSee('Administration');
    }

    public function test_every_admin_page_renders(): void
    {
        $admin = User::factory()->admin()->create();
        $c = Campaign::factory()->create();
        SmtpAccount::factory()->create();
        EmailLog::create(['campaign_id' => $c->id, 'email' => 'x@example.com', 'status' => 'failed', 'error_message' => 'Boom']);
        CampaignRecipient::factory()->for($c)->failed()->create();

        foreach ([route('admin.users.index'), route('admin.smtp.index'), route('admin.campaigns.index'), route('admin.campaigns.show', $c),
                  route('admin.logs.index'), route('admin.logs.failed'), route('admin.settings.edit')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_creates_a_user_with_a_hashed_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person', 'email' => 'new@example.com', 'password' => 'Str0ngPassw0rd', 'role' => 'user',
            'is_active' => 1, 'daily_send_limit' => 40,
        ])->assertRedirect();

        $u = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Str0ngPassw0rd', $u->password));
        $this->assertFalse($u->isAdmin());
        $this->assertSame(40, $u->daily_send_limit);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Dup', 'email' => 'new@example.com', 'password' => 'Str0ngPassw0rd', 'role' => 'user', 'is_active' => 1,
        ])->assertSessionHasErrors('email');
    }

    public function test_disabling_a_user_pauses_their_running_campaigns_and_sets_limits(): void
    {
        $admin = User::factory()->admin()->create();
        $u = User::factory()->create();
        $c = Campaign::factory()->for($u)->create(['status' => CampaignStatus::Processing]);

        $this->actingAs($admin)->put(route('admin.users.update', $u), ['role' => 'user', 'is_active' => 0, 'daily_send_limit' => 25])->assertRedirect();

        $u->refresh();
        $this->assertFalse($u->is_active);
        $this->assertSame(25, $u->daily_send_limit);
        $this->assertSame(CampaignStatus::Paused, $c->fresh()->status);

        // blank limit = back to the system default
        $this->actingAs($admin)->put(route('admin.users.update', $u), ['role' => 'user', 'is_active' => 1, 'daily_send_limit' => ''])->assertRedirect();
        $this->assertNull($u->fresh()->daily_send_limit);
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), ['role' => 'user', 'is_active' => 1])->assertSessionHas('error');
        $this->actingAs($admin)->put(route('admin.users.update', $admin), ['role' => 'admin', 'is_active' => 0])->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');

        $admin->refresh();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
    }

    public function test_deleting_a_user_removes_their_data_unless_a_campaign_is_sending(): void
    {
        $admin = User::factory()->admin()->create();
        $u = User::factory()->create();
        $c = Campaign::factory()->for($u)->create(['status' => CampaignStatus::Processing]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $u))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $u->id]);

        $c->update(['status' => CampaignStatus::Paused]);
        $this->actingAs($admin)->delete(route('admin.users.destroy', $u))->assertSessionHas('status');
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
        $this->assertDatabaseMissing('campaigns', ['id' => $c->id]);
    }

    public function test_smtp_toggle_pauses_running_campaigns_and_hides_passwords(): void
    {
        $admin = User::factory()->admin()->create();
        $account = SmtpAccount::factory()->create(['smtp_password_encrypted' => 'super-secret-pw']);
        $c = Campaign::factory()->create(['smtp_account_id' => $account->id, 'status' => CampaignStatus::Processing]);

        $this->actingAs($admin)->get(route('admin.smtp.index'))->assertOk()->assertDontSee('super-secret-pw');

        $this->actingAs($admin)->post(route('admin.smtp.toggle', $account))->assertRedirect();
        $this->assertSame('disabled', $account->fresh()->status);
        $this->assertSame(CampaignStatus::Paused, $c->fresh()->status);

        $this->actingAs($admin)->post(route('admin.smtp.toggle', $account))->assertRedirect();
        $this->assertSame('active', $account->fresh()->status);
    }

    public function test_admin_can_pause_any_campaign(): void
    {
        $admin = User::factory()->admin()->create();
        $c = Campaign::factory()->create(['status' => CampaignStatus::Processing]);

        $this->actingAs($admin)->post(route('admin.campaigns.pause', $c))->assertRedirect();

        $this->assertSame(CampaignStatus::Paused, $c->fresh()->status);
    }

    public function test_settings_are_validated_stored_and_applied(): void
    {
        $admin = User::factory()->admin()->create();
        $hard = (int) config('mailbatch.hard_max_batch_size');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'max_batch_size' => $hard + 1, 'default_daily_limit' => 100, 'max_retries' => 3,
        ])->assertSessionHasErrors('max_batch_size');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'max_batch_size' => 20, 'default_daily_limit' => 111, 'max_retries' => 2, 'force_unsubscribe' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('20', SystemSetting::get('max_batch_size'));
        $this->assertSame(20, config('mailbatch.max_batch_size'));
        $this->assertSame(111, config('mailbatch.default_daily_limit'));
        $this->assertSame(2, config('mailbatch.max_retries'));
        $this->assertTrue(config('mailbatch.unsubscribe.force'));
        $this->assertSame(111, User::factory()->create()->effectiveDailyLimit());
    }

    public function test_user_can_change_their_own_password(): void
    {
        $user = User::factory()->create();   // password: "password"

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'wrong', 'password' => 'NewPassw0rd!x', 'password_confirmation' => 'NewPassw0rd!x',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'password', 'password' => 'NewPassw0rd!x', 'password_confirmation' => 'NewPassw0rd!x',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPassw0rd!x', $user->fresh()->password));
    }
}
