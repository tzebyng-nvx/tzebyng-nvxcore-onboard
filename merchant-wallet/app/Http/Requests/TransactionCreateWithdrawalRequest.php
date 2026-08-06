<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransactionCreateWithdrawalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'currency' => [
                'required',
                'string',
                'max:10',
            ],

            'bank_id' => [
                'required',
                'string',
            ],

            'holder_name' => [
                'required',
                'string',
                'max:255',
            ],

            'account_no' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
