<?php

declare(strict_types=1);

namespace Seablast\Seablast;

/**
 * The minimal interface for an IdentityManager.
 *
 * Usage: class IdentityManager implements IdentityManagerInterface
 */
interface IdentityManagerInterface
{
    public function getRoleId(): int;
    public function isAuthenticated(): bool;
}
