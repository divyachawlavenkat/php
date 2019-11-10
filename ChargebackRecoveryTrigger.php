<?php

namespace Components\Account\ChargebackRecovery;

use Components\Account\AbstractTrigger;
use Constants\AccountGeneral;
use Constants\AccountType;
use Constants\HierarchyType;
use Constants\TransactionCategory;
use Constants\TriggerOperator;
use Models\Trigger\Trigger;

/**
 * Class ChargebackRecoveryTrigger
 * @package Components\Account\ChargebackRecovery
 */
class ChargebackRecoveryTrigger extends AbstractTrigger
{
    /**
     * {@inheritDoc}
     */
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        // Chargeback Recovery will write into NACHA on PFAC funding level
        $funding_level = (bool) ($config['config']['funding_flag'] ?? 0);
        if ($funding_level && $this->sourceAccount->getHierarchyType() == HierarchyType::PFAC) {
            $this->createNachaTrigger($config, TransactionCategory::CHARGEBACK);
        }
        else {
            //if not PFAC funding level, rollup till we hit it
            $this->createRollupTrigger($config, $parent_config, TriggerOperator::NEQ);
        }
    }
}