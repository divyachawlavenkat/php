<?php

namespace Components\Account\RejectAdjustment;

use Components\Account\AbstractInstruction;

/**
 * Class RejectAdjustmentInstruction
 * @package Components\Account\RejectAdjustment
 */
class RejectAdjustmentInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}