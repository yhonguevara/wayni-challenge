<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TopDebtorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'n' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('n')) {
            $this->merge(['n' => $this->route('n')]);
        }
    }
}
