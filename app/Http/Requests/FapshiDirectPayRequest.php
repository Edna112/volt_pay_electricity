<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FapshiDirectPayRequest extends FormRequest
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
            'meter_id' => ['required', 'integer', 'exists:meters,id'],
            // Customer pays total amount; VoltPay keeps fixed fee.
            'amount' => ['required', 'numeric', 'min:200'],
            'reference' => ['required', 'string', 'max:255'],

            // Fapshi Direct Pay fields
            // MTN/Orange (CM) numbers are 9 digits (e.g. 67XXXXXXX).
            // We accept only digits here to avoid ambiguity; frontend can strip +237/spaces before sending.
            'phone' => ['required', 'regex:/^\d{9}$/'],
            'medium' => ['nullable', 'string', 'in:mobile money,orange money'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:255'],
        ];
    }
}

