<?php

namespace App\Http\Controllers;

use App\Enums\MachineStatus;
use App\Models\CashFloat;
use App\Models\Expense;
use App\Models\Machine;
use App\Models\MachineRepair;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Support\PaperStock;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(PaperStock $stock, ApprovalController $approvals): View
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $cashGiven = (int) CashFloat::query()->sum('amount');
        $cashSpent = (int) Expense::query()->notRejected()->whereIn('user_id', CashFloat::query()->select('user_id'))->sum('amount');

        $machineSpend = (int) MachineRepair::query()->notRejected()->whereBetween('repaired_on', [$monthStart, $monthEnd])->sum('amount_paid')
            + (int) Expense::query()->notRejected()->whereNotNull('machine_id')->whereBetween('spent_on', [$monthStart, $monthEnd])->sum('amount');

        $byType = $stock->byPaperType();

        return view('dashboard', [
            'paperBalance' => $byType->where('paper_type.unit', 'rims')->sum('balance'),
            'paperTypeCount' => $byType->count(),
            'lowStock' => $byType->filter(fn (array $row): bool => $row['status'] !== 'ok')->values(),
            'cashInHand' => $cashGiven - $cashSpent,
            'cashGiven' => $cashGiven,
            'cashSpent' => $cashSpent,
            'machineSpend' => $machineSpend,
            'machines' => Machine::query()->orderBy('code')->get(),
            'machinesUnderRepair' => Machine::query()->where('status', MachineStatus::UnderRepair)->count(),
            'pending' => $approvals->pendingItems(),
            'weekly' => $this->weeklyPaper(),
            'heads' => $this->spendByHead($monthStart, $monthEnd),
            'feed' => $this->activity(),
        ]);
    }

    /**
     * Rims received and issued per week for the last eight weeks.
     *
     * @return array{labels: list<string>, received: list<int>, issued: list<int>}
     */
    private function weeklyPaper(): array
    {
        $start = now()->startOfWeek()->subWeeks(7);
        $labels = $received = $issued = [];

        $receipts = PaperReceipt::query()->notRejected()->where('received_at', '>=', $start)->get(['received_at', 'quantity']);
        $issues = PaperIssue::query()->where('issued_on', '>=', $start)->get(['issued_on', 'quantity']);

        for ($week = 0; $week < 8; $week++) {
            $from = $start->copy()->addWeeks($week);
            $to = $from->copy()->endOfWeek();
            $labels[] = $from->format('j M');
            $received[] = (int) $receipts->filter(fn ($r) => $r->received_at->between($from, $to))->sum('quantity');
            $issued[] = (int) $issues->filter(fn ($i) => $i->issued_on->between($from, $to))->sum('quantity');
        }

        return compact('labels', 'received', 'issued');
    }

    /**
     * @return list<array{name: string, color: string, amount: int}>
     */
    private function spendByHead(Carbon $since, Carbon $until): array
    {
        return Expense::query()
            ->notRejected()
            ->whereBetween('spent_on', [$since, $until])
            ->with('head')
            ->get()
            ->groupBy('expense_head_id')
            ->map(fn ($rows) => [
                'name' => $rows->first()->head->localName(),
                'color' => $rows->first()->head->color,
                'amount' => (int) $rows->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * Latest receipts, expenses and repairs merged into one feed.
     *
     * @return list<array{tone: string, text: string, at: Carbon}>
     */
    private function activity(): array
    {
        $receipts = PaperReceipt::query()->with(['party', 'paperType'])->latest()->take(5)->get()
            ->map(fn (PaperReceipt $r) => [
                'tone' => '',
                'text' => __(':by received :qty :unit :paper from :party', [
                    'by' => e($r->received_by),
                    'qty' => '<b>'.number_format($r->quantity),
                    'unit' => __($r->paperType->unit),
                    'paper' => e($r->paperType->name).'</b>',
                    'party' => e($r->party->name),
                ]),
                'at' => $r->created_at,
            ]);

        $expenses = Expense::query()->with('head')->latest()->take(5)->get()
            ->map(fn (Expense $e) => [
                'tone' => $e->isPending() ? 'w' : '',
                'text' => '<b>Rs '.number_format($e->amount).'</b> '.e($e->head->localName()).' — '.e($e->reason),
                'at' => $e->created_at,
            ]);

        $repairs = MachineRepair::query()->with('machine')->latest()->take(5)->get()
            ->map(fn (MachineRepair $m) => [
                'tone' => $m->isPending() ? 'w' : 'g',
                'text' => '<b>'.e($m->machine->code).'</b> '.e($m->description).' — Rs '.number_format($m->amount_paid),
                'at' => $m->created_at,
            ]);

        return $receipts->concat($expenses)->concat($repairs)->sortByDesc('at')->take(6)->values()->all();
    }
}
