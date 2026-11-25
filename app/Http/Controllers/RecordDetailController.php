<?php

namespace App\Http\Controllers;

use App\Models\BillPayment;
use App\Models\CashFloat;
use App\Models\ContractorJob;
use App\Models\ContractorPayment;
use App\Models\Dispatch;
use App\Models\Expense;
use App\Models\ForemanCash;
use App\Models\InventoryIssue;
use App\Models\Loan;
use App\Models\MachineRepair;
use App\Models\MaterialBill;
use App\Models\PaperIssue;
use App\Models\PaperLoan;
use App\Models\PaperReceipt;
use App\Models\PlateJob;
use App\Models\PlatePurchase;
use App\Models\ProcessorPayment;
use App\Models\Purchase;
use App\Models\RimLoading;
use App\Models\SalaryAdvance;
use App\Models\Sale;
use App\Models\User;
use App\Models\YounusPayment;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * One read-only page for any record, opened by clicking its row: every field (linked records by name, not
 * number), its attached file, who entered it and when, and its lines, payments, returns or receipts. Nothing on it
 * can be changed; changes are made where the record lives.
 */
class RecordDetailController extends Controller
{
    /**
     * Type in the URL => [model, permission to see it].
     *
     * @var array<string, array{0: class-string<Model>, 1: string}>
     */
    public const Types = [
        'expense' => [Expense::class, 'expenses.view'],
        'cash-float' => [CashFloat::class, 'expenses.view'],
        'bill-payment' => [BillPayment::class, 'expenses.view'],
        'material-bill' => [MaterialBill::class, 'purchases.view'],
        'purchase' => [Purchase::class, 'purchases.view'],
        'inventory-issue' => [InventoryIssue::class, 'purchases.view'],
        'sale' => [Sale::class, 'purchases.view'],
        'plate-purchase' => [PlatePurchase::class, 'purchases.view'],
        'plate-job' => [PlateJob::class, 'orders.view'],
        'paper-receipt' => [PaperReceipt::class, 'paper.view'],
        'paper-issue' => [PaperIssue::class, 'paper.view'],
        'paper-loan' => [PaperLoan::class, 'paper.view'],
        'khata-line' => [RimLoading::class, 'orders.view'],
        'processor-payment' => [ProcessorPayment::class, 'orders.view'],
        'younus-payment' => [YounusPayment::class, 'orders.view'],
        'contractor-job' => [ContractorJob::class, 'orders.view'],
        'contractor-payment' => [ContractorPayment::class, 'orders.view'],
        'dispatch' => [Dispatch::class, 'orders.view'],
        'machine-repair' => [MachineRepair::class, 'machines.view'],
        'foreman-cash' => [ForemanCash::class, 'staff.view'],
        'salary-advance' => [SalaryAdvance::class, 'staff.view'],
        'loan' => [Loan::class, 'staff.view'],
    ];

    /** Fields never shown. */
    private const Hidden = ['id', 'password', 'remember_token', 'sync_key', 'pending_commands', 'source_type', 'source_id', 'payable_type', 'payable_id', 'updated_at'];

    /** Lists shown under the record when it has them. */
    private const Lists = ['items', 'payments', 'receipts', 'returns', 'repayments'];

    public function show(string $type, int $id): View
    {
        abort_unless(isset(self::Types[$type]), 404);
        [$class, $permission] = self::Types[$type];
        abort_unless(Gate::allows($permission), 403);
        $record = $class::query()->findOrFail($id);

        $lists = [];
        foreach (self::Lists as $name) {
            if (! $this->isRelation($record, $name)) {
                continue;
            }
            $relation = $record->{$name}();
            if (! $relation instanceof HasMany && ! $relation instanceof MorphMany) {
                continue;
            }
            $rows = $relation->get();
            if ($rows->isNotEmpty()) {
                $lists[__(Str::headline($name))] = $rows->map(fn (Model $row) => $this->fields($row, $record))->all();
            }
        }

        $attachment = $record->getAttribute('attachment');

        return view('records.show', [
            'title' => (method_exists($record, 'deletionKind') ? $record->deletionKind() : class_basename($record)).' · '.$this->name($record),
            'fields' => $this->fields($record),
            'lists' => $lists,
            'attachment' => $attachment ? [
                'url' => Storage::disk('public')->url($attachment),
                'image' => (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $attachment),
            ] : null,
            'cashOut' => method_exists($record, 'cashOutExpense') ? $record->cashOutExpense()->first() : null,
            'source' => $record instanceof Expense && $record->isLinked() ? $record->source : null,
            'back' => url()->previous() !== url()->current() ? url()->previous() : route('dashboard'),
        ]);
    }

