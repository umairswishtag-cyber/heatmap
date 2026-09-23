<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function update(User $user, array $attributes): User
    {
        if (isset($attributes['email'])) {
            $attributes['email'] = mb_strtolower(trim($attributes['email']));
        }

        $input = Validator::make($attributes, [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'contact' => ['sometimes', 'nullable', 'string', 'max:120'],
            'avatarUrl' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'currentPassword' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:10', 'confirmed'],
        ])->validate();

        if (! empty($input['password'])) {
            if (empty($input['currentPassword']) || ! Hash::check($input['currentPassword'], $user->password)) {
                throw ValidationException::withMessages([
                    'currentPassword' => ['The current password is incorrect.'],
                ]);
            }
        }

        $user->fill(array_filter([
            'name' => $input['name'] ?? null,
            'email' => isset($input['email']) ? mb_strtolower($input['email']) : null,
            'contact' => array_key_exists('contact', $input) ? $input['contact'] : null,
            'avatar_url' => array_key_exists('avatarUrl', $input) ? $input['avatarUrl'] : null,
            'password' => $input['password'] ?? null,
        ], fn ($value, $key) => array_key_exists($key, ['name' => true, 'email' => true, 'contact' => true, 'avatar_url' => true, 'password' => true]) && $value !== null, ARRAY_FILTER_USE_BOTH));

        if (array_key_exists('contact', $input) && $input['contact'] === null) {
            $user->contact = null;
        }
        if (array_key_exists('avatarUrl', $input) && $input['avatarUrl'] === null) {
            $user->avatar_url = null;
        }

        $user->save();

        return $user->refresh();
    }
}
