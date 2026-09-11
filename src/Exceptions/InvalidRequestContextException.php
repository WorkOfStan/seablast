<?php

declare(strict_types=1);

namespace Seablast\Seablast\Exceptions;

/** Invalid forwarding metadata must be handled before sessions or application output. */
final class InvalidRequestContextException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Bad Request', 400);
    }
}
