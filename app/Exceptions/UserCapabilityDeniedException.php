<?php

namespace App\Exceptions;

use Exception;

class UserCapabilityDeniedException extends Exception
{
    public function __construct(string $message = 'Operation not permitted for your account.')
    {
        parent::__construct($message);
    }
}
