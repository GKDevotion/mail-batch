<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Template variables, safe rendering and HTML sanitising.
 *
 * Syntax:  {{name}}   {{ website }}   {{name|there}}  (text after | = fallback when the value is empty)
 *
 * Safety:
 *  - HTML body: variable values are HTML-escaped, then the result is sanitised again (script, event handlers,
 *    javascript: links, <style> etc. are removed).
 *  - Subject: values inserted raw but CR/LF/control characters are stripped (header-injection protection).
 *  - Plain text: values inserted raw, control characters stripped.
 */
class EmailTemplateService
{
    private const VAR_REGEX = '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*(?:\|([^{}]*))?\}\}/';

    /** Always available (empty when the column is not mapped, so fallbacks like {{name|there}} work). */
    private const CORE = ['name' => 'Recipient name', 'email' => 'Recipient email', 'website' => 'Website', 'contact' => 'Contact'];

    /** Available when mapped. */
    private const OPTIONAL = ['status' => 'Status', 'date' => 'Date', 'reply' => 'Reply', 'note' => 'Note'];

    private ?HtmlSanitizer $sanitizer = null;

    // ------------------------------------------------------------------ variables

    /**
     * @return array<string, array{label:string, header:?string, builtin:bool}>
     */
    public function variableList(Campaign $campaign): array
    {
        $mapping = $campaign->column_mapping ?? [];
        $list = [];

        foreach (self::CORE as $key => $label) {
            $list[$key] = ['label' => $label, 'header' => $mapping[$key] ?? null, 'builtin' => false];
        }

        foreach (self::OPTIONAL as $key => $label) {
            if (isset($mapping[$key])) {
                $list[$key] = ['label' => $label, 'header' => $mapping[$key], 'builtin' => false];
            }
        }

        // Every Excel column is usable by its slug: "Company Name" => {{company_name}}
        foreach ($this->headerSlugs($campaign->excel_headers ?? []) as $header => $slug) {
            $list[$slug] ??= ['label' => $header, 'header' => $header, 'builtin' => false];
        }

        $list['website_url'] = ['label' => 'Website as a link (adds https://)', 'header' => null, 'builtin' => true];
        $list['sender_name'] = ['label' => 'Sender name', 'header' => null, 'builtin' => true];
        $list['sender_email'] = ['label' => 'Sender email', 'header' => null, 'builtin' => true];
        $list['unsubscribe_url'] = ['label' => 'Unsubscribe link', 'header' => null, 'builtin' => true];

        return $list;
    }

    /** @return array<int,string> lower-cased variable names used in the given templates */
    public function extractVariables(string ...$templates): array
    {
        $found = [];
        foreach ($templates as $tpl) {
            if (preg_match_all(self::VAR_REGEX, $tpl, $m)) {
                foreach ($m[1] as $name) {
                    $found[Str::lower($name)] = true;
                }
            }
        }

        return array_keys($found);
    }

    /** @return array<int,string> */
    public function unknownVariables(Campaign $campaign, string ...$templates): array
    {
        return array_values(array_diff($this->extractVariables(...$templates), array_keys($this->variableList($campaign))));
    }

    /** @return array<string,string> */
    public function recipientVariables(Campaign $campaign, CampaignRecipient $recipient): array
    {
        $row = $recipient->metadata ?? [];
        $vars = [];

        foreach ($this->variableList($campaign) as $name => $info) {
            if (! $info['builtin']) {
                $vars[$name] = $info['header'] !== null ? (string) ($row[$info['header']] ?? '') : '';
            }
        }

        foreach (['name', 'website', 'contact'] as $core) {
            $value = (string) ($recipient->{$core} ?? '');
            if ($value !== '') {
                $vars[$core] = $value;
            }
        }

        $vars['email'] = (string) $recipient->email;
        $vars['website_url'] = $this->normalizeUrl($vars['website'] ?? '');
        $vars['sender_name'] = (string) ($campaign->smtpAccount?->from_name ?? '');
        $vars['sender_email'] = (string) ($campaign->smtpAccount?->from_email ?? '');

        return $vars;
    }

    /** Real recipient values, or "[name]" style placeholders when there is no recipient. */
    public function sampleVariables(Campaign $campaign, ?CampaignRecipient $recipient): array
    {
        if ($recipient) {
            return $this->recipientVariables($campaign, $recipient);
        }

        $vars = [];
        foreach (array_keys($this->variableList($campaign)) as $name) {
            $vars[$name] = '['.$name.']';
        }
        $vars['website_url'] = 'https://example.com';
        $vars['sender_name'] = (string) ($campaign->smtpAccount?->from_name ?? '[sender_name]');
        $vars['sender_email'] = (string) ($campaign->smtpAccount?->from_email ?? '[sender_email]');

        return $vars;
    }

    // ------------------------------------------------------------------ rendering

