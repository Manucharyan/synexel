<?php

namespace App\Domain\Spreadsheet\Enums;

enum AuditOutcome: string
{
    case Success = 'success';
    case Denied = 'denied';
    case Failed = 'failed';
}
