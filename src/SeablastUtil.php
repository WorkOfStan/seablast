<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Interfaces\IdentityManagerInterface;
use Webmozart\Assert\Assert;

class SeablastUtil
{
    use \Nette\SmartObject;

    /**
     * Handle user authentication and store relevant information in the configuration object.
     *
     * Call only if ($this->identity->isAuthenticated()).
     *
     * @param SeablastConfiguration $configuration
     * @param IdentityManagerInterface $identity
     * @return void
     */
    public static function handleUserAuthentication(
        SeablastConfiguration $configuration,
        IdentityManagerInterface $identity
    ): void {
        $configuration->flag->activate(SeablastConstant::FLAG_USER_IS_AUTHENTICATED);
        // Save the current user's role, id and group list into the configuration object
        Assert::methodExists($identity, 'getRoleId');
        Assert::methodExists($identity, 'getUserId');
        Assert::methodExists($identity, 'getGroups');
        $configuration->setInt(SeablastConstant::USER_ROLE_ID, $identity->getRoleId());
        $configuration->setInt(SeablastConstant::USER_ID, $identity->getUserId());
        $configuration->setArrayInt(SeablastConstant::USER_GROUPS, $identity->getGroups());
    }

    /**
     * If string start with prefix, remove it.
     *
     * @param string $string
     * @param string $prefix
     * @return string
     */
    public static function removePrefix(string $string, string $prefix): string
    {
        return (substr($string, 0, strlen($prefix)) === $prefix) ? substr($string, strlen($prefix)) : $string;
    }

    /**
     * If string ends with suffix, remove it.
     *
     * @param string $string
     * @param string $suffix
     * @return string
     */
    public static function removeSuffix(string $string, string $suffix): string
    {
        return (substr($string, -strlen($suffix)) === $suffix)
            ? substr($string, 0, strlen($string) - strlen($suffix)) : $string;
    }
}
