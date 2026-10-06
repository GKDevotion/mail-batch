<?php

namespace App\Services;

use App\Exceptions\SmtpHostNotAllowedException;
use App\Mail\SmtpTestMail;
use App\Models\SmtpAccount;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * Builds on-demand SMTP mailers and tests them.
 * Passwords are only handled in memory here: never returned, never logged.
 *
 * Config shape: host, port, username, password, encryption (ssl|tls), from_name, from_email.
 */
class SmtpConnectionService
{
    /** @return array<string,mixed> */
    public function configFromAccount(SmtpAccount $account): array
    {
        return [
            'host' => $account->smtp_host,
            'port' => $account->smtp_port,
            'username' => $account->smtp_username,
            'password' => $account->decryptedPassword(),
            'encryption' => $account->encryption->value,
            'from_name' => $account->from_name,
            'from_email' => $account->from_email,
        ];
    }

    /**
     * Saved account (optional) overlaid with submitted form values. Blank password = keep saved one.
     *
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function configFromInput(array $input, ?SmtpAccount $account = null): array
    {
        $cfg = $account ? $this->configFromAccount($account) : [];

        $map = [
            'smtp_host' => 'host', 'smtp_port' => 'port', 'smtp_username' => 'username',
            'smtp_password' => 'password', 'encryption' => 'encryption',
            'from_name' => 'from_name', 'from_email' => 'from_email',
        ];

        foreach ($map as $in => $out) {
            if (isset($input[$in]) && $input[$in] !== '') {
                $cfg[$out] = $input[$in];
            }
        }

        if (isset($cfg['port'])) {
            $cfg['port'] = (int) $cfg['port'];
        }

        return $cfg;
    }

    /** True when the submitted form does not differ from the saved account (so a test result applies to it). */
    public function matchesSaved(SmtpAccount $account, array $input): bool
    {
        if (! empty($input['smtp_password'])) {
            return false;
        }

        $saved = [
            'smtp_host' => $account->smtp_host, 'smtp_port' => $account->smtp_port,
            'smtp_username' => $account->smtp_username, 'encryption' => $account->encryption->value,
            'from_email' => $account->from_email,
        ];

        foreach ($saved as $key => $value) {
            if (isset($input[$key]) && (string) $input[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * On-demand mailer (also used by the sending engine in Phase 6).
     * ssl = implicit TLS (smtps), tls = STARTTLS (smtp). Certificates are verified.
     *
     * @param  array<string,mixed>  $cfg
     *
     * @throws SmtpHostNotAllowedException
     */
    public function mailer(array $cfg): Mailer
    {
        $this->assertHostAllowed((string) $cfg['host']);

        $options = [
            'transport' => 'smtp',
            'scheme' => ($cfg['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp',
            'host' => $cfg['host'],
            'port' => (int) $cfg['port'],
            'username' => $cfg['username'],
            'password' => $cfg['password'],
            'timeout' => (int) config('mailbatch.smtp.timeout_seconds'),
        ];

        $domain = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($domain) && $domain !== '' && $domain !== 'localhost') {
            $options['local_domain'] = $domain; // EHLO name
        }

        return Mail::build($options);
    }

    /**
     * Connection test ($to = null: connect + authenticate only) or full test (also sends a test email).
     *
     * @param  array<string,mixed>  $cfg
     * @return array{ok:bool,message:string}
     */
    public function test(array $cfg, ?string $to = null): array
    {
        try {
            $mailer = $this->mailer($cfg);
            $transport = $mailer->getSymfonyTransport();

            if ($transport instanceof SmtpTransport) {
                $transport->start();   // connect, STARTTLS/SSL, AUTH
            }

            if ($to === null) {
                if ($transport instanceof SmtpTransport) {
                    $transport->stop();
                }

                return ['ok' => true, 'message' => "Connected to {$cfg['host']}:{$cfg['port']} and authenticated successfully."];
            }

            $mailer->to($to)->send(new SmtpTestMail($cfg['from_email'], $cfg['from_name'], $cfg['host']));

            return ['ok' => true, 'message' => "Test email sent to {$to}. Check the inbox (and spam folder)."];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e, $cfg)];
        }
    }

    /**
     * Safe, user-facing message. Raw server text, usernames and passwords are never included.
     * Logs only the error class and category.
     *
     * @param  array<string,mixed>  $cfg
     */
    public function friendlyError(Throwable $e, array $cfg): string
    {
        [$category, $message] = $this->describe($e, $cfg);

        Log::warning('SMTP error', [
            'host' => $cfg['host'] ?? null,
            'port' => $cfg['port'] ?? null,
            'category' => $category,
            'error_type' => $e::class,
        ]);

        return $message;
    }

    /**
     * @param  array<string,mixed>  $cfg
     * @return array{0:string,1:string} [category, message]
     */
    public function describe(Throwable $e, array $cfg): array
    {
        if ($e instanceof SmtpHostNotAllowedException) {
            return ['host_blocked', $e->getMessage()];
        }

        $raw = Str::lower($e->getMessage());
        $where = ($cfg['host'] ?? 'the server').':'.($cfg['port'] ?? '');

        return match (true) {
            str_contains($raw, 'authenticat') || preg_match('/got code "?(535|534|530)"?/', $raw) === 1
                => ['auth', 'Authentication failed. Check the username and password. Some providers need an app-specific password or SMTP access enabled.'],

            str_contains($raw, 'timed out') || str_contains($raw, 'timeout')
                => ['timeout', "The server at {$where} did not respond in time. Check the host and port, and that outgoing SMTP is allowed by your network."],

            str_contains($raw, 'crypto') || str_contains($raw, 'certificate') || str_contains($raw, 'handshake') || str_contains($raw, 'ssl operation')
                => ['tls', 'Secure connection failed. Check the encryption setting (SSL usually means port 465, TLS usually port 587) and that the server certificate is valid.'],

            str_contains($raw, 'connection could not be established') || str_contains($raw, 'connection refused')
                || str_contains($raw, 'getaddrinfo') || str_contains($raw, 'name or service not known')
                || str_contains($raw, 'network is unreachable') || str_contains($raw, 'no route')
                => ['connection', "Could not connect to {$where}. Check the host name, port and encryption."],

            preg_match('/got code "?5\d\d"?/', $raw) === 1 || str_contains($raw, 'relay') || str_contains($raw, 'rejected')
                => ['rejected', 'The server rejected the message. The From address may not be allowed for this account, or the recipient was refused.'],

            default => ['unknown', 'The SMTP server returned an error. Check your settings and try again.'],
        };
    }

    /**
     * SSRF guard: users type the host, so refuse private, loopback, link-local and reserved addresses.
     * Disable only for local development (MAILBATCH_SMTP_ALLOW_PRIVATE_HOSTS=true, e.g. Mailpit).
     *
     * @throws SmtpHostNotAllowedException
     */
    public function assertHostAllowed(string $host): void
    {
        if (config('mailbatch.smtp.allow_private_hosts')) {
            return;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveHost($host);

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new SmtpHostNotAllowedException('This SMTP host is not allowed: private, loopback and internal addresses are blocked.');
            }
        }
    }

    /** @return array<int,string> */
    private function resolveHost(string $host): array
    {
        $ips = @gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }
}
