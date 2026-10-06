<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('campaign'));
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('mailbatch.upload.max_kb'),
                'extensions:'.implode(',', config('mailbatch.upload.extensions')),
                'mimetypes:'.implode(',', config('mailbatch.upload.mimes')),
            ],
        ];
    }

    public function messages(): array
    {
        $mb = round(config('mailbatch.upload.max_kb') / 1024, 1);

        return [
            'file.required' => 'Please choose an Excel file to upload.',
            'file.uploaded' => 'The upload failed. The file may be larger than the server allows.',
            'file.max' => "The file is too large. The maximum size is {$mb} MB.",
            'file.extensions' => 'Only .xls and .xlsx files are accepted.',
            'file.mimetypes' => 'This does not look like a valid Excel spreadsheet.',
        ];
    }
}
