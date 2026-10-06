<?php

namespace App\Http\Requests;

use App\Exceptions\SmtpHostNotAllowedException;
use App\Services\SmtpConnectionService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmtpAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('smtp_account');

        return $account ? $this->user()->can('update', $account) : $this->user()->can('create', \App\Models\SmtpAccount::class);
    }

    public function rules(): array
    {
        return self::fieldRules(passwordRequired: $this->isMethod('POST'));
    }

    /** @return array<string, array<int, mixed>> */
    public static function fieldRules(bool $passwordRequired): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'smtp_host' => ['required', 'string', 'max:253', self::HOST_REGEX, self::hostRule()],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_username' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'smtp_password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'max:500'],
            'encryption' => ['required', Rule::in(config('mailbatch.smtp.encryptions'))],
            'from_name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n<>"]/'],
            'from_email' => ['required', 'email:rfc', 'max:254'],
        ];
    }

    public const HOST_REGEX = 'regex:/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/';

    /** Rejects private/loopback/internal hosts (SSRF protection). */
    public static function hostRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            try {
                app(SmtpConnectionService::class)->assertHostAllowed((string) $value);
            } catch (SmtpHostNotAllowedException $e) {
                $fail($e->getMessage());
            }
        };
    }

    public function messages(): array
    {
        return [
            'smtp_host.regex' => 'Enter a valid host name or IPv4 address, for example smtp.example.com.',
            'smtp_port.between' => 'The port must be between 1 and 65535.',
            'from_name.not_regex' => 'The from name contains characters that are not allowed.',
            'smtp_password.required' => 'The SMTP password is required.',
        ];
    }

    public function attributes(): array
    {
        return [
            'smtp_host' => 'SMTP host', 'smtp_port' => 'SMTP port', 'smtp_username' => 'SMTP username',
            'smtp_password' => 'SMTP password', 'from_name' => 'from name', 'from_email' => 'from email',
        ];
    }
}
