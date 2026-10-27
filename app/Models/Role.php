<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of permissions, e.g. Owner, Manager, Store keeper.
 * A "full access" role passes every permission check and cannot be deleted.
 */
#[Fillable(['name', 'description', 'permissions', 'is_super'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * What the office manager could do before roles were editable: everything except owner controls.
     *
     * @var list<string>
     */
    public const ManagerPermissions = [
        'paper.view', 'paper.receive', 'paper.issue', 'paper.types', 'godowns.manage',
        'purchases.view', 'purchases.create', 'inventory.issue', 'inventory.manage', 'parties.manage',
        'expenses.view', 'expenses.create',
        'machines.view', 'machines.manage', 'machines.repair',
        'books.view', 'books.manage',
        'orders.view', 'orders.manage', 'contractors.manage', 'plates.manage', 'dispatch.manage', 'buyers.manage', 'waste.manage',
        'staff.view', 'staff.manage', 'attendance.manage', 'advances.create', 'payroll.prepare',
        'reports.view',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_super' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function allows(string $permission): bool
    {
        return $this->is_super || in_array($permission, $this->permissions ?? [], true);
    }

    /**
     * Every permission key defined in config/permissions.php.
     *
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return collect(config('permissions'))->flatMap(fn (array $group) => array_keys($group))->values()->all();
    }
}
