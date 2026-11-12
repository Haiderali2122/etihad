<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Orders staff for the payday screens: each foreman followed by his team, then everyone not under a foreman.
 * A foreman's own "already taken" is the cash he was given less what his team took (what he kept).
 */
class ForemanTeams
{
    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, Employee>
     */
    public static function ordered(Collection $employees): Collection
    {
        $foremen = $employees->where('is_foreman', true)->sortBy('name');
        $placed = collect();

        foreach ($foremen as $foreman) {
            $placed->push($foreman);
            $employees->where('foreman_id', $foreman->id)->where('is_foreman', false)->sortBy('name')->each(fn (Employee $e) => $placed->push($e));
        }

        return $placed->concat($employees->whereNotIn('id', $placed->pluck('id'))->sortBy('name'))->values();
    }

    /**
     * What each foreman kept: cash given less the kharcha his team took. Negative when the team took more.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  array<int|string, int>  $cash  foreman id => cash given
     * @param  array<int|string, int>  $taken  employee id => kharcha taken
     * @return array<int, int> foreman id => kept
     */
    public static function kept(Collection $employees, array $cash, array $taken): array
    {
        return $employees->where('is_foreman', true)->mapWithKeys(fn (Employee $f) => [
            $f->id => (int) ($cash[$f->id] ?? 0) - (int) $employees->where('foreman_id', $f->id)->where('is_foreman', false)->sum(fn (Employee $e) => (int) ($taken[$e->id] ?? 0)),
        ])->all();
    }
}
