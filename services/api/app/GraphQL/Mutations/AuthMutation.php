<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthMutation
{
    public function register($_, array $args): array
    {
        $input = Validator::make($args['input'], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ])->validate();

        $user = User::create($input);

        return ['token' => $user->createToken('dashboard')->plainTextToken, 'user' => $user];
    }

    public function login($_, array $args): array
    {
        $input = Validator::make($args['input'], [
            'email' => ['required', 'email'], 'password' => ['required', 'string'],
        ])->validate();
        $user = User::where('email', strtolower($input['email']))->first();
        if (! $user || ! Hash::check($input['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid.']);
        }

        return ['token' => $user->createToken('dashboard')->plainTextToken, 'user' => $user];
    }

    public function logout($_, array $args): bool
    {
        request()->user()->currentAccessToken()?->delete();

        return true;
    }
}
