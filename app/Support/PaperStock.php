<?php

namespace App\Support;

use App\Enums\ApprovalStatus;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use Illuminate\Support\Collection;

/**
 * Running paper balance by type/size and party.
 *
 * Receipts count as soon as the truck is unloaded (waiting or approved); a receipt the owner
 * sends back drops out of stock. Every issue — in-house, outsource or return — is a deduction.
 */
class PaperStock
{
    /**
     * @return Collection<int, array{paper_type: PaperType, party: Party, received: int, pending: int, issued: int, balance: int, status: string}>
     */
    public function balances(?int $paperTypeId = null, ?int $partyId = null): Collection
    {
        $received = PaperReceipt::query()
            ->where('status', '!=', ApprovalStatus::Rejected)
            ->when($paperTypeId, fn ($q) => $q->where('paper_type_id', $paperTypeId))
            ->when($partyId, fn ($q) => $q->where('party_id', $partyId))
            ->selectRaw('paper_type_id, party_id, SUM(quantity) as received')
            ->selectRaw('SUM(CASE WHEN status = ? THEN quantity ELSE 0 END) as pending', [ApprovalStatus::Pending->value])
            ->groupBy('paper_type_id', 'party_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->paper_type_id}-{$row->party_id}");

        $issued = PaperIssue::query()
            ->when($paperTypeId, fn ($q) => $q->where('paper_type_id', $paperTypeId))
            ->when($partyId, fn ($q) => $q->where('party_id', $partyId))
            ->selectRaw('paper_type_id, party_id, SUM(quantity) as issued')
            ->groupBy('paper_type_id', 'party_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->paper_type_id}-{$row->party_id}");

        $keys = $received->keys()->merge($issued->keys())->unique();
        $paperTypes = PaperType::query()->findMany($keys->map(fn ($k) => (int) explode('-', $k)[0]))->keyBy('id');
        $parties = Party::query()->findMany($keys->map(fn ($k) => (int) explode('-', $k)[1]))->keyBy('id');

        return $keys->map(function (string $key) use ($received, $issued, $paperTypes, $parties): array {
            [$typeId, $partyId] = array_map('intval', explode('-', $key));
            $in = (int) ($received[$key]->received ?? 0);
            $out = (int) ($issued[$key]->issued ?? 0);

            return [
                'paper_type' => $paperTypes[$typeId],
                'party' => $parties[$partyId],
                'received' => $in,
                'pending' => (int) ($received[$key]->pending ?? 0),
                'issued' => $out,
                'balance' => $in - $out,
            ];
        })
            ->map(fn (array $row): array => $row + ['status' => $this->levelFor($row['paper_type'], $row['balance'])])
            ->sortBy(fn (array $row): string => $row['paper_type']->code.$row['party']->name)
            ->values();
    }

    public function available(int $paperTypeId, int $partyId): int
    {
        return (int) ($this->balances($paperTypeId, $partyId)->first()['balance'] ?? 0);
    }

    /**
     * Rim balance across all parties for each paper type, with its reorder status.
     *
     * @return Collection<int, array{paper_type: PaperType, balance: int, status: string}>
     */
    public function byPaperType(): Collection
    {
        $balances = $this->balances()->groupBy(fn (array $row): int => $row['paper_type']->id);

        return PaperType::query()->active()->orderBy('code')->get()->map(function (PaperType $type) use ($balances): array {
            $balance = (int) ($balances->get($type->id)?->sum('balance') ?? 0);

            return ['paper_type' => $type, 'balance' => $balance, 'status' => $this->levelFor($type, $balance)];
        });
    }

    /**
     * @return Collection<int, array{paper_type: PaperType, balance: int, status: string}>
     */
    public function belowReorderLevel(): Collection
    {
        return $this->byPaperType()->filter(fn (array $row): bool => $row['status'] !== 'ok')->values();
    }

    /**
     * "critical" below half the reorder level, "low" below it, otherwise "ok".
     */
    public function levelFor(PaperType $type, int $balance): string
    {
        return match (true) {
            $type->reorder_level > 0 && $balance < $type->reorder_level / 2 => 'critical',
            $balance < $type->reorder_level => 'low',
            default => 'ok',
        };
    }
}
