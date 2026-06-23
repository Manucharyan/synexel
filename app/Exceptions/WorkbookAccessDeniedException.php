<?php

namespace App\Exceptions;

use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Models\Workbook;
use Exception;

class WorkbookAccessDeniedException extends Exception
{
    public function __construct(
        public readonly Workbook $workbook,
        public readonly SharePermission $required,
        string $message = 'Access denied.',
    ) {
        parent::__construct($message);
    }
}
