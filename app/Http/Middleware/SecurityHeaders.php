<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every web response.
 * CSP mode (MAILBATCH_CSP): enforce (default) | report (Report-Only, for testing) | off.
 * Inline scripts must carry the per-request nonce: nonce="{{ request()->attributes->get('csp_nonce') }}".
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure() && app()->isProduction()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $mode = (string) config('mailbatch.security.csp', 'enforce');
        if ($mode === 'enforce') {
            $h->set('Content-Security-Policy', $this->policy($nonce));
        } elseif ($mode === 'report') {
            $h->set('Content-Security-Policy-Report-Only', $this->policy($nonce));
        }

        // Signed-in pages (recipient data, exports) must not be kept by browser/proxy caches.
        if ($request->user()) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://code.jquery.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "font-src 'self' https://cdn.jsdelivr.net data:",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
