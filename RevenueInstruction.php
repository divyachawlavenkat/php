<?php

namespace Components\Account\Revenue;

use Components\Account\AbstractInstruction;

/**
 * Class RevenueInstruction
 * @package Components\Account\Revenue
 */
class RevenueInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}