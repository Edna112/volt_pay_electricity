<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EneoVerifyMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'meter_number' => ['required', 'string', 'max:100'],
            'provider' => ['required', 'string', 'in:ENEO,eneo'],
            'alias' => ['nullable', 'string', 'max:255'],
        ];
    }
}

