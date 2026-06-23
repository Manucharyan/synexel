<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class InstallService
{
    public static function isInstalled(): bool
    {
        try {
            if (! Schema::hasTable('users')) {
                return false;
            }

            return User::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
