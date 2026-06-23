<?php

namespace App\Domain\Spreadsheet\Enums;

enum SharePermission: string
{
    case Read = 'read';
    case Write = 'write';

    public function canWrite(): bool
    {
        return $this === self::Write;
    }

    public function label(): string
    {
        return match ($this) {
            self::Read => 'View only',
            self::Write => 'Can edit',
        };
    }
}
