<?php

namespace App\Support;

use App\Models\User;

class InstallService
{
    public static function isInstalled(): bool
    {
        return User::query()->exists();
    }
}
