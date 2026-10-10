<?php

namespace App\Modules\ActivityLog\Support;

/** Tiny user-agent reader: enough for an audit trail, no dependency. */
class UserAgent
{
    /** @return array{browser:string,os:string,device:string} */
    public static function parse(string $ua): array
    {
        if ($ua === '') {
            return ['browser' => '', 'os' => '', 'device' => ''];
        }

        $browser = match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/Firefox|FxiOS/i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/i', $ua) => 'Chrome',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            (bool) preg_match('/curl|wget|python|postman|insomnia/i', $ua) => 'API client',
            default => 'Other',
        };

        $os = match (true) {
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/Linux|X11/i', $ua) => 'Linux',
            default => 'Other',
        };

        $device = match (true) {
            (bool) preg_match('/iPad|Tablet/i', $ua) => 'Tablet',
            (bool) preg_match('/Mobile|iPhone|Android/i', $ua) => 'Mobile',
            default => 'Desktop',
        };

        return ['browser' => $browser, 'os' => $os, 'device' => $device];
    }
}
