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
            'issued_to' => ['required', 'string', 'max:120'],
            'wastage_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
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

                $available = app(PaperStock::class)->available((int) $this->paper_type_id, (int) $this->party_id);

                if ((int) $this->quantity > $available) {
                    $validator->errors()->add('quantity', __('Only :n in stock for this paper and party.', ['n' => number_format($available)]));
                }
            },
        ];
    }
}
