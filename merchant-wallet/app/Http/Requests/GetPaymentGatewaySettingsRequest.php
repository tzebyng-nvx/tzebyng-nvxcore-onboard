<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Foundation\Http\FormRequest;

class GetPaymentGatewaySettingsRequest extends FormRequest
{
    use HasPaginationRules;

    public function rules(): array
    {
        return [
            //
        ];
    }
}
