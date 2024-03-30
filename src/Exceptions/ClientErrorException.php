<?php

declare(strict_types=1);

namespace Seablast\Seablast\Exceptions;

use Exception;

final class ClientErrorException extends Exception
{
    /** @api */
    public function __construct(string $message = 'Unknown client side error.')
    {
        parent::__construct($message);
    }
}
