<?php

namespace Components\Account\MerchantFee;

use Components\Account\AbstractTrigger;
use Constants\AccountType;
use Constants\TransactionCategory;

/**
 * Class MerchantFeeTrigger
 * @package Components\Account\MerchantFee
 */
class MerchantFeeTrigger extends AbstractTrigger
{
    public function createTriggers($config, $parent_config, $pfac_data)
    {

        //create a trigger to move to Fee Account
        // if it is billing level, move to Fee Account, otherwise, roll up to parent.
        $billing_level = (bool) ($config['config']['billing_flag'] ?? 0);
        if ($billing_level) {
            $this->createSidewaysTrigger(AccountType::FEE_ACCOUNT, $config, TransactionCategory::FEE);
        }
        else {
            //TODO should we rollup here if we rollup on Fee already? or vice versa
            $this->createRollupTrigger($config, $parent_config);
        }
    }
}