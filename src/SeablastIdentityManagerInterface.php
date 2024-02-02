<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use stdClass;

/**
 * Definition of a minimal interface for an IdentityManager
 *
 * Usage: class IdentityManager implements SeablastIdentityManagerInterface
 */
interface SeablastIdentityManagerInterface
{
    public function __construct();
    public function getUser(): stdClass;
    public function isAuthenticated(): bool;
}
