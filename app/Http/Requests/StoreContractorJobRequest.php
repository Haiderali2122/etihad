<?php

namespace App\Http\Requests;

use App\Enums\ContractorJobKind;
use App\Models\ContractorJob;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractorJobRequest extends FormRequest
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
            'contractor_id' => ['required', Rule::exists('contractors', 'id')->where('is_active', true)],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'kind' => ['required', Rule::enum(ContractorJobKind::class)],
            'job_on' => ['required', 'date'],
            'forms' => ['required', 'numeric', 'min:0.01', 'max:9999', 'decimal:0,2'],
            'copies' => ['required', 'integer', 'min:1'],
            'folding_rate' => ['nullable', 'required_if:kind,folding', 'numeric', 'decimal:0,2', 'min:0'],
            'form_rate' => ['nullable', 'required_if:kind,binding', 'numeric', 'decimal:0,2', 'min:0'],
            'copy_rate' => ['nullable', 'required_if:kind,binding', 'numeric', 'decimal:0,2', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ] + self::extraWorkRules();
    }

    /**
     * Extra work ticked on the job (sticker pasting, numbering, pinning): a ticked one needs its rate per 1,000 books.
     *
     * @return array<string, list<string>>
     */
    public static function extraWorkRules(): array
    {
        return collect(array_keys(ContractorJob::Extras))->flatMap(fn (string $extra) => [
            "has_{$extra}" => ['nullable', 'boolean'],
            "{$extra}_rate" => ['nullable', "required_if_accepted:has_{$extra}", 'numeric', 'decimal:0,2', 'min:0'],
        ])->all();
    }

    /**
     * The extra-work rates to save: the typed rate where ticked, empty where not.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, float|null>
     */
    public static function extraWorkRates(array $data): array
    {
        return collect(array_keys(ContractorJob::Extras))->mapWithKeys(fn (string $extra) => [
            "{$extra}_rate" => filter_var($data["has_{$extra}"] ?? false, FILTER_VALIDATE_BOOLEAN) ? (float) $data["{$extra}_rate"] : null,
        ])->all();
    }
}
