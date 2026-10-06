<?php

namespace Tests\Unit;

use App\Exceptions\SmtpHostNotAllowedException;
use App\Services\SmtpConnectionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;

class SmtpErrorDescribeTest extends TestCase
{
    private function category(\Throwable $e): string
    {
        return (new SmtpConnectionService())->describe($e, ['host' => 'smtp.example.com', 'port' => 587])[0];
    }

    public function test_classifies_errors_without_leaking_raw_text(): void
    {
        $auth = new TransportException('Failed to authenticate on SMTP server with username "bob@x.com" using "LOGIN". Expected response code "235" but got code "535"');
        $svc = new SmtpConnectionService();
        [$cat, $msg] = $svc->describe($auth, ['host' => 'smtp.example.com', 'port' => 587]);

        $this->assertSame('auth', $cat);
        $this->assertStringNotContainsString('bob@x.com', $msg);

        $this->assertSame('connection', $this->category(new TransportException('Connection could not be established with host "smtp.example.com:587": Connection refused')));
        $this->assertSame('timeout', $this->category(new TransportException('Connection to "smtp.example.com:587" timed out.')));
        $this->assertSame('tls', $this->category(new TransportException('Unable to complete TLS handshake: ssl operation failed')));
        $this->assertSame('rejected', $this->category(new TransportException('Expected response code "250" but got code "554", with message "554 5.7.1 Relay access denied"')));
        $this->assertSame('unknown', $this->category(new \RuntimeException('something odd')));
        $this->assertSame('host_blocked', $this->category(new SmtpHostNotAllowedException('blocked')));
    }
}
