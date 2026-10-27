<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-settings');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'manager_daily_limit' => ['required', 'integer', 'min:0'],
            'default_wastage_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'ot_divisor' => ['required', 'numeric', 'min:1', 'max:24'],
            'ot_multiplier' => ['required', 'numeric', 'min:0.5', 'max:3'],
            'standard_hours' => ['required', 'numeric', 'min:1', 'max:16'],
            'unmarked_as' => ['required', 'in:present,absent'],
            'mid_month_day' => ['required', 'integer', 'min:1', 'max:28'],
            'mid_month_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'pay_day' => ['required', 'integer', 'min:1', 'max:28'],
        ];
    }
}
