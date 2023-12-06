<?php

namespace Seablast\Seablast;

//use Webmozart\Assert\Assert;

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
        //todo named parameters? one array?
        $this->get = $get;
        $this->post = $post;
        $this->server = $server;
        $this->session = $session;
    }
}
