<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoleRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Role::allPermissions())],
        ];
    }

    /**
     * Don't let someone remove user management from their own role and lock themselves out.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Role|null $role */
                $role = $this->route('role');

                if (! $role || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $role->is_super && $this->user()->role_id === $role->id
                    && ! in_array('users.manage', $this->input('permissions', []), true)) {
                    $validator->errors()->add('permissions', __('You cannot remove user management from your own role.'));
                }
            },
        ];
    }
}
