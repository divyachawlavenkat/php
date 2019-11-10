<?php

namespace Components\Account\Chargeback;

use Components\Account\AbstractInstruction;
use Constants\AccountGeneral;
use Constants\AccountType;
use Constants\SettlementMethod;
use Constants\TransactionCategory;
use Core\Exceptions\ApiException;
use Models\Trigger\TriggerInstruction;

/**
 * Class ChargebackInstruction
 * @package Components\Account\Chargeback
 */
class ChargebackInstruction extends AbstractInstruction
{
    /**
     * Create instructions
     *
     * @param $config
     * @param $parent_config
     * @param $pfac_data
     *
     * @return bool
     * @throws \Core\Exceptions\ApiException
     */
    public function createInstructions($config, $parent_config, $pfac_data)
    {
        //only on Funding level!
        $funding_level = (bool) ($config['config']['funding_flag'] ?? 0);
        if ($funding_level) {
            if (isset($config['pfac']['managed_chargeback_payee']) && $config['pfac']['managed_chargeback_payee'] == AccountGeneral::CHARGE_BACK_PAYEE_MERCHANT) {  //Merchant Responsible
                if (isset($config['pfac']['managed_chargeback']) && $config['pfac']['managed_chargeback'] == '1') {  // we manages CB
                    if (isset($config['merchant']['settlement_method']) && $config['merchant']['settlement_method'] == SettlementMethod::NET_SETTLEMENT
                        && isset($config['merchant']['chargeback_settlement_method']) && $config['merchant']['chargeback_settlement_method'] != AccountGeneral::CHARGE_BACK_SETTLE_BY_NACHA) {
                        //If positive balance & if Net & if not allowed to debit Merchant Settlement Account via NACHA, instruct to balance out from Revenue account to PFAC Chargeback Recovery Account (insist if CB settlement method is 1, otherwise not insist);
                        if ($config['merchant']['chargeback_settlement_method'] == AccountGeneral::CHARGE_BACK_SETTLE_BY_NETTING) {  //insist
                            $this->instructRevenueToChargebackRecovery($config, TriggerInstruction::INSIST_ON);
                        }
                        else {   // not insist if try netting then NACHA
                            $this->instructRevenueToChargebackRecovery($config, TriggerInstruction::INSIST_OFF);
                        }
                    }
                    //else, it will be settled by NACHA to merchant's physical bank account (via trigger).
                } // else, do nothing.
            } // else, it is PFAC responsible, apply the CB to PFAC Chargeback Recovery by Trigger (not by instruction)

        }

        return true;
    }

    /**
     * Create instruction to Revenue account to move money to Chargeback Recovery Account
     *
     * @param $config
     * @param $insist_flag
     *
     * @return void
     * @throws ApiException
     */
    public function instructRevenueToChargebackRecovery($config, $insist_flag)
    {
        $instruction = new \Models\Trigger\TriggerInstruction();
        $instruction->trigger_account_hierarchy_type = $this->sourceAccount->getHierarchyType();
        $instruction->trigger_account_node_id = $this->sourceAccount->getAccountOwnerNode()->merchant_node_id;
        $instruction->trigger_account_id = $this->sourceAccount->getId();
        $instruction->trigger_account_type = $this->sourceAccount->getAccountType();
        $instruction->currency = $this->sourceAccount->getCurrency();
        $instruction->instruction_on = TriggerInstruction::ON_DAILY; //end of day process
        $instruction->instruction_type = TriggerInstruction::TYPE_DIRECTIVE; //from revenue to pfac revenue, but has to update its own balance
        $instruction->balance_sign = TriggerInstruction::BALANCE_POSITIVE; //1 - positive balance means chargebacks happened
        $instruction->insist_flag = $insist_flag; // insist, as we are not allowed to debit the merchant

        $instruction->priority = $this->getInstructionPriority(); //instruction processing order/sequence

        $currency = $this->sourceAccount->getCurrency();
        if (isset($config['config']['accounts'][$currency][AccountType::REVENUE_ACCOUNT . '_' . $currency])) {
            $target_account_id = $config['config']['accounts'][$currency][AccountType::REVENUE_ACCOUNT . '_' . $currency];
        }
        else {
            $this->logger->error('Unable to get account type (REVENUE_ACCOUNT) for instruction creation.');
            //'E_12-50-003' => 'Unable to get account type (%0%) for instruction creation.',
            throw new ApiException(ApiException::REQUEST_FAILED, 1250003, ["REVENUE_ACCOUNT"]);
        }

        $instruction->target_account_id = $target_account_id;
        $instruction->target_account_type = AccountType::REVENUE_ACCOUNT; // move from Revenue Account

        if (isset($config['config']['accounts'][$currency][AccountType::CHARGEBACK_RECOVERY_ACCOUNT . '_' . $currency])) {
            $cbk_recovery_account_id = $config['config']['accounts'][$currency][AccountType::CHARGEBACK_RECOVERY_ACCOUNT . '_' . $currency];
        }
        else {
            $this->logger->error('Unable to get account type (CHARGEBACK_RECOVERY_ACCOUNT) for instruction creation.');
            //'E_12-50-003' => 'Unable to get account type (%0%) for instruction creation.',
            throw new ApiException(ApiException::REQUEST_FAILED, 1250003, ["CHARGEBACK_RECOVERY_ACCOUNT"]);
        }

        $instruction->destination_account_id = $cbk_recovery_account_id; // move to Chargeback Recovery Account
        $instruction->destination_account_type = AccountType::CHARGEBACK_RECOVERY_ACCOUNT; // move to Chargeback Recovery Account

        if (!$this->isExistingInstruction($instruction)) {
            $instruction->save();
        }
    }

    /**
     * Override to CHARGEBACK as fixed transaction category
     *
     * @inheritDoc
     */
    protected function executeInstruction(TriggerInstruction $instruction, $transaction_category_id = NULL)
    {
        parent::executeInstruction($instruction, TransactionCategory::CHARGEBACK_MERCHANT);
    }
}