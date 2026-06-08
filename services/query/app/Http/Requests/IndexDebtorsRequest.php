<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IndexDebtorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'situation' => ['nullable', 'string', 'in:01,03,04,05,11,21,23'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
