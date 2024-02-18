<?php

declare(strict_types=1);

namespace Seablast\Seablast;

class Superglobals
{
    use \Nette\SmartObject;

    /** @var mixed[] */
    public $get;
    /** @var mixed[] */
    public $post;
    /** @var mixed[] */
    public $server;
    /** @var mixed[] */
    public $session;

    /**
     * May be setup either with superglobals for production or with arbitrary constants to test variants
     * @param array<mixed> $get
     * @param array<mixed> $post
     * @param array<mixed> $server
     * @param array<mixed> $session
     */
    public function __construct(array $get = [], array $post = [], array $server = [], array $session = [])
    {
        $this->get = $get;
        $this->post = $post;
        $this->server = $server;
        $this->session = $session;
    }

    /**
     * Injection necessary in case of late session start
     * @param array<mixed> $session
     * @return void
     */
    public function setSession(array $session = []): void
    {
        $this->session = $session;
    }
}
