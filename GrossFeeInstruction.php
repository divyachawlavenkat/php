<?php

namespace Components\Account\GrossFee;

use Components\Account\AbstractInstruction;

/**
 * Class GrossFeeInstruction
 * @package Components\Account\GrossFee
 */
class GrossFeeInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}
