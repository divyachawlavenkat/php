<?php

namespace Components\Account\PFacRevenue;

use Components\Account\AbstractTrigger;
use Constants\TransactionCategory;

/**
 * Class PFacRevenueTrigger
 * @package Components\Account\PFacRevenue
 */
class PFacRevenueTrigger extends AbstractTrigger
{
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        // PFAC Revenue is only created on funding level, move money to PFAC’s physical bank account (via NACHA)
        $funding_level = (bool) ($config['config']['funding_flag'] ?? 0);
        if ($funding_level) {
            $this->createNachaTrigger($config, TransactionCategory::FUNDING);
        }
    }
}