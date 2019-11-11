<?php

namespace Components\Account\RollingReserve;

use Components\Account\AbstractInstruction;

/**
 * Class RollingReserveInstruction
 * @package Components\Account\RollingReserve
 */
class RollingReserveInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        //no instructions needed from FundingDelayAccount

        return false;
    }
}