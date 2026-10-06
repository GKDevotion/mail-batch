<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestSmtpRequest;
use App\Services\SmtpConnectionService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;

class SmtpTestController extends Controller
{
    public function __invoke(TestSmtpRequest $request, SmtpConnectionService $smtp): JsonResponse
    {
        $data = $request->validated();
        $account = filled($data['smtp_account_id'] ?? null)
            ? $request->user()->smtpAccounts()->find($data['smtp_account_id'])
            : null;

        try {
            $cfg = $smtp->configFromInput($data, $account);
        } catch (DecryptException) {
            return response()->json([
                'ok' => false,
                'message' => 'The saved password could not be read. Re-enter the password and save the account again.',
            ]);
        }

        foreach (['host', 'port', 'username', 'password', 'encryption', 'from_name', 'from_email'] as $key) {
            if (empty($cfg[$key])) {
                return response()->json(['ok' => false, 'message' => 'Please fill in all SMTP fields first.']);
            }
        }

        $result = $smtp->test($cfg, $data['mode'] === 'email' ? $data['test_email'] : null);

        if ($account && $smtp->matchesSaved($account, $data)) {
            $account->update(['last_tested_at' => now(), 'last_test_ok' => $result['ok']]);
        }

        // Only a status and a sanitised message ever leave the server.
        return response()->json(['ok' => $result['ok'], 'message' => $result['message']]);
    }
}
