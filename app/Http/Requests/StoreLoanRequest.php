<?php

namespace App\Http\Requests;

use Closure;
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
            'first_instalment_month' => ['required', 'date_format:Y-m', function (string $attribute, mixed $value, Closure $fail): void {
                // Months compare as text (2026-09 < 2026-10); nothing can be deducted before the loan was given.
                $issued = strtotime((string) $this->input('issued_on'));
                if ($issued !== false && is_string($value) && $value < date('Y-m', $issued)) {
                    $fail(__('The first instalment cannot be before the month the loan is given.'));
                }
            }],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
