<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $editing = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($editing)],
            'password' => [$editing ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'role_id' => ['required', 'exists:roles,id'],
            'locale' => ['required', 'in:en,ur'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Stop changes that would lock everyone out: deactivating yourself, or leaving no active owner.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User|null $editing */
                $editing = $this->route('user');

                if (! $editing || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($editing->is($this->user()) && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', __('You cannot deactivate your own account.'));
                }

                $staysOwner = $this->boolean('is_active') && Role::query()->whereKey($this->integer('role_id'))->value('is_super');

                if ($editing->isOwner() && $editing->is_active && ! $staysOwner && User::lastActiveOwner($editing)) {
                    $validator->errors()->add('role_id', __('This is the last active owner. Give another user the owner role first.'));
                }
            },
        ];
    }
}
