<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\UnsubscribeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UnsubscribeController extends Controller
{
    public function __construct(private readonly UnsubscribeService $unsubscribe)
    {
    }

    public function show(string $token): View
    {
        $data = $this->unsubscribe->parse($token) ?? abort(404);

        return view('unsubscribe.show', [
            'token' => $token,
            'email' => $this->mask($data['email']),
            'sender' => $this->sender($data['campaign_id']),
            'already' => $this->unsubscribe->isUnsubscribed($data['user_id'], $data['email']),
        ]);
    }

    public function store(Request $request, string $token): View
    {
        $data = $this->unsubscribe->parse($token) ?? abort(404);

        $this->unsubscribe->confirm($data);

        return view('unsubscribe.done', ['sender' => $this->sender($data['campaign_id'])]);
    }

    private function sender(int $campaignId): string
    {
        return Campaign::with('smtpAccount')->find($campaignId)?->smtpAccount?->from_name ?? config('mailbatch.name');
    }

    private function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2) + [1 => ''];

        return Str::substr($local, 0, 1).str_repeat('*', max(2, Str::length($local) - 1)).'@'.$domain;
    }
}
