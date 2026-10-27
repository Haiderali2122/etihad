<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $roleId = $request->integer('role') ?: null;

        return view('users.index', [
            'users' => User::query()->with('role')
                ->when($roleId, fn ($q) => $q->where('role_id', $roleId))
                ->orderByDesc('is_active')->orderBy('name')
                ->paginate(25)->withQueryString(),
            'roles' => Role::query()->withCount('users')->orderByDesc('is_super')->orderBy('name')->get(),
            'roleId' => $roleId,
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(['is_active' => true, 'locale' => 'en']));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('users.index')->with('status', __('User :name added.', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        return $this->form($user);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated() + ['is_active' => $request->boolean('is_active')];

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', __('User :name saved.', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $blocker = match (true) {
            $user->is($request->user()) => __('You cannot delete your own account.'),
            $user->isOwner() && User::lastActiveOwner($user) => __('This is the last active owner. Give another user the owner role first.'),
            $user->cashFloats()->exists() || $user->expenses()->exists() => __(':name has cash records. Deactivate the account instead so the history stays.', ['name' => $user->name]),
            default => null,
        };

        if ($blocker) {
            return back()->withErrors(['user' => $blocker]);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', __('User :name deleted.', ['name' => $user->name]));
    }

    private function form(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'roles' => Role::query()->orderByDesc('is_super')->orderBy('name')->get(),
        ]);
    }
}
