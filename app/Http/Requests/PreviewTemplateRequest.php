<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Lenient: live preview works while the author is still typing. */
class PreviewTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configure', $this->route('campaign'));
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string', 'max:'.config('mailbatch.template.max_html_length')],
            'body_text' => ['nullable', 'string', 'max:'.config('mailbatch.template.max_text_length')],
            'recipient_id' => ['nullable', 'integer'],
        ];
    }
}
