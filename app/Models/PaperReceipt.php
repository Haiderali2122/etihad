<?php

namespace App\Models;

use App\Models\Concerns\RequiresApproval;
use App\Support\PaperStock;
use Database\Factories\PaperReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One truck of paper unloaded at the godown, recorded from the numbered receipt slip.
 */
#[Fillable(['slip_no', 'received_at', 'party_id', 'paper_type_id', 'quantity', 'truck_no', 'godown_id', 'received_by', 'note', 'attachment', 'created_by'])]
class PaperReceipt extends Model
{
    /** @use HasFactory<PaperReceiptFactory> */
    use HasFactory, RequiresApproval;

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Where the truck was unloaded.
     *
     * @return BelongsTo<Godown, $this>
     */
    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }

    /**
     * @return BelongsTo<PaperType, $this>
     */
    public function paperType(): BelongsTo
    {
        return $this->belongsTo(PaperType::class);
    }

    /**
     * Stops the owner sending back a receipt whose paper has already been issued.
     */
    public function rejectionBlocker(): ?string
    {
        $available = app(PaperStock::class)->available($this->paper_type_id, $this->party_id);

        return $this->quantity > $available
            ? __('Paper from this slip has already been issued; reverse the issue first, then send the slip back.')
            : null;
    }

    public function approvalKind(): string
    {
        return __('Paper receipt');
    }

    public function approvalTitle(): string
    {
        return __(':qty :unit :paper from :party', [
            'qty' => number_format($this->quantity),
            'unit' => __($this->paperType->unit),
            'paper' => $this->paperType->name,
            'party' => $this->party->name,
        ]);
    }

    public function approvalDetail(): string
    {
        return __('Slip :no, truck :truck, :store', ['no' => $this->slip_no, 'truck' => $this->truck_no ?: '—', 'store' => $this->godown?->localName() ?? '—']);
    }

    public function approvalAmount(): ?int
    {
        return null;
    }

    public function approvalIcon(): string
    {
        return 'truck';
    }

    public function approvalUrl(): string
    {
        return route('paper-receipts.show', $this);
    }
}
