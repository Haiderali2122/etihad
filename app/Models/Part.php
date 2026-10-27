<?php

namespace App\Models;

use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A machine part, e.g. rula (roller), chain, bearing.
 */
#[Fillable(['name', 'name_ur'])]
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory;

    /**
     * @return HasMany<MachineRepair, $this>
     */
    public function repairs(): HasMany
    {
        return $this->hasMany(MachineRepair::class);
    }
}
