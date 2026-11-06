<?php

namespace App\Models;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\MachineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'model', 'purchase_price', 'status', 'notes'])]
class Machine extends Model
{
    /** @use HasFactory<MachineFactory> */
    use HasFactory, RecordsDeletion;

    protected function casts(): array
    {
        return [
            'type' => MachineType::class,
            'status' => MachineStatus::class,
            'purchase_price' => 'integer',
        ];
    }

    /**
     * @return HasMany<MachineRepair, $this>
     */
    public function repairs(): HasMany
    {
        return $this->hasMany(MachineRepair::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function deletionBlocker(): ?string
    {
        $used = $this->repairs()->exists() || WorkOrder::query()->where('machine_id', $this->id)->exists();

        return $used ? __(':code has repairs or work orders recorded against it, so it cannot be deleted.', ['code' => $this->code]) : null;
    }

    public function deletionLabel(): string
    {
        return trim($this->code.' '.$this->name);
    }
}
