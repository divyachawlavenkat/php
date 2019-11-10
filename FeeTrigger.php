<?php

namespace Components\Account\Fee;

use Components\Account\AbstractTrigger;
use Constants\AccountType;
use Constants\HierarchyType;
use Constants\TransactionCategory;
use Constants\TriggerOperator;

/**
 * Class FeeTrigger
 * @package Components\Account\Fee
 */
class FeeTrigger extends AbstractTrigger
{
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        if ($this->sourceAccount->getHierarchyType() == HierarchyType::MERCHANT) {
            //for fee account on Merchant hierarchy, roll up to parent fee account (even to PFAC leaf node)
            $this->createRollupTrigger($config, $parent_config);
        }
        else {
            $funding_level = (bool) ($config['config']['funding_flag'] ?? 0);
            //if PFAC hierarchy and funding level, move from FEE to PFAC Revenue
            if($funding_level) {
                // TODO: currently this code will not be executed since the account creation does not include PFAC nodes.
                $this->createSidewaysTrigger(AccountType::PFAC_REVENUE_ACCOUNT, $config, TransactionCategory::PFAC_REVENUE, TriggerOperator::GT);
                // TODO: commission is configured on PFAC hierarchy when setting up PFAC's.
                // 1. trigger to move commission to PFAC node :
                // Not sure how to create this trigger now since commission account is not created.
            }
        }
    }
}