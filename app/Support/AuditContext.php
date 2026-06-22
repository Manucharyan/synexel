<?php

namespace App\Support;

use App\Models\User;

class AuditContext
{
    private static ?User $user = null;

    private static ?string $ipAddress = null;

    private static ?string $userAgent = null;

    public static function set(?User $user, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        self::$user = $user;
        self::$ipAddress = $ipAddress;
        self::$userAgent = $userAgent ? mb_substr($userAgent, 0, 512) : null;
    }

    public static function clear(): void
    {
        self::$user = null;
        self::$ipAddress = null;
        self::$userAgent = null;
    }

    public static function user(): ?User
    {
        return self::$user;
    }

    public static function userId(): ?int
    {
        return self::$user?->id;
    }

    public static function ipAddress(): ?string
    {
        return self::$ipAddress;
    }

    public static function userAgent(): ?string
    {
        return self::$userAgent;
    }
}
