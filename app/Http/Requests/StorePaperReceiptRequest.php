<?php

namespace App\Http\Requests;

use App\Models\Godown;
use App\Models\PaperReceipt;
use App\Support\OtherOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaperReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "Other…" on the list: save the typed name to the list (or reuse a matching one) before validating.
     */
    protected function prepareForValidation(): void
    {
        OtherOption::resolve($this, 'godown_id', Godown::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A corrected copy of a sent-back receipt may keep that receipt's slip number.
            'slip_no' => ['required', 'string', 'max:30', Rule::unique('paper_receipts', 'slip_no')->ignore($this->route('paper_receipt')?->id ?? PaperReceipt::beingResent()?->id)],
            'received_at' => ['required', 'date'],
            'party_id' => ['required', 'exists:parties,id'],
            'paper_type_id' => ['required', 'exists:paper_types,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'truck_no' => ['nullable', 'string', 'max:30'],
            'godown_id' => ['required', Rule::exists('godowns', 'id')->where('is_active', true)],
            'received_by' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['godown_id' => __('unloaded in')];
    }
}
