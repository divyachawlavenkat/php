<?php

namespace Components\Account\FeeCollect;

use Components\Account\AbstractTrigger;
use Constants\AccountType;
use Constants\TransactionCategory;
use Models\Trigger\Trigger;

/**
 * Class FeeCollectTrigger
 * @package Components\Account\FeeCollect
 */
class FeeCollectTrigger extends AbstractTrigger
{
    /**
     * @inheritDoc
     */
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        // if it is billing level (where Fee collect is created), collect the money by NACHA and move that amount to FEE Account
        $billing_level = (bool) ($config['config']['billing_flag'] ?? 0);
        if ($billing_level) {
            //we have TWO triggers on this account. The first moves the money to fee account, the second moves the "out" (the same amount) to NACHA
            $this->createSidewaysTrigger(AccountType::FEE_ACCOUNT, $config, TransactionCategory::FEE);
            $this->createNachaTrigger($config, TransactionCategory::FEE);
        }
        //else do nothing, gross fee and service fee already roll up
    }

    /**
     * @inheritDoc
     */
    protected function getSettlementAmount()
    {
        //Nacha trigger should move OUT amount, as balance is already gone due to FEE trigger
        //note that this only works because we only run this trigger on billing level, where money moves sideways
        //on other levels, out might include lower level outs or rollups from the respective node
        $this->sourceAccount->getCurrentAccountInfo()->getTotalOut();
    }

    /**
     * @inheritDoc
     */
    protected function createTransactionUponSettlementOutput()
    {
        // we will not create a transaction again, since money has been moved out to Fee account and transaction has been created there.
        return false;
    }
}