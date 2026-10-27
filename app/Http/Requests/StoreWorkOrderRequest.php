<?php

namespace App\Http\Requests;

use App\Enums\PartyKind;
use App\Enums\PrintMode;
use App\Models\BookFormat;
use App\Models\PaperType;
use App\Models\WorkOrder;
use App\Support\PaperStock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkOrderRequest extends FormRequest
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
            'ordered_on' => ['required', 'date'],
            'book_format_id' => ['required', 'exists:book_formats,id'],
            'copies' => ['required', 'integer', 'min:1'],
            'wastage_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'paper_type_id' => ['required', 'exists:paper_types,id'],
            'party_id' => ['required', 'exists:parties,id'],
            'mode' => ['required', Rule::enum(PrintMode::class)],
            'machine_id' => ['nullable', 'required_if:mode,in_house', 'exists:machines,id'],
            'press_id' => ['nullable', 'required_if:mode,outsource', Rule::exists('parties', 'id')->where('kind', PartyKind::Press->value)],
            'buyer_id' => ['nullable', Rule::exists('parties', 'id')->where('kind', PartyKind::Buyer->value)],
            'due_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The godown must hold enough of the chosen paper for the rims this order needs.
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

                $needed = $this->rimsNeeded();
                $available = app(PaperStock::class)->available($this->integer('paper_type_id'), $this->integer('party_id'));

                if ($needed > $available) {
                    $validator->errors()->add('paper_type_id', __('This order needs :need rims but only :n are in stock for this paper and party.', [
                        'need' => number_format($needed), 'n' => number_format($available),
                    ]));
                }
            },
        ];
    }

    public function rimsNeeded(): int
    {
        $format = BookFormat::query()->findOrFail($this->integer('book_format_id'));
        $sheets = PaperType::query()->whereKey($this->integer('paper_type_id'))->value('sheets_per_unit') ?: 500;

        return WorkOrder::rims((float) $format->forms, $this->integer('copies'), (float) $this->input('wastage_percent'), $sheets)['needed'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['machine_id' => __('machine'), 'press_id' => __('outside press'), 'book_format_id' => __('book')];
    }
}
