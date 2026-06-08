<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ShowEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:5', 'regex:/^\d{1,5}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('code')) {
            $this->merge(['code' => $this->route('code')]);
        }
    }
}
