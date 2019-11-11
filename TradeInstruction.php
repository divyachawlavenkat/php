<?php

namespace Components\Account\Trade;

use Components\Account\AbstractInstruction;
use Core\Exceptions\ApiException;

/**
 * Class TradeInstruction
 * @package Components\Account\Trade
 */
class TradeInstruction extends AbstractInstruction
{
    /**
     * Create Instructions.
     *
     * @param $config
     * @param $parent_config
     * @param $pfac_data
     *
     * @return bool
     * @throws ApiException
     */
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        // no instructions.
        return false;
    }

    public function processInstruction($instruction)
    {
        //no implementation needed, as no instructions here
        return false;
    }
}