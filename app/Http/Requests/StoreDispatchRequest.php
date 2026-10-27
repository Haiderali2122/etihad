<?php

namespace App\Http\Requests;

use App\Enums\PartyKind;
use App\Models\Dispatch;
use App\Models\WorkOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDispatchRequest extends FormRequest
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
            'dispatched_on' => ['required', 'date'],
            'buyer_id' => ['required', Rule::exists('parties', 'id')->where('kind', PartyKind::Buyer->value)],
            'destination' => ['nullable', 'string', 'max:120'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'book_format_id' => ['nullable', 'required_without:work_order_id', 'exists:book_formats,id'],
            'bundles' => ['required', 'integer', 'min:0'],
            'copies_per_bundle' => ['required', 'integer', 'min:1'],
            'loose_copies' => ['nullable', 'integer', 'min:0'],
            'rims' => ['nullable', 'numeric', 'min:0'],
            'labour_per_bundle' => ['nullable', 'numeric', 'min:0'],
            'labour_per_rim' => ['nullable', 'numeric', 'min:0'],
            'labour_paid_to' => ['nullable', 'string', 'max:120'],
            'truck_no' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A load must carry books, and an order can only be loaded once it is bound and only up to the copies it has left.
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

                $copies = $this->integer('bundles') * $this->integer('copies_per_bundle') + $this->integer('loose_copies');

                if ($copies < 1) {
                    $validator->errors()->add('copies', __('A load must have at least one copy.'));

                    return;
                }

                $order = $this->filled('work_order_id') ? WorkOrder::query()->find($this->integer('work_order_id')) : null;

                if (! $order) {
                    return;
                }

                if (! in_array($order->stage, Dispatch::orderStages(), true)) {
                    $validator->errors()->add('work_order_id', __('Order :number is still at :stage and cannot be loaded yet.', [
                        'number' => $order->number, 'stage' => $order->stage->label(),
                    ]));

                    return;
                }

                $remaining = max(0, $order->copies - (int) $order->dispatches()->sum('copies'));

                if ($copies > $remaining) {
                    $validator->errors()->add('copies', __('Only :n copies of order :number are left to load.', [
                        'n' => number_format($remaining), 'number' => $order->number,
                    ]));
                }
            },
        ];
    }
}
