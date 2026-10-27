<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreForemanSheetRequest extends FormRequest
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
            'foreman_id' => ['required', Rule::exists('employees', 'id')->where('is_foreman', true)->where('is_active', true)],
            'given_on' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['array'],
            'lines.*.employee_id' => ['nullable', 'distinct', Rule::exists('employees', 'id')->where('is_active', true)],
            'lines.*.amount' => ['nullable', 'integer', 'min:1', 'required_with:lines.*.employee_id'],
        ];
    }

    /**
     * The foreman cannot hand out more than he was given.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $handed = collect($this->input('lines', []))->filter(fn ($l) => filled($l['employee_id'] ?? null))->sum(fn ($l) => (int) ($l['amount'] ?? 0));

                if ($handed > (int) $this->input('amount')) {
                    $validator->errors()->add('amount', __('Handed out (Rs :handed) is more than the cash given (Rs :given).', [
                        'handed' => number_format($handed), 'given' => number_format((int) $this->input('amount')),
                    ]));
                }
            },
        ];
    }
}
