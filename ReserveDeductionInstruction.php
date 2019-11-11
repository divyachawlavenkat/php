<?php

namespace Components\Account\ReserveDeduction;

use Components\Account\AbstractInstruction;

/**
 * Class ReserveDeductionInstruction
 * @package Components\Account\ReserveDeduction
 */
class ReserveDeductionInstruction extends AbstractInstruction
{

    /**
     * Creates instructions with specified config.
     *
     * @param $config
     * @param $parent_config
     * @param $pfac_data
     *
     * @return mixed
     */
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        return false;
    }
}