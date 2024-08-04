<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\SeablastFlag;

class SeablastFlagTest extends TestCase
{
    public function testActivateSetsFlag()
    {
        $flag = new SeablastFlag();
        $flag->activate('testFlag');

        $this->assertTrue($flag->status('testFlag'));
    }

    public function testDeactivateUnsetsFlag()
    {
        $flag = new SeablastFlag();
        $flag->activate('testFlag');
        $this->assertTrue($flag->status('testFlag'));

        $flag->deactivate('testFlag');
        $this->assertFalse($flag->status('testFlag'));
    }

    public function testStatusReturnsFalseForUnsetFlag()
    {
        $flag = new SeablastFlag();

        $this->assertFalse($flag->status('nonExistentFlag'));
    }

    public function testActivateReturnsSelf()
    {
        $flag = new SeablastFlag();
        $result = $flag->activate('testFlag');

        $this->assertSame($flag, $result);
    }

    public function testDeactivateReturnsSelf()
    {
        $flag = new SeablastFlag();
        $result = $flag->deactivate('testFlag');

        $this->assertSame($flag, $result);
    }
}
