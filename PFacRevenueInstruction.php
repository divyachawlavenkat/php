<?php

namespace Components\Account\PFacRevenue;

use Components\Account\AbstractInstruction;

/**
 * Class PFacRevenueInstruction
 * @package Components\Account\PFacRevenue
 */
class PFacRevenueInstruction extends AbstractInstruction
{
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}