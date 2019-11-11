<?php

namespace Components\Account\ReserveRelease;

use Components\Account\AbstractTrigger;
use Constants\TransactionCategory;
use Constants\TriggerOperator;

/**
 * Class ReserveReleaseTrigger
 * @package Components\Account\ReserveRelease
 */
class ReserveReleaseTrigger extends AbstractTrigger
{

    public function createTriggers($config, $parent_config, $pfac_data)
    {
        $funding_level = (bool) ($config['config']['funding_flag'] ?? 0);

        if ($funding_level) {
            //send to NACHA
            $this->createNachaTrigger($config, TransactionCategory::RESERVE_RELEASE);
        }
        else {
            //rollup to funding (we might be taken reserve on outlet level)
            $this->createRollupTrigger($config, $parent_config);
        }
    }

    // For nacha trigger, it will be the normal process
}