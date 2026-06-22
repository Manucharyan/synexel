<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function create(
        string $name,
        string $email,
        string $password,
        UserRole $role = UserRole::User,
        bool $active = true,
    ): User {
        if (User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email is already registered.']);
        }

        if (User::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => 'This username is already taken.']);
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function createAdmin(string $name, string $email, string $password): User
    {
        return $this->create($name, $email, $password, UserRole::Admin);
    }

    public function setActive(User $user, bool $active): User
    {
        if ($user->isAdmin() && ! $active && $this->adminCount() <= 1) {
            throw ValidationException::withMessages([
                'is_active' => 'Cannot deactivate the only administrator.',
            ]);
        }

        $user->update(['is_active' => $active]);

        return $user->fresh();
    }

    public function delete(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($target->isAdmin() && $this->adminCount() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete the only administrator.',
            ]);
        }

        $target->tokens()->delete();
        $target->delete();
    }

    public function adminCount(): int
    {
        return User::query()->where('role', UserRole::Admin->value)->count();
    }
}
