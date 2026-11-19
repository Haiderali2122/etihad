<?php

namespace App\Http\Requests;

use App\Enums\PaperIssueKind;
use App\Support\PaperStock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaperIssueRequest extends FormRequest
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
            'issued_on' => ['required', 'date'],
            'kind' => ['required', Rule::enum(PaperIssueKind::class)],
            'party_id' => ['required', 'exists:parties,id'],
            'paper_type_id' => ['required', 'exists:paper_types,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:40'],
            'book_format_id' => ['nullable', 'exists:book_formats,id'],
            'book_name' => ['nullable', 'string', 'max:120'],
            'copies' => ['nullable', 'integer', 'min:1'],
            'issued_to' => [Rule::requiredIf(fn () => $this->input('kind') !== PaperIssueKind::InHouse->value || ! $this->filled('machine_id')), 'nullable', 'string', 'max:120'],
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->where('is_active', true)],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'wastage_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'note' => ['nullable', 'string', 'max:255'],
            // "paper type id-party id" of the stock the shortage is taken from.
            'shortage_stock' => ['nullable', 'regex:/^\d+-\d+$/'],
        ];
    }

    /**
     * The stock picked to cover a shortage: [paper type id, party id], or [0, 0] when none.
     *
     * @return array{0: int, 1: int}
     */
    public function shortageStock(): array
    {
        $parts = explode('-', (string) $this->input('shortage_stock'));

        return count($parts) === 2 ? [(int) $parts[0], (int) $parts[1]] : [0, 0];
    }

    /**
     * Paper cannot leave the godown if the party's balance does not cover it.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $stock = app(PaperStock::class);
                $available = $stock->available((int) $this->paper_type_id, (int) $this->party_id);
                $short = (int) $this->quantity - $available;
                if ($short <= 0) {
                    return;
                }

                // Short: the rest can come from another stock of the same paper; the order party then owes it.
                if (! $this->filled('shortage_stock') || $this->input('kind') === PaperIssueKind::Return->value) {
                    $validator->errors()->add('quantity', __('Only :n in stock for this paper and party.', ['n' => number_format($available)]));

                    return;
                }
                [$coverType, $coverParty] = $this->shortageStock();
                if ($coverType === (int) $this->paper_type_id && $coverParty === (int) $this->party_id) {
                    $validator->errors()->add('shortage_stock', __('Pick a different stock for the rest.'));

                    return;
                }
                $cover = $stock->available($coverType, $coverParty);
                if ($cover < $short) {
                    $validator->errors()->add('shortage_stock', __('That stock has only :n rims; :short are needed.', ['n' => number_format($cover), 'short' => number_format($short)]));
                }
            },
        ];
    }
}
