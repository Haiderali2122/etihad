<?php

namespace App\Models;

use App\Models\Concerns\HasBillPayments;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\PlatePurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Blank plates bought from a supplier: size, number of plates and price per plate. It adds to plate stock;
 * plate jobs given to processors take from it. The bill is paid later, in parts or all at once.
 */
#[Fillable(['purchased_on', 'supplier', 'plate_size', 'plates', 'rate', 'amount', 'paid_amount', 'bill_no', 'attachment', 'note', 'created_by'])]
class PlatePurchase extends Model
{
    /** @use HasFactory<PlatePurchaseFactory> */
    use HasBillPayments, HasFactory, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'plates' => 'integer',
            'rate' => 'decimal:2',
            'amount' => 'integer',
            'paid_amount' => 'integer',
        ];
    }

    public function billTotal(): int
    {
        return (int) $this->amount;
    }

    /**
     * Plates in stock by size: bought less given out on plate jobs, biggest stock first.
     *
     * @return Collection<string, array{size: string, bought: int, used: int, stock: int}>
     */
    public static function stockBySize(): Collection
    {
        $key = fn (?string $size) => mb_strtolower(trim((string) $size));
        $bought = static::query()->selectRaw('plate_size, SUM(plates) as n')->groupBy('plate_size')->get();
        $used = PlateJob::query()->whereNotNull('plate_size')->selectRaw('plate_size, SUM(plates) as n')->groupBy('plate_size')->get();

        $rows = [];
        foreach ($bought as $b) {
            $rows[$key($b->plate_size)] ??= ['size' => trim($b->plate_size), 'bought' => 0, 'used' => 0];
            $rows[$key($b->plate_size)]['bought'] += (int) $b->n;
        }
        foreach ($used as $u) {
            $rows[$key($u->plate_size)] ??= ['size' => trim($u->plate_size), 'bought' => 0, 'used' => 0];
            $rows[$key($u->plate_size)]['used'] += (int) $u->n;
        }

        return collect($rows)->map(fn (array $r) => $r + ['stock' => $r['bought'] - $r['used']])->sortByDesc('stock');
    }

    public function deletionLabel(): string
    {
        return $this->plates.' × '.$this->plate_size.' '.__('plates').' — Rs '.number_format($this->amount).($this->supplier ? ', '.$this->supplier : '');
    }
}
