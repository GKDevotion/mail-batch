<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * AJAX SMTP test. Works with unsaved form values, a saved account id, or both
 * (form values override; a blank password falls back to the saved one).
 */
class TestSmtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $req = $this->filled('smtp_account_id') ? 'nullable' : 'required';

        return [
            'mode' => ['required', Rule::in(['connection', 'email'])],
            'test_email' => ['required_if:mode,email', 'nullable', 'email:rfc', 'max:254'],
            'smtp_account_id' => ['nullable', 'integer', Rule::exists('smtp_accounts', 'id')->where('user_id', $this->user()->id)],
            'smtp_host' => [$req, 'string', 'max:253', SmtpAccountRequest::HOST_REGEX, SmtpAccountRequest::hostRule()],
            'smtp_port' => [$req, 'integer', 'between:1,65535'],
            'smtp_username' => [$req, 'string', 'max:255'],
            'smtp_password' => [$req, 'string', 'max:500'],
            'encryption' => [$req, Rule::in(config('mailbatch.smtp.encryptions'))],
            'from_name' => [$req, 'string', 'max:100', 'not_regex:/[\r\n<>"]/'],
            'from_email' => [$req, 'email:rfc', 'max:254'],
        ];
    }

    public function messages(): array
    {
        return ['test_email.required_if' => 'Enter an email address to send the test message to.'] + (new SmtpAccountRequest)->messages();
    }

    public function attributes(): array
    {
        return (new SmtpAccountRequest)->attributes();
    }
}
