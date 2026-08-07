<?php

namespace App\Http\Requests;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TransactionIndexRequest extends FormRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'type' => ['sometimes', 'nullable', new Enum(TransactionType::class)],
            'status' => ['sometimes', 'nullable', new Enum(TransactionStatus::class)],
        ]);
    }
}
