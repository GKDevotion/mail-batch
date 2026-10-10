<?php

namespace Tests\Feature\Modules;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Support\UserAgent;
use App\Support\Modules\Events\ActivityRecorded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogModuleTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function test_user_agent_parsing(): void
    {
        $this->assertSame(['browser' => 'Chrome', 'os' => 'Windows', 'device' => 'Desktop'], UserAgent::parse(self::CHROME_WINDOWS));
        $this->assertSame(['browser' => 'Safari', 'os' => 'iOS', 'device' => 'Mobile'], UserAgent::parse('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Edge', UserAgent::parse(self::CHROME_WINDOWS.' Edg/120.0')['browser']);
    }

    public function test_sign_in_is_logged_with_ip_and_device(): void
    {
        $user = User::factory()->create();

        $this->withHeader('User-Agent', self::CHROME_WINDOWS)->post('/login', ['email' => $user->email, 'password' => 'password']);

        $log = ActivityLog::where('action', 'auth.login')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('auth', $log->module);
        $this->assertSame('Chrome', $log->browser);
        $this->assertSame('Windows', $log->os);
        $this->assertSame('Desktop', $log->device);
        $this->assertNotNull($log->ip);
    }

    public function test_failed_sign_in_keeps_the_email_but_never_the_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Wr0ng-Secret-Pass']);

        $log = ActivityLog::where('action', 'auth.failed')->firstOrFail();
        $this->assertSame($user->email, $log->properties['email']);
        $this->assertStringNotContainsString('Wr0ng-Secret-Pass', ActivityLog::all()->toJson());
    }

    public function test_campaign_changes_are_logged(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Launch']);
        $this->assertTrue(ActivityLog::where('action', 'campaign.created')->where('subject_id', $campaign->id)->exists());

        $campaign->update(['status' => CampaignStatus::Paused]);

        $log = ActivityLog::where('action', 'campaign.status_changed')->firstOrFail();
        $this->assertSame(['from' => 'draft', 'to' => 'paused'], $log->properties);
        $this->assertStringContainsString('Launch', $log->description);

        $campaign->update(['subject' => 'New subject']);
        $this->assertTrue(ActivityLog::where('action', 'campaign.template_updated')->exists());

        $campaign->update(['batch_size' => 10]);   // not interesting: no new entry
        $this->assertSame(1, ActivityLog::where('action', 'campaign.template_updated')->count());
    }

    public function test_user_changes_never_expose_passwords(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        $user->update(['name' => 'Renamed']);
        $user->update(['password' => 'N3w-Password-Value']);

        $this->assertSame(['name'], ActivityLog::where('action', 'user.updated')->first()->properties['fields']);
        $this->assertTrue(ActivityLog::where('action', 'user.password_changed')->exists());

        $json = ActivityLog::all()->toJson();
        $this->assertStringNotContainsString($hash, $json);
        $this->assertStringNotContainsString('N3w-Password-Value', $json);
    }

    public function test_smtp_credentials_are_never_logged(): void
    {
        $account = SmtpAccount::factory()->create(['smtp_password_encrypted' => 'secret-password-one']);
        $account->update(['smtp_password_encrypted' => 'secret-password-two', 'smtp_host' => 'mail.example.org']);

        $log = ActivityLog::where('action', 'smtp.updated')->firstOrFail();
        $this->assertTrue($log->properties['credentials_changed']);
        $this->assertSame(['smtp_host'], $log->properties['fields']);

        $json = ActivityLog::all()->toJson();
        $this->assertStringNotContainsString('secret-password-one', $json);
        $this->assertStringNotContainsString('secret-password-two', $json);

        $account->update(['last_test_ok' => true]);
        $this->assertTrue(ActivityLog::where('action', 'smtp.tested')->exists());
    }

    public function test_other_modules_can_log_through_an_event(): void
    {
        ActivityRecorded::record('contacts.imported', 'Imported 5,000 contacts', null, ['rows' => 5000, 'api_token' => 'abc123']);

        $log = ActivityLog::where('action', 'contacts.imported')->firstOrFail();
        $this->assertSame('contacts', $log->module);
        $this->assertSame(5000, $log->properties['rows']);
        $this->assertSame('[hidden]', $log->properties['api_token']);
    }

    public function test_role_changes_are_logged(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.access.roles.store'), ['name' => 'Auditors', 'permissions' => ['activity.view']]);

        $this->assertTrue(ActivityLog::where('action', 'access.role_created')->exists());
        $this->assertSame(['activity.view'], ActivityLog::where('action', 'access.permissions_changed')->firstOrFail()->properties['added']);
    }

    public function test_page_is_for_people_with_the_permission_and_filters_work(): void
    {
        ActivityRecorded::record('contacts.imported', 'Imported alpha list');
        ActivityRecorded::record('campaign.created', 'Created beta campaign');

        $manager = User::factory()->create(['role_id' => Role::idForKey('manager')]);

        $this->actingAs(User::factory()->create())->get(route('admin.activity.index'))->assertForbidden();

        $this->actingAs($manager)->get(route('admin.activity.index'))->assertOk()->assertSee('Imported alpha list')->assertSee('Created beta campaign');
        $this->actingAs($manager)->get(route('admin.activity.index', ['module' => 'contacts']))->assertSee('Imported alpha list')->assertDontSee('Created beta campaign');
        $this->actingAs($manager)->get(route('admin.activity.index', ['q' => 'beta']))->assertSee('Created beta campaign')->assertDontSee('Imported alpha list');
        $this->actingAs($manager)->get(route('admin.activity.index', ['from' => 'not-a-date']))->assertSessionHasErrors('from');
    }

    public function test_dashboard_widget_is_permission_gated(): void
    {
        ActivityRecorded::record('contacts.imported', 'Widget visible entry');

        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertOk()->assertSee('Recent activity')->assertSee('Widget visible entry');
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertDontSee('Widget visible entry');
    }

    public function test_prune_deletes_old_entries(): void
    {
        $old = ActivityLog::create(['module' => 'auth', 'action' => 'auth.login', 'description' => 'old']);
        $old->forceFill(['created_at' => now()->subDays(400)])->save();
        $recent = ActivityLog::create(['module' => 'auth', 'action' => 'auth.login', 'description' => 'recent']);

        $this->artisan('activitylog:prune')->assertSuccessful();

        $this->assertDatabaseMissing('activity_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $recent->id]);
    }
}
