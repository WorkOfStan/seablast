<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\Superglobals;

class SuperglobalsTest extends TestCase
{
    public function testConstructorInitializesSuperglobals()
    {
        $get = ['key1' => 'value1'];
        $post = ['key2' => 'value2'];
        $server = ['key3' => 'value3'];
        $session = ['key4' => 'value4'];

        $superglobals = new Superglobals($get, $post, $server, $session);

        $this->assertSame($get, $superglobals->get);
        $this->assertSame($post, $superglobals->post);
        $this->assertSame($server, $superglobals->server);
        $this->assertSame($session, $superglobals->session);
    }

    public function testConstructorInitializesEmptyArrays()
    {
        $superglobals = new Superglobals();

        $this->assertEmpty($superglobals->get);
        $this->assertEmpty($superglobals->post);
        $this->assertEmpty($superglobals->server);
        $this->assertEmpty($superglobals->session);
    }

    public function testSetSessionUpdatesSession()
    {
        $initialSession = ['key1' => 'value1'];
        $superglobals = new Superglobals([], [], [], $initialSession);

        $this->assertSame($initialSession, $superglobals->session);

        $newSession = ['key2' => 'value2'];
        $superglobals->setSession($newSession);

        $this->assertSame($newSession, $superglobals->session);
    }

    public function testSetSessionWithEmptyArray()
    {
        $initialSession = ['key1' => 'value1'];
        $superglobals = new Superglobals([], [], [], $initialSession);

        $this->assertSame($initialSession, $superglobals->session);

        $superglobals->setSession([]);

        $this->assertEmpty($superglobals->session);
    }
}
