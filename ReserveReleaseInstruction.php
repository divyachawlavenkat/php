<?php

namespace Components\Account\ReserveRelease;

use Components\Account\AbstractInstruction;

/**
 * Class ReserveReleaseInstruction
 * @package Components\Account\ReserveRelease
 */
class ReserveReleaseInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}