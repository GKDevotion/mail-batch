<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['force_unsubscribe' => $this->boolean('force_unsubscribe')]);
    }

    public function rules(): array
    {
        return [
            'max_batch_size' => ['required', 'integer', 'between:1,'.(int) config('mailbatch.hard_max_batch_size')],
            'default_daily_limit' => ['required', 'integer', 'between:0,1000000'],
            'max_retries' => ['required', 'integer', 'between:1,10'],
            'force_unsubscribe' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['max_batch_size.between' => 'The batch size must be between :min and :max (the :max ceiling is set in the server configuration).'];
    }
}
