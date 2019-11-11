<?php

namespace Components\Account\RejectAdjustment;

use Components\Account\AbstractTrigger;
use Constants\AccountGeneral;
use Constants\AccountType;
use Constants\TransactionCategory;
use Constants\TransactionType;
use Constants\TriggerOperator;
use Core\Exceptions\ApiException;
use Models\Account\RejectAdjustment\RejectAdjustmentAccountTransaction;
use Models\Trigger\Trigger;

/**
 * Class RejectAdjustmentTrigger
 * @package Components\Account\RejectAdjustment
 */
class RejectAdjustmentTrigger extends AbstractTrigger
{
    /**
     * {@inheritDoc}
     */
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        // Reject Adjustment is only created on funding level, move money to/from PFAC’s physical bank account (via NACHA)
        $funding_level = (bool) ($config['config']['funding_flag'] ?? 0);
        if ($funding_level) {
            $this->createNachaTrigger($config, TransactionCategory::FUNDING);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function createTransactionUponSettlementOutput()
    {
        //do not debit this account on settlement output, as we need to keep balance that PFAC is due to pay back OR get back on reprocess
        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function executeNachaTrigger(Trigger $trigger)
    {
        //only move transactions to NACHA which are not yet flagged as settled (this is handled by getSettlementAmount() override)
        parent::executeNachaTrigger($trigger);
    }

    /**
     * {@inheritDoc}
     */
    protected function getSettlementAmount()
    {
        //initialise amount as 0 and initialise pagination
        $move_amount = 0;
        $limit = 100;
        $offset = 0;

        /* @var \Models\Account\RejectAdjustment\RejectAdjustmentAccountTransaction $rejectAdjustmentTransactionModel  */
        $rejectAdjustmentTransactionModel = $this->modelLoader->load(RejectAdjustmentAccountTransaction::class);

        do {
            //FIXME: are we gonna have a memory problem with AUDIT here?

            $adjustmentTransactions = $rejectAdjustmentTransactionModel->getNonSettledTransactions($this->sourceAccount->getId(), $limit, $offset);
            $result_count = count($adjustmentTransactions);

            foreach ($adjustmentTransactions as $adjustmentTransaction) {
                // adjust move amount value
                if($adjustmentTransaction->transaction_type_id == TransactionType::CREDIT) {
                    $move_amount += $adjustmentTransaction->credit;
                }
                elseif($adjustmentTransaction->transaction_type_id == TransactionType::DEBIT) {
                    $move_amount -= $adjustmentTransaction->debit;
                }
                else {
                    //this should never happen
                    $this->logger->error("Reject Adjustment Account " . $adjustmentTransaction->reject_adjustment_account_id . " NACHA trigger had invalid transaction type " . $adjustmentTransaction->transaction_type_id);
                    throw new ApiException(ApiException::SYSTEM_ERROR, 1301001);
                }

                //mark transaction as settled
                $adjustmentTransaction->settled = $adjustmentTransaction::SETTLED;
                $adjustmentTransaction->save();
            }

            $offset += $result_count;
        }
        while($result_count == $limit);

        return $move_amount;
    }
}