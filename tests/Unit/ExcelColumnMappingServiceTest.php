<?php

namespace Tests\Unit;

use App\Services\ExcelColumnMappingService;
use PHPUnit\Framework\TestCase;

class ExcelColumnMappingServiceTest extends TestCase
{
    public function test_status_rules(): void
    {
        $s = new ExcelColumnMappingService();

        $this->assertSame([0, null], $s->parseStatus(''));
        $this->assertSame([0, null], $s->parseStatus('0'));
        $this->assertSame([1, null], $s->parseStatus('1'));
        $this->assertSame([1, null], $s->parseStatus(' 1.0 '));
        $this->assertSame([0, 'unknown_status'], $s->parseStatus('sent'));
    }

    public function test_email_parsing(): void
    {
        $s = new ExcelColumnMappingService();

        $this->assertSame(['info@example.com', null], $s->parseEmail('  Info@Example.com '));
        $this->assertSame(['info@example.com', null], $s->parseEmail('mailto:info@example.com'));
        $this->assertSame([null, 'missing_email'], $s->parseEmail('   '));
        $this->assertSame([null, 'invalid_email'], $s->parseEmail('not-an-email'));
        $this->assertSame([null, 'invalid_email'], $s->parseEmail('a@x.com; b@y.com'));
    }

    public function test_suggests_mapping_from_headers(): void
    {
        $s = new ExcelColumnMappingService();
        $map = $s->suggest(['Company Name', 'Website', 'E-mail', 'Phone', 'Status', 'Notes']);

        $this->assertSame('Company Name', $map['name']);
        $this->assertSame('E-mail', $map['email']);
        $this->assertSame('Phone', $map['contact']);
        $this->assertSame('Status', $map['status']);
        $this->assertSame('Notes', $map['note']);
    }
}
