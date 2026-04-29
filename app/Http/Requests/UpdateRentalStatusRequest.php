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
            'cancellation_reason' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(function () {
                    if ($this->input('status') !== 'cancelled') {
                        return false;
                    }
                    $role = (string) ($this->user()?->role ?? '');

                    return in_array($role, ['admin', 'operator', 'ops'], true);
                }),
            ],
            'payment_status' => ['nullable', 'string', Rule::in(['paid', 'pending', 'unpaid', 'failed', 'rejected_by_approval'])],
        ];
    }
}
