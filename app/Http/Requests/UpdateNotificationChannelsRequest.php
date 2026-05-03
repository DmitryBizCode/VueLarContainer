<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notification_email_enabled' => ['required', 'boolean'],
            'notification_telegram_enabled' => ['required', 'boolean'],
        ];
    }
}
