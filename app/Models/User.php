<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role_id', 'locale', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Holds a full-access role (the owner).
     */
    public function isOwner(): bool
    {
        return (bool) $this->role?->is_super;
    }

    /**
     * True when $user is the only active owner, so removing their owner access would lock everyone out.
     */
    public static function lastActiveOwner(self $user): bool
    {
        return ! static::query()->active()->whereKeyNot($user->id)
            ->whereHas('role', fn (Builder $q) => $q->where('is_super', true))
            ->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && (bool) $this->role?->allows($permission);
    }

    /**
     * @return HasMany<CashFloat, $this>
     */
    public function cashFloats(): HasMany
    {
        return $this->hasMany(CashFloat::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Cash given by the owner minus every approved or waiting spend.
     */
    public function cashInHand(): int
    {
        return (int) $this->cashFloats()->sum('amount')
            - (int) $this->expenses()->notRejected()->sum('amount');
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($words[0] ?? '', 0, 1);
        $last = count($words) > 1 ? mb_substr(end($words), 0, 1) : mb_substr($words[0] ?? '', 1, 1);

        return mb_strtoupper($first.$last);
    }
}
