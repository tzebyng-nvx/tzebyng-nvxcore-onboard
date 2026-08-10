<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', Rule::unique('tenants', 'id')],
            'domain' => ['required', 'string', 'max:255', Rule::unique('domains', 'domain')],
            'admin_name' => ['sometimes', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_phone' => ['sometimes', 'string', 'max:30'],
            'admin_password' => ['required', 'string', 'min:6'],
        ];
    }
}
