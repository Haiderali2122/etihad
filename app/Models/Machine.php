<?php

namespace App\Models;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use Database\Factories\MachineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'model', 'purchase_price', 'status', 'notes'])]
class Machine extends Model
{
    /** @use HasFactory<MachineFactory> */
    use HasFactory;

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
}
