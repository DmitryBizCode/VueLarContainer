<?php

namespace App\Http\Requests;

use App\Models\Inquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminInquiryHandlingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'handling_status' => ['required', 'string', 'max:40', Rule::in(Inquiry::handlingStatusValues())],
            'admin_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
