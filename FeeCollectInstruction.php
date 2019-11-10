<?php

namespace Components\Account\FeeCollect;

use Components\Account\AbstractInstruction;

/**
 * Class FeeCollectInstruction
 * @package Components\Account\FeeCollect
 */
class FeeCollectInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}