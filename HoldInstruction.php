<?php

namespace Components\Account\Hold;

use Components\Account\AbstractInstruction;

/**
 * Class HoldInstruction
 * @package Components\Account\Hold
 */
class HoldInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}