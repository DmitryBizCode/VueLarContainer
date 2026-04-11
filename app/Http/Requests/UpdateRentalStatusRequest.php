<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRentalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['approved', 'rejected', 'scheduled', 'in_progress', 'completed', 'cancelled', 'pending_approval']),
            ],
            'rejection_reason' => ['nullable', 'string', 'max:2000', 'required_if:status,rejected'],
            'payment_status' => ['nullable', 'string', Rule::in(['paid', 'pending', 'unpaid', 'failed'])],
        ];
    }
}
