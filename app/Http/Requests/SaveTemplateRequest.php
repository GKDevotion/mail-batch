<?php

namespace App\Http\Requests;

use App\Services\EmailTemplateService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configure', $this->route('campaign'));
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'body_html' => ['required', 'string', 'max:'.config('mailbatch.template.max_html_length')],
            'body_text' => ['nullable', 'string', 'max:'.config('mailbatch.template.max_text_length')],
            'recipient_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'Please enter an email subject.',
            'subject.not_regex' => 'The subject must be a single line.',
            'body_html.required' => 'Please write the email body.',
            'body_html.max' => 'The HTML body is too long.',
        ];
    }

    /** Unknown {{variables}} are rejected with a friendly message. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $service = app(EmailTemplateService::class);
                $campaign = $this->route('campaign');
                $unknown = $service->unknownVariables($campaign, (string) $this->subject, (string) $this->body_html, (string) $this->body_text);

                if ($unknown !== []) {
                    $names = collect($unknown)->map(fn ($n) => '{{'.$n.'}}')->implode(', ');
                    $available = collect(array_keys($service->variableList($campaign)))->map(fn ($n) => '{{'.$n.'}}')->implode(', ');
                    $validator->errors()->add('body_html', "Unknown variable(s): {$names}. Available variables: {$available}.");
                }
            },
        ];
    }
}
