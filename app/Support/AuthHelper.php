<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthHelper
{
    public static function findUserByLogin(string $login): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($login)])
            ->orWhereRaw('LOWER(name) = ?', [strtolower($login)])
            ->first();
    }

    public static function attempt(string $login, string $password, bool $remember = false): bool
    {
        $user = self::findUserByLogin($login);

        if (! $user || ! $user->isActive() || ! Hash::check($password, $user->password)) {
            return false;
        }

        Auth::login($user, $remember);

        return true;
    }
}
