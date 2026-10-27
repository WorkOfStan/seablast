<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use Seablast\Interfaces\IdentityManagerInterface;

/** Database-free identity for the isolated HTTP fixture. */
class RequestContextIdentity implements IdentityManagerInterface
{
    public function __construct()
    {
        header('X-Identity-Resolved: 1');
    }

    /** @return int[] */
    public function getGroups(): array
    {
        return [3];
    }

    public function getRoleId(): int
    {
        return 2;
    }

    public function getUserId(): int
    {
        return 42;
    }

    public function isAuthenticated(): bool
    {
        return !empty($_SESSION['fixtureUser']);
    }
}