    /**
     * Each field as [label, shown value], in the record's own order.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function fields(Model $record, ?Model $parent = null): array
    {
        $out = [];
        foreach ($record->getAttributes() as $key => $raw) {
            if (in_array($key, self::Hidden, true) || ($parent && $key === Str::snake(class_basename($parent)).'_id')) {
                continue;
            }
            $out[] = [$this->label($key), $this->value($record, $key)];
        }

        return $out;
    }

    private function label(string $key): string
    {
        $key = match ($key) {
            'created_at' => 'entered_at',
            'created_by' => 'entered_by',
            default => $key,
        };

        return __(Str::headline(preg_replace('/_id$/', '', $key)));
    }

    private function value(Model $record, string $key): string
    {
        $value = $record->getAttribute($key);
        if ($value === null || $value === '') {
            return '—';
        }

        // Linked records by name: work order, party, paper, machine, employee, and who entered or decided.
        if (str_ends_with($key, '_id') || str_ends_with($key, '_by')) {
            $base = preg_replace('/_(id|by)$/', '', $key);
            foreach (array_unique([Str::camel($base), Str::camel(preg_replace('/_party$/', '', $base)), $base === 'party' ? 'processor' : $base]) as $relation) {
                if ($this->isRelation($record, $relation) && $record->{$relation}() instanceof BelongsTo) {
                    $linked = $record->{$relation};

                    return $linked ? $this->name($linked) : '—';
                }
            }
            if (str_ends_with($key, '_by')) {
                return (string) (User::query()->whereKey($value)->value('name') ?? '—');
            }
        }

        if ($value instanceof BackedEnum) {
            return method_exists($value, 'label') ? $value->label() : (string) $value->value;
        }
        if ($value instanceof CarbonInterface) {
            return $value->format($value->format('H:i:s') === '00:00:00' ? 'j M Y' : 'j M Y, g:i A');
        }
        if (is_bool($value) || in_array($key, ['is_active', 'is_foreman', 'is_approved'], true)) {
            return $value ? __('Yes') : __('No');
        }
        if ($key === 'attachment') {
            return __('Attached (shown below)');
        }
        if (is_numeric($value) && preg_match('/amount|total|salary|pay$|_pay|deduct|bonus|advance|cost|price/', $key) && ! str_contains($key, 'rate')) {
            return 'Rs '.number_format((float) $value);
        }
        if (is_numeric($value) && str_contains((string) $value, '.')) {
            return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Whether a method on the record is a relation that can be called without arguments (not, say, the static
     * created() event hook).
     */
    private function isRelation(Model $record, string $method): bool
    {
        if (! method_exists($record, $method)) {
            return false;
        }
        $reflection = new \ReflectionMethod($record, $method);

        return ! $reflection->isStatic() && $reflection->isPublic() && $reflection->getNumberOfRequiredParameters() === 0
            && $reflection->getDeclaringClass()->getName() !== Model::class;
    }

    /**
     * A readable name for a record.
     */
    private function name(Model $model): string
    {
        foreach (['number', 'name', 'slip_no', 'code'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return (string) $model->getAttribute($attribute);
            }
        }
        foreach (['label', 'receiptNumber', 'deletionLabel'] as $method) {
            if (method_exists($model, $method)) {
                return (string) $model->{$method}();
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
