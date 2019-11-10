<?php

namespace Components\Account\DelayFunding;

use Components\Account\AbstractInstruction;

/**
 * Class DelayFundingInstruction
 * @package Components\Account\DelayFunding
 */
class DelayFundingInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        //no instructions needed from FundingDelayAccount
        return false;
    }
}