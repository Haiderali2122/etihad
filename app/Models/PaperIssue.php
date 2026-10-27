<?php

namespace App\Models;

use App\Enums\PaperIssueKind;
use Database\Factories\PaperIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paper leaving the godown: to an in-house machine, to an outside press, or back to the supplier.
 */
#[Fillable(['issued_on', 'kind', 'party_id', 'paper_type_id', 'quantity', 'reference', 'book_format_id', 'book_name', 'copies', 'issued_to', 'wastage_percent', 'note', 'created_by'])]
class PaperIssue extends Model
{
    /** @use HasFactory<PaperIssueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'kind' => PaperIssueKind::class,
            'quantity' => 'integer',
            'copies' => 'integer',
            'wastage_percent' => 'decimal:2',
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
     * @return BelongsTo<PaperType, $this>
     */
    public function paperType(): BelongsTo
    {
        return $this->belongsTo(PaperType::class);
    }

    /**
     * @return BelongsTo<BookFormat, $this>
     */
    public function bookFormat(): BelongsTo
    {
        return $this->belongsTo(BookFormat::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
