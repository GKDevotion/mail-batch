<?php

namespace App\Services;

use Illuminate\Support\Str;

class ExcelColumnMappingService
{
    /**
     * Mappable fields. Every Excel column is additionally usable as a template variable (Phase 5),
     * so optional fields like date/reply/note only matter if you want fixed names for them.
     *
     * @return array<string, array{label:string, required:bool, hint:string, aliases:array<int,string>}>
     */
    public function fields(): array
    {
        return [
            'name' => ['label' => 'Recipient Name', 'required' => false, 'hint' => 'Used for {{name}}',
                'aliases' => ['name', 'companyname', 'company', 'recipient', 'recipientname', 'business', 'businessname', 'fullname']],
            'email' => ['label' => 'Recipient Email', 'required' => true, 'hint' => 'Required',
                'aliases' => ['email', 'emailaddress', 'emailid', 'mail', 'email1', 'recipientemail']],
            'website' => ['label' => 'Website', 'required' => false, 'hint' => 'Used for {{website}}',
                'aliases' => ['website', 'site', 'url', 'web', 'websiteurl', 'domain']],
            'contact' => ['label' => 'Contact', 'required' => false, 'hint' => 'Used for {{contact}}',
                'aliases' => ['contact', 'phone', 'mobile', 'tel', 'telephone', 'contactnumber', 'phonenumber']],
            'status' => ['label' => 'Status', 'required' => false, 'hint' => '1 = do not send; 0 or empty = send',
                'aliases' => ['status', 'sent', 'emailstatus', 'mailstatus']],
            'date' => ['label' => 'Date (optional)', 'required' => false, 'hint' => 'Used for {{date}}',
                'aliases' => ['date', 'createdat', 'datesent']],
            'reply' => ['label' => 'Reply (optional)', 'required' => false, 'hint' => 'Used for {{reply}}',
                'aliases' => ['reply', 'response', 'replied']],
            'note' => ['label' => 'Note (optional)', 'required' => false, 'hint' => 'Used for {{note}}',
                'aliases' => ['note', 'notes', 'remark', 'remarks', 'comment', 'comments']],
        ];
    }

    /**
     * Guess a mapping from header names. Each header is used at most once.
     *
     * @param  array<int,string>  $headers
     * @return array<string,string>
     */
    public function suggest(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $h) {
            $normalized[$h] = preg_replace('/[^a-z0-9]/', '', Str::lower($h));
        }

        $result = [];
        $used = [];

        foreach ($this->fields() as $key => $field) {
            foreach ($normalized as $header => $norm) {
                if (! isset($used[$header]) && in_array($norm, $field['aliases'], true)) {
                    $result[$key] = $header;
                    $used[$header] = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Keep only known fields pointing at real headers.
     *
     * @param  array<string,mixed>  $input
     * @param  array<int,string>  $headers
     * @return array<string,string>
     */
    public function clean(array $input, array $headers): array
    {
        $clean = [];
        foreach (array_keys($this->fields()) as $key) {
            $value = $input[$key] ?? null;
            if (is_string($value) && $value !== '' && in_array($value, $headers, true)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string,string>  $row      header => value
     * @param  array<string,string>  $mapping  field => header
     * @return array<string,string>
     */
    public function mapRow(array $row, array $mapping): array
    {
        $out = [];
        foreach (array_keys($this->fields()) as $key) {
            $header = $mapping[$key] ?? null;
            $out[$key] = $header !== null ? (string) ($row[$header] ?? '') : '';
        }

        return $out;
    }

    /** @return array{0:?string,1:?string} [normalised email, problem (SkipReason value)] */
    public function parseEmail(string $raw): array
    {
        $email = trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $raw) ?? '');
        $email = preg_replace('/^mailto:/i', '', $email) ?? $email;
        $email = trim($email, " \t\n\r\0\x0B<>");

        if ($email === '') {
            return [null, 'missing_email'];
        }

        if (strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [null, 'invalid_email'];
        }

        return [Str::lower($email), null];
    }

    /**
     * Status rules: 1 = do NOT send. 0, empty, NULL = send.
     * Anything else is ambiguous and is skipped (never sent) rather than guessed.
     *
     * @return array{0:int,1:?string} [status, problem]
     */
    public function parseStatus(string $raw): array
    {
        $v = trim($raw);

        return match (true) {
            $v === '', $v === '0', $v === '0.0' => [0, null],
            $v === '1', $v === '1.0' => [1, null],
            default => [0, 'unknown_status'],
        };
    }
}
