<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('is_active', true)],
            'issued_on' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'monthly_instalment' => ['required', 'integer', 'min:1', 'lte:amount'],
            'first_instalment_month' => ['required', 'date_format:Y-m'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
