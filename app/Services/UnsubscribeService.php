<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Unsubscribe;
use Illuminate\Support\Str;

/**
 * Stateless signed unsubscribe links: {user}.{campaign}.{base64url(email)}.{hmac}
 * Nothing is stored until the recipient confirms.
 */
class UnsubscribeService
{
    public function token(int $userId, int $campaignId, string $email): string
    {
        $b64 = rtrim(strtr(base64_encode(Str::lower($email)), '+/', '-_'), '=');

        return "{$userId}.{$campaignId}.{$b64}.".$this->signature($userId, $campaignId, $b64);
    }

    public function url(Campaign $campaign, string $email): string
    {
        return route('unsubscribe.show', ['token' => $this->token($campaign->user_id, $campaign->id, $email)]);
    }

    /** @return array{user_id:int, campaign_id:int, email:string}|null */
    public function parse(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 4 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            return null;
        }

        [$uid, $cid, $b64, $sig] = $parts;

        if (! hash_equals($this->signature((int) $uid, (int) $cid, $b64), $sig)) {
            return null;
        }

        $email = base64_decode(strtr($b64, '-_', '+/'), true);

        if ($email === false || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return ['user_id' => (int) $uid, 'campaign_id' => (int) $cid, 'email' => $email];
    }

    public function isUnsubscribed(int $userId, string $email): bool
    {
        return Unsubscribe::query()
            ->where('user_id', $userId)
            ->where('email', Str::lower($email))
            ->whereNotNull('unsubscribed_at')
            ->exists();
    }

    /** Record the opt-out and skip every unsent row for that address across the sender's campaigns. */
    public function confirm(array $data): void
    {
        $email = Str::lower($data['email']);

        Unsubscribe::updateOrCreate(
            ['user_id' => $data['user_id'], 'email' => $email],
            ['campaign_id' => Campaign::whereKey($data['campaign_id'])->value('id'), 'token' => Str::random(40), 'unsubscribed_at' => now()]
        );

        CampaignRecipient::query()
            ->whereIn('campaign_id', Campaign::where('user_id', $data['user_id'])->select('id'))
            ->where('email', $email)
            ->whereNull('sent_at')
            ->whereNull('skip_reason')
            ->update(['skip_reason' => 'unsubscribed']);
    }

    private function signature(int $userId, int $campaignId, string $b64): string
    {
        return substr(hash_hmac('sha256', "{$userId}.{$campaignId}.{$b64}", (string) config('app.key')), 0, 32);
    }
}
