<?php

namespace Components\Account\InstructionalHold;

use Components\Account\AbstractInstruction;

/**
 * Class InstructionalHoldInstruction
 * @package Components\Account\InstructionalHold
 */
class InstructionalHoldInstruction extends AbstractInstruction
{

    public function createInstructions($config, $parent_config, $pfac_data)
    {
        // there is no instruction needed for this account
        return false;
    }
}