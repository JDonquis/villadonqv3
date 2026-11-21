<?php

namespace App\Exceptions;

use RuntimeException;

class BncException extends RuntimeException
{
    public function __construct(
        string $message,
        public ?string $bncCode = null,
        public array $context = []
    ) {
        parent::__construct($message);
    }
}
