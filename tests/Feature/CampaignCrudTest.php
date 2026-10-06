<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/campaigns')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_create_a_campaign(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/campaigns', [
            'name' => 'October outreach', 'batch_size' => 50, 'include_unsubscribe' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'user_id' => $user->id, 'name' => 'October outreach', 'status' => 'draft',
        ]);
    }

    public function test_batch_size_cannot_exceed_the_limit(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/campaigns', ['name' => 'Too big', 'batch_size' => 51])
            ->assertSessionHasErrors('batch_size');
    }

    public function test_user_cannot_view_or_delete_another_users_campaign(): void
    {
        $owner = Campaign::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get("/campaigns/{$owner->id}")->assertForbidden();
        $this->actingAs($intruder)->delete("/campaigns/{$owner->id}")->assertForbidden();
    }

    public function test_owner_can_delete_a_campaign(): void
    {
        $campaign = Campaign::factory()->create();

        $this->actingAs($campaign->user)->delete("/campaigns/{$campaign->id}")
            ->assertRedirect('/campaigns');

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }

    public function test_disabled_users_are_signed_out(): void
    {
        $this->actingAs(User::factory()->disabled()->create())
            ->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_stats_endpoint_returns_json_totals(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure(['total_campaigns', 'total_recipients', 'sent', 'failed', 'pending', 'skipped']);
    }
}
