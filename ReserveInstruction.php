<?php

namespace Components\Account\Reserve;

use Components\Account\AbstractInstruction;

/**
 * Class ReserveInstruction
 * @package Components\Account\Reserve
 */
class ReserveInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        //No instruction need as it will be manual money move
        return false;
    }

}