<?php

namespace Components\Account\Chargeback;

use Components\Account\AbstractTrigger;
use Constants\AccountGeneral;
use Constants\AccountType;
use Constants\TransactionCategory;
use Constants\TriggerOperator;
use Core\Exceptions\ApiException;
use Models\Trigger\Trigger;

/**
 * Class ChargebackTrigger
 * @package Components\Account\Chargeback
 */
class ChargebackTrigger extends AbstractTrigger
{
    /**
     * create triggers
     *
     *
     * @param $config
     * @param $parent_config
     * @param $pfac_data
     *
     * @return bool
     * @throws \Core\Exceptions\ApiException
     */
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        $funding_level = (bool) ($config['config']['funding_flag'] ?? 0);
        if ($funding_level) {
            if (isset($config['pfac']['managed_chargeback']) && $config['pfac']['managed_chargeback'] == '1') {  //we managed CB
                if (isset($config['pfac']['managed_chargeback_payee']) && $config['pfac']['managed_chargeback_payee'] == AccountGeneral::CHARGE_BACK_PAYEE_PFAC) {
                    //if PFAC is responsible for the Charge Back, apply charge back to Chargeback Recovery Account (via a trigger)
                    // trigger for CB balance (can be positive or negative)
                    $this->createSidewaysTrigger(AccountType::CHARGEBACK_RECOVERY_ACCOUNT, $config, TransactionCategory::CHARGEBACK_PFAC, TriggerOperator::NEQ);
                }
                else {  // merchant is responsible for charge back
                    //negative balance, or Gross, or allow to debit merchant: in the daily trigger it will be settled by NACHA (to keep revenue and CB separate).
                    //NOTE that there is a scenario where both this AND an instruction get created. In this case, instruction will only happen on positive balance, so this trigger would take care of negative (or leftover) balance
                    // if it is "net if possible", there is an instruction in place with insist flag off (in which case the leftover amounts will be debited from merchant)
                    //When we write NACHA for merchant, we still need to impact "Chargeback Recovery"
                    // CREATE normal side way trigger to CHARGEBACK RECOVERY, then when executing this trigger, write NACHA entry to debit/credit Merchant
                    $this->createSidewaysTrigger(AccountType::CHARGEBACK_RECOVERY_ACCOUNT, $config, TransactionCategory::CHARGEBACK_MERCHANT, TriggerOperator::NEQ);
                }
            } // else: we don't manage CB --> do nothing
        }
        else { // roll up to parent node
            $this->createRollupTrigger($config, $parent_config, TriggerOperator::NEQ);
        }

        return true;
    }

    protected function executeTrigger(Trigger $trigger)
    {
        if ($trigger->transaction_category_id == TransactionCategory::CHARGEBACK_PFAC) {
            return parent::executeTrigger($trigger);
        }
        elseif ($trigger->transaction_category_id == TransactionCategory::CHARGEBACK_MERCHANT) {
            // if it is the trigger to move chargeback amount to PFAC (not via NETTING instruction), it needs to generate settlement output beside the trigger execution.
            $move_amount = parent::executeTrigger($trigger);

            // the sign of the amount is reverse of the amount moved to Chargeback Recovery. i.e, if it is positive, it means it needs to debit merchant in NACHA.
            $this->sourceAccount->createNachaSettlementAccountsEntry(-$move_amount);

            return $move_amount;
        }
        else {
            //rollup trigger
            return parent::executeTrigger($trigger);
        }
    }

    protected function flipAmount($trigger)
    {
        //flip if PFAC responsible, as positive (chargeback) means debit PFAC
        if ($trigger->transaction_category_id == TransactionCategory::CHARGEBACK_PFAC) {
            return true;
        }
        else {
            return parent::flipAmount($trigger);
        }
    }
}