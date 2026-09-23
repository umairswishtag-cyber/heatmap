<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthMutation
{
    public function register($_, array $args): array
    {
        $args['input']['email'] = strtolower(trim($args['input']['email'] ?? ''));

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
        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($input['email']))])->first();
        if (! $user || ! Hash::check($input['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid.']);
        }

        return ['token' => $user->createToken('dashboard')->plainTextToken, 'user' => $user];
    }

    public function requestPasswordReset($_, array $args): bool
    {
        $input = Validator::make($args, [
            'email' => ['required', 'email'],
        ])->validate();

        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($input['email']))])->first();

        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        // Keep this response identical for known and unknown email addresses.
        return true;
    }

    public function resetPassword($_, array $args): bool
    {
        $input = Validator::make($args['input'], [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ])->validate();

        $status = Password::reset($input, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return true;
    }

    public function logout($_, array $args): bool
    {
        request()->user()->currentAccessToken()?->delete();

        return true;
    }
}