    /**
     * @param  array<string,string>  $vars
     * @return array{subject:string, html:string, text:string}
     */
    public function render(Campaign $campaign, array $vars, string $subject, string $html, ?string $text, ?string $unsubscribeUrl): array
    {
        $unsubscribe = $campaign->shouldIncludeUnsubscribe() ? $unsubscribeUrl : null;
        $vars['unsubscribe_url'] = $unsubscribe ?? '';
        $linkInBody = in_array('unsubscribe_url', $this->extractVariables($html, (string) $text), true);

        $subjectOut = $this->cleanHeader($this->substitute($subject, $vars, fn (string $v) => $v));
        $body = $this->sanitizeHtml($this->substitute($html, $vars, fn (string $v) => e($v)));

        $textBody = filled($text)
            ? $this->cleanText($this->substitute($text, $vars, fn (string $v) => $v))
            : $this->htmlToText($body);

        // Footer: sender identification + unsubscribe link (unless the author placed {{unsubscribe_url}} themselves)
        $htmlParts = [];
        $textParts = [];

        if (filled($campaign->sender_identification)) {
            $htmlParts[] = nl2br(e($campaign->sender_identification));
            $textParts[] = $this->cleanText($campaign->sender_identification);
        }

        if ($unsubscribe && ! $linkInBody) {
            $htmlParts[] = '<a href="'.e($unsubscribe).'" style="color:#777777;">Unsubscribe</a>';
            $textParts[] = 'Unsubscribe: '.$unsubscribe;
        }

        $footer = $htmlParts === [] ? '' :
            '<div style="margin-top:24px;padding-top:12px;border-top:1px solid #dddddd;font-size:12px;color:#777777;line-height:1.5;">'
            .implode('<br>', $htmlParts).'</div>';

        $document = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            .'<body style="margin:0;padding:16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222222;line-height:1.5;">'
            .$body.$footer.'</body></html>';

        $textOut = $textParts === [] ? $textBody : rtrim($textBody)."\n\n-- \n".implode("\n", $textParts);

        return ['subject' => $subjectOut, 'html' => $document, 'text' => $textOut];
    }

    /** Remove everything unsafe. Relative links stay allowed so href="{{website_url}}" survives; schemes are allow-listed. */
    public function sanitizeHtml(string $html): string
    {
        $this->sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeMedias(false)
                ->allowAttribute('style', '*')
                ->allowAttribute('align', '*')
                ->allowAttribute('valign', '*')
                ->allowAttribute('bgcolor', ['table', 'tr', 'td', 'th'])
                ->allowAttribute('width', ['img', 'table', 'td', 'th'])
                ->allowAttribute('height', ['img', 'td', 'th'])
                ->allowAttribute('border', ['table', 'img'])
                ->allowAttribute('cellpadding', ['table'])
                ->allowAttribute('cellspacing', ['table'])
                ->allowAttribute('colspan', ['td', 'th'])
                ->allowAttribute('rowspan', ['td', 'th'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(max(200000, (int) config('mailbatch.template.max_html_length') * 2))
        );

        return $this->filterStyles($this->sanitizer->sanitize($html));
    }

    public function htmlToText(string $html): string
    {
        $t = preg_replace('#<li\b[^>]*>#i', "\n- ", $html) ?? $html;
        $t = preg_replace('#<(br|/p|/div|/h[1-6]|/li|/tr|/table)\b[^>]*>#i', "\n", $t) ?? $t;
        $t = preg_replace_callback('#<a\b[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is', function ($m) {
            $label = trim(strip_tags($m[2]));
            $href = html_entity_decode($m[1]);

            return ($label === '' || $label === $href) ? $href : $label.' ('.$href.')';
        }, $t) ?? $t;
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t]+\n/", "\n", $t) ?? $t;
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;

        return trim($this->cleanText($t));
    }

    // ------------------------------------------------------------------ internals

    /** @param array<string,string> $vars */
    private function substitute(string $template, array $vars, callable $escape): string
    {
        return preg_replace_callback(self::VAR_REGEX, function (array $m) use ($vars, $escape) {
            $value = (string) ($vars[Str::lower($m[1])] ?? '');

            if (trim($value) === '' && isset($m[2])) {
                $value = trim($m[2]);
            }

            return $escape($value);
        }, $template) ?? $template;
    }

    /** @return array<string,string> header => slug (unique) */
    private function headerSlugs(array $headers): array
    {
        $out = [];
        $used = [];

        foreach (array_values($headers) as $i => $header) {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii((string) $header))) ?? '', '_');

            if ($slug === '') {
                $slug = 'column_'.($i + 1);
            }
            if (ctype_digit($slug[0])) {
                $slug = 'c_'.$slug;
            }

            $base = $slug;
            for ($n = 2; isset($used[$slug]); $n++) {
                $slug = $base.'_'.$n;
            }

            $used[$slug] = true;
            $out[(string) $header] = $slug;
        }

        return $out;
    }

    private function normalizeUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $value)) {
            if (str_contains($value, '://') || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value)) {
                return ''; // other schemes (javascript:, data:, ...) are never linked
            }
            $value = 'https://'.$value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    private function cleanHeader(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F\x{2028}\x{2029}]+/u', ' ', $value) ?? $value;

        return Str::limit(trim($value), 255, '');
    }

    private function cleanText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? $value;
    }

    /** Drop style attributes that contain legacy script-execution tricks. */
    private function filterStyles(string $html): string
    {
        return preg_replace_callback('/\sstyle="([^"]*)"/i', function (array $m) {
            return preg_match('/expression\s*\(|javascript:|vbscript:|behavior\s*:|-moz-binding|@import/i', html_entity_decode($m[1]))
                ? '' : $m[0];
        }, $html) ?? $html;
    }
}
