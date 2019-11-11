<?php

namespace Components\Account\InstructionalHold;

use Components\Account\AbstractTrigger;
use Constants\AccountType;
use Constants\TransactionCategory;

/**
 * Class InstructionalHoldTrigger
 * @package Components\Account\InstructionalHold
 */
class InstructionalHoldTrigger extends AbstractTrigger
{

    public function createTriggers($config, $parent_config, $pfac_data)
    {
        // we need to rollup instructional hold to the funding level
        $funding_level = (bool) ($config['config']['funding_flag'] ?? 0);
        if (!$funding_level) {
            $this->createRollupTrigger($config, $parent_config);
        }
        return false;
    }
}