<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Unsubscribe;
use App\Services\UnsubscribeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_round_trip_and_tampering(): void
    {
        $svc = app(UnsubscribeService::class);
        $token = $svc->token(5, 9, 'Person@Example.com');

        $this->assertSame(['user_id' => 5, 'campaign_id' => 9, 'email' => 'person@example.com'], $svc->parse($token));
        $this->assertNull($svc->parse(substr($token, 0, -1).'0'));
        $this->assertNull($svc->parse('5.9.'.base64_encode('other@example.com').'.'.substr($token, -32)));
        $this->assertNull($svc->parse('garbage'));
    }

    public function test_confirming_unsubscribes_and_skips_unsent_rows(): void
    {
        $c = Campaign::factory()->create();
        $other = Campaign::factory()->for($c->user)->create();
        $pending = CampaignRecipient::factory()->for($c)->create(['email' => 'gone@example.com']);
        $inOther = CampaignRecipient::factory()->for($other)->create(['email' => 'gone@example.com']);
        $sent = CampaignRecipient::factory()->for($c)->sent()->create(['email' => 'gone@example.com', 'dedupe_key' => null]);
        $token = app(UnsubscribeService::class)->token($c->user_id, $c->id, 'gone@example.com');

        $this->get(route('unsubscribe.show', $token))->assertOk()->assertSee('Yes, unsubscribe me')->assertDontSee('gone@example.com');
        $this->post(route('unsubscribe.store', $token))->assertOk()->assertSee('You are unsubscribed');

        $this->assertDatabaseHas('unsubscribes', ['user_id' => $c->user_id, 'email' => 'gone@example.com']);
        $this->assertNotNull(Unsubscribe::first()->unsubscribed_at);
        $this->assertSame('unsubscribed', $pending->fresh()->skip_reason);
        $this->assertSame('unsubscribed', $inOther->fresh()->skip_reason);
        $this->assertNull($sent->fresh()->skip_reason);   // already delivered rows are untouched
    }

    public function test_invalid_tokens_return_404(): void
    {
        $this->get('/unsubscribe/not.a.valid.token')->assertNotFound();
        $this->post('/unsubscribe/not.a.valid.token')->assertNotFound();
    }
}
