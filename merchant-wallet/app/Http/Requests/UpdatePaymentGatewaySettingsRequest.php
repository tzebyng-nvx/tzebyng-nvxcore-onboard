<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentGatewaySettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'merchant_username' => ['sometimes', 'string', 'max:255'],
            'api_key' => ['sometimes', 'string', 'max:255'],
            'secret_key' => ['sometimes', 'string', 'max:255'],
            'base_url' => ['sometimes', 'url', 'max:255'],
        ];
    }
}
