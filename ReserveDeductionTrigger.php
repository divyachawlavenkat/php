<?php

namespace Components\Account\ReserveDeduction;

use Components\Account\AbstractTrigger;
use Constants\HierarchyType;
use Constants\TransactionCategory;
use Constants\TriggerOperator;

/**
 * Class ReserveDeductionTrigger
 * @package Components\Account\ReserveDeduction
 */
class ReserveDeductionTrigger extends AbstractTrigger
{

    /**
     * Creates triggers.
     * @param array $config
     * @param array $parent_config
     * @param array $pfac_data
     * @throws \Core\Exceptions\ApiException
     */
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        $funding_level = (bool) ($config['config']['funding_flag'] ?? 0);

        if ($funding_level && $this->sourceAccount->getHierarchyType() == HierarchyType::PFAC) {
            //send to NACHA for pfac
            $this->createNachaTrigger($config, TransactionCategory::RESERVE_DEDUCTION);
        }
        else {
            //rollup all the wayto pfac funding from merchant (as this money was deducted)
            $this->createRollupTrigger($config, $parent_config);
        }
    }

    // For nacha trigger, it will be the normal process
}