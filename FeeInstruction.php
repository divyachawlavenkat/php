<?php

namespace Components\Account\Fee;

use Components\Account\AbstractInstruction;

/**
 * Class FeeInstruction
 * @package Components\Account\Fee
 */
class FeeInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}