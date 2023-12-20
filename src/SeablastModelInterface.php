<?php

namespace Seablast\Seablast;

use Seablast\Seablast\SeablastConfiguration;
use stdClass;

/**
 * Definition of a model used by SeablastModel
 *
 * Usage: class AbcModel implements SeablastModelInterface
 */
interface SeablastModelInterface
{
    public function __construct(SeablastConfiguration $configuration);
    public function getParameters(): stdClass;
}
