<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Either pick one of your saved SMTP accounts, or submit a complete new account. */
class SaveCampaignSmtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configure', $this->route('campaign'));
    }

    public function rules(): array
    {
        if ($this->filled('smtp_account_id') || ! $this->filled('smtp_host')) {
            return [
                'smtp_account_id' => [
                    'required', 'integer',
                    Rule::exists('smtp_accounts', 'id')->where('user_id', $this->user()->id)->where('status', 'active'),
                ],
            ];
        }

        return SmtpAccountRequest::fieldRules(passwordRequired: true);
    }

    public function messages(): array
    {
        return [
            'smtp_account_id.required' => 'Please choose a saved SMTP account or add a new one.',
            'smtp_account_id.exists' => 'The selected SMTP account is not available.',
        ] + (new SmtpAccountRequest)->messages();
    }

    public function attributes(): array
    {
        return (new SmtpAccountRequest)->attributes();
    }
}
