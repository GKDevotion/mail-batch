<?php

namespace App\Http\Requests;

class TestEmailRequest extends SaveTemplateRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'test_email' => ['required', 'email:rfc', 'max:254'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + ['test_email.required' => 'Enter an email address to send the test to.'];
    }
}
