<?php

namespace App\Domain\Accounting;

use App\Domain\DomainException;
use App\Models\LedgerTransaction;

class DuplicatePostingException extends DomainException
{
    public function __construct(string $message, public ?LedgerTransaction $existing = null)
    {
        parent::__construct($message);
    }
}
