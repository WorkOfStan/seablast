<?php

declare(strict_types=1);

namespace Seablast\Seablast\Exceptions;

use Exception;

final class MissingTemplateException extends Exception
{
    /** @api */
    public function __construct(string $message = 'Unknown internal error.')
    {
        parent::__construct($message);
    }
}
