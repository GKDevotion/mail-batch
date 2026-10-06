<?php

namespace App\Http\Requests;

use App\Services\CampaignService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership/permission is enforced by CampaignPolicy in the controller
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'include_unsubscribe' => $this->boolean('include_unsubscribe'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'batch_size' => ['required', 'integer', 'min:1', 'max:'.CampaignService::maxBatchSize()],
            'include_unsubscribe' => ['boolean'],
            'sender_identification' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'batch_size.max' => 'Batch size cannot exceed :max emails per action.',
        ];
    }
}
