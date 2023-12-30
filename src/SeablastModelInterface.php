<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;
use stdClass;

/**
 * Definition of a model used by SeablastModel
 *
 * Usage: class AbcModel implements SeablastModelInterface
 */
interface SeablastModelInterface
{
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals);
    public function knowledge(): stdClass;
}
