<?php

namespace Components\Account\RejectReprocess;

use Components\Account\AbstractInstruction;

/**
 * Class RejectReprocessInstruction
 * @package Components\Account\RejectReprocess
 */
class RejectReprocessInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}