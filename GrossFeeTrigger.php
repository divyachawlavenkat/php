<?php

namespace Components\Account\GrossFee;

use Components\Account\AbstractTrigger;
use Constants\AccountType;
use Constants\TransactionCategory;

/**
 * Class GrossFeeTrigger
 * @package Components\Account\GrossFee
 */
class GrossFeeTrigger extends AbstractTrigger
{
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        // if it is billing level, move to Fee Collect Account, otherwise, roll up to parent.
        $billing_level = (bool) ($config['config']['billing_flag'] ?? 0);
        if ($billing_level) {
            $this->createSidewaysTrigger(AccountType::FEE_COLLECT_ACCOUNT, $config, TransactionCategory::FEE);
        }
        else {
            $this->createRollupTrigger($config, $parent_config);
        }
    }
}