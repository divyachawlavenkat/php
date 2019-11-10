<?php

namespace Components\Account\DelayFunding;

use Components\Account\AbstractTrigger;
use Constants\AccountGeneral;
use Constants\AccountType;
use Constants\EntityType;
use Constants\TransactionCategory;
use Core\Exceptions\ApiException;
use Models\Account\DelayFunding\DelayFundingAccountInfo;
use Models\Merchant;
use Models\MerchantSubGroup;
use Models\NationalHoliday;
use Models\Outlet;
use Models\Trigger\Trigger;

/**
 * Class DelayFundingTrigger
 * @package Components\Account\DelayFunding
 */
class DelayFundingTrigger extends AbstractTrigger
{
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        //only create the trigger if this is the funding level (as we need to be extra careful with rollup account infos..)
        $delay_funding_flag = (bool) ($config['config']['delay_funding_flag'] ?? false);
        $funding_flag = (bool) ($config['config']['funding_flag'] ?? false);

        if ($delay_funding_flag && $funding_flag) {
            //create output to NACHA, processing will check old transactions to process after delay
            $this->createNachaTrigger($config, TransactionCategory::FUNDING);
        }

        return true;
    }

    /**
     * When settling Delay Funding, keep checking the account info table to see all incoming transactions and their dates.
     * For all account info rows <= the funding delay days from now, mark them as processed and fund the combined amount.
     *
     * {@inheritDoc}
     */
    protected function executeNachaTrigger(Trigger $trigger)
    {
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

        //if there is no balance, there is nothing to be released
        if($this->sourceAccount->getBalance() == 0) {
            return 0;
        }

        $owner = $ownerModel->getById($ownerNode->entity_id);
        $alliance_unique_id = $this->getAllianceUniqueId($owner);

        //date to be processed is today minus funding_delay_days
        //with this date, also consider holidays/weekends, as these should not count into delay according to Minhaz
        $date_to_process = $this->calculateDelayDate($owner->funding_delay_days, $alliance_unique_id);

        //now load account infos that are not processed, and are as old as date_to_process or older.
        // getter should not throw exception, as could be that there is no info to be processed.
        /* @var DelayFundingAccountInfo $accountInfoModel */
        $accountInfoModel = $this->modelLoader->load(DelayFundingAccountInfo::class);
        $accountInfos = $accountInfoModel->getAccountInfosForProcessing($this->sourceAccount->getId(), $date_to_process, false);

        //we loop through account info here to find out what needs to get settled
        foreach ($accountInfos as $accountInfo) {

            //make sure there was actual balance in the account (this should help avoiding rollups)
            if ($accountInfo->balance > 0) {
                //everything that came in on that day, minus everything that may have already been settled
                // (this is relevant for Rolling Reserve, but will more likely never happen on DelayFunding)
                $amount_to_settle = $accountInfo->total_in - $accountInfo->settled_amount;

                //increase settled amount by what is about to be settled, and flag the info as processed (with today as date_processed)
                $accountInfo->settled_amount += $amount_to_settle;
                $accountInfo->processed_flag = 1;
                $accountInfo->date_processed = date('Y-m-d');

                //increase move amount by this account info amount to settle
                $move_amount += $amount_to_settle;
            }
            else {
                $accountInfo->processed_flag = 2; //nothing to process
            }
        }

        if ($move_amount != 0) {
            //check hold/suspend flags, then move there or to NACHA
            $this->moveAmountToDestination($move_amount);
        }

        //after successfully moving money to NACHA settlement, loop through account infos again and save the changes
        foreach ($accountInfos as $accountInfo) {
            $accountInfo->save();
        }

        //return amount moved
        return $move_amount;
    }

    /**
     * Finds the date that should be processed today, based on the given configured delay days.
     * Weekends and holidays do not get counted into the delay,
     * so 3 day delay may come back with a date 5 days ago if there is a weekend in between.
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-08-06
     *
     * @param int delay days as configured on the merchant
     * @param string $alliance_unique_id for which alliance the holidays need to checked
     *
     * @return string desc
     * @throws \Exception
     */
    public function calculateDelayDate($delay_days, $alliance_unique_id, $date = null)
    {
        //firstly, find today's date
        $date = $date ?? new \DateTime();

        $date_allowed = FALSE;

        $holidayModel = new NationalHoliday();

        while (!$date_allowed) {

            //if it is a weekend, go back to before the weekend
            $weekend = $date->format('N') >= 6;
            if ($weekend) {
                $date->modify('last Friday');
            }

            //if this is a holiday, go one day back, and run the check for weekends and holidays again
            $holiday = $holidayModel->isDayHoliday($alliance_unique_id, $date->format('Y-m-d')); //FIXME: load country code depending on merchant? //TODO alliance unique ID
            if ($holiday) {
                $date->modify('-1 weekdays');
                $date_allowed = FALSE;
                continue;
            }

            //if we got here that means the day was no holiday or weekend, so we can actually count it as delay
            //hence we decrease the date by another day for one of the delay days, and then start running the same checks again!
            if ($delay_days > 0) {
                $delay_days--;
                $date->modify('-1 weekdays');
                $date_allowed = FALSE;
                continue;
            }

            //if this is not a weekend, not a holiday, and we counted in all delay days, then this must be it!
            $date_allowed = TRUE;
        }

        return $date->format('Y-m-d');
    }

    public function calculateProcessingDate($delay_days, $alliance_unique_id, $date = null)
    {
        //firstly, find today's date
        $date = $date ?? new \DateTime();

        $date_allowed = FALSE;

        $holidayModel = new NationalHoliday();

        //0 should not happen in funding delay, but it is the same as 1 so handle the same way
        if ($delay_days == 0) {
            $delay_days = 1;
        }

        while (!$date_allowed) {
            $date_allowed = TRUE;

            //if it is a weekend, go back to before the weekend
            $weekend = $date->format('N') >= 6;
            if ($weekend) {
                $date->modify('next Monday');
            }

            //if this is a holiday, go one day back, and run the check for weekends and holidays again
            $holiday = $holidayModel->isDayHoliday($alliance_unique_id, $date->format('Y-m-d')); //FIXME: load country code depending on merchant? //TODO alliance unique ID
            if ($holiday) {
                $date->modify('+1 weekdays');
                $date_allowed = FALSE;
                continue;
            }

            //if we got here that means the day was no holiday or weekend, so we can actually count it as delay
            //hence we decrease the date by another day for one of the delay days, and then start running the same checks again!
            if ($delay_days > 0) {
                $delay_days--;
                $date->modify('+1 weekdays');
                $date_allowed = FALSE;
                continue;
            }
        }

        return $date->format('Y-m-d');
    }

    /**
     * If the merchant is currently held or suspended,
     * we want to move the money about to be released to the respective account.
     * Otherwise the money is settled to nacha output as usual.
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-08-12
     *
     * @param type name desc
     *
     * @throws \Core\Exceptions\ApiException
     */
    private function moveAmountToDestination($move_amount)
    {
        $ownerNode = $this->sourceAccount->getAccountOwnerNode();

        //if the node is held/suspended, we need to move to that respective account INSTEAD of NACHA
        if (in_array($ownerNode->hold_suspend_flag, [AccountGeneral::AUTO_HOLD, AccountGeneral::HOLD])) {
            $holdAcc = $this->loadAccount($ownerNode, $this->sourceAccount->getCurrency(), AccountType::HOLD_ACCOUNT);
            $this->sourceAccount->moveAmount($holdAcc, $move_amount, TransactionCategory::FUNDING);
        }
        elseif ($ownerNode->hold_suspend_flag == AccountGeneral::SUSPEND) {
            $suspendAcc = $this->loadAccount($ownerNode, $this->sourceAccount->getCurrency(), AccountType::HOLD_ACCOUNT);
            $this->sourceAccount->moveAmount($suspendAcc, $move_amount, TransactionCategory::FUNDING);
        }
        //else just move to NACHA as usual
        else {
            $debitTransaction = $this->sourceAccount->debitForSettlementOutput($move_amount);
            $this->sourceAccount->createNachaSettlementAccountsEntry($move_amount, $debitTransaction);
        }
    }

    /**
     * Get Alliance Unique ID from Trigger owner
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-09-25
     *
     * @param Merchant|MerchantSubGroup|Outlet $owner
     *
     * @return string Alliance Unique ID
     * @throws \Core\Exceptions\ApiException
     */
    private function getAllianceUniqueId($owner)
    {
        //check owner model for alliance unique ID, as stored on merchant - this will never be a pfac node
        if ($owner instanceof Merchant) {
            //already a merchant, so has alliance unique ID
            return $owner->alliance_unique_id;
        }
        elseif ($owner instanceof MerchantSubGroup || $owner instanceof Outlet) {
            //subgroup or outlet need to load merchant to know

            /* @var Merchant $merchantModel */
            $merchantModel = $this->modelLoader->load(Merchant::class);
            $merchant = $merchantModel->getByMerchantReference($owner->merchant_reference);

            return $merchant->alliance_unique_id;
        }
    }

}