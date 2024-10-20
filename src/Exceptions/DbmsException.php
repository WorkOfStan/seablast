<?php

declare(strict_types=1);

namespace Seablast\Seablast\Exceptions;

use Exception;

final class DbmsException extends Exception
{
    /** @api */
    public function __construct(
            string $message = 'Unknown database management error.',
            int $code = 0,
            ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
