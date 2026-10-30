<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestPushNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cuenta_esperada' => ['required', 'integer', 'min:1'],
            'user_id' => ['prohibited'], 'device_id' => ['prohibited'], 'subscription_id' => ['prohibited'],
            'incident_id' => ['prohibited'], 'title' => ['prohibited'], 'body' => ['prohibited'],
        ];
    }
}
