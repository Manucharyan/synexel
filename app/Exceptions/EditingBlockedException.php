<?php

namespace App\Exceptions;

use RuntimeException;

class EditingBlockedException extends RuntimeException
{
    public function __construct(string $message = 'This action is blocked by an administrator.')
    {
        parent::__construct($message);
    }
}
