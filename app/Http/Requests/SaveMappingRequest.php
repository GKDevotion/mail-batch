<?php

namespace App\Http\Requests;

use App\Services\ExcelColumnMappingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('campaign'));
    }

    public function rules(): array
    {
        $headers = $this->route('campaign')->excel_headers ?? [];
        $rules = ['mapping' => ['required', 'array']];

        foreach (app(ExcelColumnMappingService::class)->fields() as $key => $field) {
            $rules["mapping.$key"] = [
                $field['required'] ? 'required' : 'nullable',
                'string',
                Rule::in($headers),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'mapping.email.required' => 'Please choose the column that contains the recipient email address.',
            'mapping.*.in' => 'The selected column does not exist in your file.',
        ];
    }
}
