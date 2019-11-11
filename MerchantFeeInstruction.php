<?php

namespace Components\Account\MerchantFee;

use Components\Account\AbstractInstruction;

/**
 * Class MerchantFeeInstruction
 * @package Components\Account\MerchantFee
 */
class MerchantFeeInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}