<?php

namespace Components\Account\RollingReserve;

use Components\Account\AbstractTrigger;
use Constants\AccountType;
use Constants\EntityType;
use Constants\TransactionCategory;
use Constants\TriggerOperator;
use Core\Exceptions\ApiException;
use Models\Account\RollingReserve\RollingReserveAccountInfo;
use Models\Merchant;
use Models\MerchantSubGroup;
use Models\Outlet;
use Models\Trigger\Trigger;

/**
 * Class RollingReserveInstruction
 * @package Components\Account\RollingReserve
 */
class RollingReserveTrigger extends AbstractTrigger
{
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        //roll up to funding level if that is where the reserve is taken
        //then use a NACHA trigger to move money into CARS and ReserveRelease, checking account info
        $reserve_level = (bool) ($config['config']['reserve_flag'] ?? 0);
        if (!$reserve_level) {
            //rollup to reserve level
            $this->createRollupTrigger($config, $parent_config);
        }
        else {
            //on reserve level: trigger to ReserveRelease, and special NACHA trigger to move today's IN to CARS accounts

            //trigger every day to reserve release. Processing will check transactions in/out.
            $this->createSidewaysTrigger(AccountType::RESERVE_RELEASE_ACCOUNT, $config, TransactionCategory::RESERVE_RELEASE);

            //note that the Trigger Operator is GTE as this should also run on balance 0 (IN amount might have been released on the same day)
            $this->createNachaTrigger($config, TransactionCategory::RESERVE, TriggerOperator::GTE);
        }
    }

    /**
     * When moving from Rolling Reserve to release, we need to follow special logic.
     * Otherwise, use standard implementation of parent.
     *
     * {@inheritDoc}
     */
    protected function executeTrigger(Trigger $trigger)
    {
        //if the target is reserve release, we need special logic to only release money that came in the given delay days ago
        if ($trigger->target_account_type == AccountType::RESERVE_RELEASE_ACCOUNT) {
            return $this->processRollingReserveRelease($trigger);
        }
        else { //else we follow standard process (this is for a rollup trigger)
            return parent::executeTrigger($trigger);
        }
    }

    /**
     * When moving money into Rolling Reserve, it needs to move from IPS to CARS (Collateral account).
     * For this we use a nacha trigger, but only want to move the additional money coming in today.
     * The remaining balance is already on CARS, or if money was taken out, it was taken from CARS.
     * We also do not want to debit the Rolling Reserve account upon said trigger.
     *
     * {@inheritDoc}
     */
    protected function executeNachaTrigger(Trigger $trigger)
    {
        /* @var \Models\Account\RollingReserve\RollingReserveAccountInfo $accountInfo */
        $accountInfo = $this->sourceAccount->getCurrentAccountInfo();

        //check if account info was already moved to CARS (as this may be an older acc info if there was no new IN today)
        if ($accountInfo->moved_to_cars == 1) {
            return 0; //no money to move
        }

        //move all IN of the account info
        $move_amount = $accountInfo->total_in;

        //no need to call nacha if IN was 0
        if ($move_amount > 0) {
            $this->sourceAccount->createNachaSettlementAccountsEntry($move_amount);
        }

        //mark the account info as moved
        $accountInfo->moved_to_cars = 1;
        $accountInfo->save();

        //return amount moved
        return $move_amount;
    }

    /**
     * When moving Rolling Reserve to Reserve Release, keep checking the account info table to see all incoming transactions and their dates.
     * For all account info rows <= the funding delay days from now, mark them as processed and fund the combined amount (minus what was already debited for Reserve Deduction)
     *
     * @param Trigger $trigger
     *
     * @return float amount moved out
     * @throws \Core\Exceptions\ApiException
     */
    private function processRollingReserveRelease(Trigger $trigger)
    {
        //default move amount to 0
        $move_amount = 0;

        $ownerNode = $this->sourceAccount->getAccountOwnerNode();

        //load actual owner model, to get delay days - this will never be a pfac node
        switch ($ownerNode->entity_type) {
            case EntityType::OUTLET:
                /* @var Outlet $ownerModel */
                $ownerModel = $this->modelLoader->load(Outlet::class);
                break;
            case EntityType::SUB_MERCHANT_GROUP:
                /* @var MerchantSubGroup $ownerModel */
                $ownerModel = $this->modelLoader->load(MerchantSubGroup::class);
                break;
            case EntityType::MERCHANT:
                /* @var Merchant $ownerModel */
                $ownerModel = $this->modelLoader->load(Merchant::class);
                break;
            default:
                throw new ApiException(ApiException::SYSTEM_ERROR . 1301001);
        }

        $owner = $ownerModel->getById($ownerNode->entity_id);

        //date to be processed is today minus reserve_delay_days
        $date_to_process = date('Y-m-d', strtotime("- $owner->reserve_delay_days days"));

        //now load account infos that are not processed, and are as old as date_to_process or older.
        // getter should not throw exception, as could be that there is no info to be processed.
        /* @var \Models\Account\RollingReserve\RollingReserveAccountInfo $accountInfoModel */
        $accountInfoModel = $this->modelLoader->load(RollingReserveAccountInfo::class);
        $accountInfos = $accountInfoModel->getAccountInfosForProcessing($this->sourceAccount->getId(), $date_to_process, false);

        //we loop through account info here to find out what needs to get settled
        foreach ($accountInfos as $accountInfo) {
            //everything that came in on that day, minus everything that may have already been settled
            // (this is relevant for Rolling Reserve, but will more likely never happen on DelayFunding)
            $amount_to_settle = $accountInfo->total_in - $accountInfo->settled_amount;

            //increase settled amount by what is about to be settled, and flag the info as processed
            $accountInfo->settled_amount += $amount_to_settle;
            $accountInfo->processed_flag = 1;

            //increase move amount by this account info amount to settle
            $move_amount += $amount_to_settle;
        }


        //do not call if move amount is 0
        if ($move_amount != 0) {
            $this->targetAccount = $this->loadAccountById($trigger->target_account_id, $trigger->target_account_type);
            $this->sourceAccount->moveAmount($this->targetAccount, $move_amount, $trigger->transaction_category_id);
        }

        //after successfully moving money to NACHA settlement, loop through account infos again and save the changes
        foreach ($accountInfos as $accountInfo) {
            $accountInfo->save();
        }

        //return amount moved
        return $move_amount;
    }

}