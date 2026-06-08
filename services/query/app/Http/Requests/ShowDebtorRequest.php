<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ShowDebtorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cuit' => ['required', 'string', 'size:11', 'regex:/^\d{11}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('cuit')) {
            $this->merge(['cuit' => $this->route('cuit')]);
        }
    }
}
