<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\MachineRepair;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\Purchase;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $from = $this->dateParam($request, 'from', now()->startOfMonth())->startOfDay();
        $to = $this->dateParam($request, 'to', now())->endOfDay();

        $reports = [
            'purchases_by_supplier' => $this->purchasesBy('party', $from, $to),
            'purchases_by_category' => $this->purchasesBy('category', $from, $to),
            'expenses_by_head' => $this->expensesByHead($from, $to),
            'machine_spend' => $this->machineSpend($from, $to),
            'paper_movement' => $this->paperMovement($from, $to),
        ];

        if ($export = $request->string('export')->toString()) {
            abort_unless(isset($reports[$export]), 404);

            return $this->csv("{$export}_{$from->format('Ymd')}_{$to->format('Ymd')}.csv", $reports[$export]);
        }

        return view('reports.index', $reports + ['from' => $from, 'to' => $to]);
    }

    /**
     * A date from the query string, or the default when it is missing or not a date (e.g. a hand-edited link).
     */
    private function dateParam(Request $request, string $key, Carbon $default): Carbon
    {
        try {
            return Carbon::parse($request->string($key)->toString() ?: $default);
        } catch (InvalidFormatException) {
            return $default;
        }
    }

    /**
     * @return Collection<int, array<string, string|int>>
     */
    private function purchasesBy(string $group, Carbon $from, Carbon $to): Collection
    {
        return Purchase::query()->approved()->with(['party', 'item.category'])
            ->whereBetween('purchased_on', [$from, $to])
            ->get()
            ->groupBy(fn (Purchase $p) => $group === 'party' ? $p->party->name : $p->item->category->localName())
            ->map(fn (Collection $rows, string $name) => [__('Name') => $name, __('Bills') => $rows->count(), __('Amount (Rs)') => (int) $rows->sum('amount')])
            ->sortByDesc(__('Amount (Rs)'))
            ->values();
    }

    /**
     * @return Collection<int, array<string, string|int>>
     */
    private function expensesByHead(Carbon $from, Carbon $to): Collection
    {
        return Expense::query()->approved()->with('head')
            ->whereBetween('spent_on', [$from, $to])
            ->get()
            ->groupBy(fn (Expense $e) => $e->head->localName())
            ->map(fn (Collection $rows, string $name) => [__('Head') => $name, __('Entries') => $rows->count(), __('Amount (Rs)') => (int) $rows->sum('amount')])
            ->sortByDesc(__('Amount (Rs)'))
            ->values();
    }

    /**
     * @return Collection<int, array<string, string|int>>
     */
    private function machineSpend(Carbon $from, Carbon $to): Collection
    {
        return MachineRepair::query()->approved()->with('machine')
            ->whereBetween('repaired_on', [$from, $to])
            ->get()
            ->groupBy(fn (MachineRepair $r) => "{$r->machine->code} {$r->machine->name}")
            ->map(fn (Collection $rows, string $name) => [
                __('Machine') => $name,
                __('Bills') => $rows->count(),
                __('Discount (Rs)') => (int) $rows->sum('discount'),
                __('Paid (Rs)') => (int) $rows->sum('amount_paid'),
            ])
            ->sortByDesc(__('Paid (Rs)'))
            ->values();
    }

    /**
     * @return Collection<int, array<string, string|int>>
     */
    private function paperMovement(Carbon $from, Carbon $to): Collection
    {
        $received = PaperReceipt::query()->notRejected()->with('paperType')->whereBetween('received_at', [$from, $to])->get()
            ->groupBy(fn (PaperReceipt $r) => $r->paperType->label());
        $issued = PaperIssue::query()->with('paperType')->whereBetween('issued_on', [$from, $to])->get()
            ->groupBy(fn (PaperIssue $i) => $i->paperType->label());

        return $received->keys()->merge($issued->keys())->unique()->sort()->map(fn (string $paper) => [
            __('Paper') => $paper,
            __('Received') => (int) ($received->get($paper)?->sum('quantity') ?? 0),
            __('In-house') => (int) ($issued->get($paper)?->where('kind.value', 'in_house')->sum('quantity') ?? 0),
            __('Outsource') => (int) ($issued->get($paper)?->where('kind.value', 'outsource')->sum('quantity') ?? 0),
            __('Returned') => (int) ($issued->get($paper)?->where('kind.value', 'return')->sum('quantity') ?? 0),
        ])->values();
    }

    /**
     * @param  Collection<int, array<string, string|int>>  $rows
     */
    private function csv(string $filename, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys($rows->first()));
            }
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
