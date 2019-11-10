<?php

namespace Components\Account\ChargebackRecovery;

use Components\Account\AbstractInstruction;

/**
 * Class ChargebackRecoveryInstruction
 * @package Components\Account\ChargebackRecovery
 */
class ChargebackRecoveryInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}