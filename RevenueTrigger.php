<?php

namespace Components\Account\Revenue;

use Components\Account\AbstractTrigger;
use Constants\AccountGeneral;
use Constants\AccountType;
use Constants\TransactionCategory;
use Constants\TriggerOperator;
use Models\Trigger\Trigger;

/**
 * Class RevenueTrigger
 * @package Components\Account\Revenue
 */
class RevenueTrigger extends AbstractTrigger
{
    /**
     * Creates account trigger.
     *
     * @param $config
     * @param $parent_config
     * @param $pfac_data
     *
     * @throws \Core\Exceptions\ApiException
     */
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        $funding_level = (bool) ($config['config']['funding_flag'] ?? false);
        if ($funding_level) {
            $delay_funding_flag = (bool) ($config['config']['delay_funding_flag'] ?? false);
            if ($delay_funding_flag) {
                $this->createSidewaysTrigger(AccountType::DELAY_FUNDING_ACCOUNT, $config, TransactionCategory::FUNDING);
            }
            else {
                // create Nacha trigger.
                //TODO operator currently is GT 0, maybe (depending if we are allowed to debit?) the operator should be NEQ 0
                $this->createNachaTrigger($config, TransactionCategory::FUNDING);

                //TODO temp, after discussion with Adrian, Dogak, Tomasz, Minhaz on 28/09/19, added second nacha trigger for negative values, so it can be easily removed again
                $this->createNachaTrigger($config, TransactionCategory::FUNDING, TriggerOperator::LT);
            }
        }
        else {
            $this->createRollupTrigger($config, $parent_config, TriggerOperator::NEQ);
        }
    }

    /**
     * @inheritDoc
     * if the node is held/suspended, we need to move to that respective account INSTEAD of NACHA, else just settle to NACHA
     */
    protected function executeNachaTrigger(Trigger $trigger)
    {
        //move all balance from source to target account
        $move_amount = $this->sourceAccount->getBalance();

        //if there is no money to move, just return
        if ($move_amount == 0) {
            return 0;
        }
        //else check if owner is held/suspended
        $ownerNode = $this->sourceAccount->getAccountOwnerNode();

        //if the node is held/suspended, we need to move to that respective account INSTEAD of NACHA
        if (in_array($ownerNode->hold_suspend_flag, [AccountGeneral::AUTO_HOLD, AccountGeneral::HOLD])) {
            $holdAcc = $this->loadAccount($ownerNode, $this->sourceAccount->getCurrency(), AccountType::HOLD_ACCOUNT);
            $this->sourceAccount->moveAmount($holdAcc, $move_amount, $trigger->transaction_category_id);
        }
        elseif ($ownerNode->hold_suspend_flag == AccountGeneral::SUSPEND) {
            $suspendAcc = $this->loadAccount($ownerNode, $this->sourceAccount->getCurrency(), AccountType::HOLD_ACCOUNT);
            $this->sourceAccount->moveAmount($suspendAcc, $move_amount, $trigger->transaction_category_id);
        }
        //else just move to NACHA
        else {
            $move_amount = parent::executeNachaTrigger($trigger);
        }

        //return amount moved, regardless of trigger
        return $move_amount;
    }
}