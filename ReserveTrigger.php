<?php

namespace Components\Account\Reserve;

use Components\Account\AbstractTrigger;
use Constants\TransactionCategory;
use Constants\TriggerOperator;
use Models\Trigger\Trigger;

/**
 * Class ReserveTrigger
 * @package Components\Account\Reserve
 */
class ReserveTrigger extends AbstractTrigger
{

    public function createTriggers($config, $parent_config, $pfac_data)
    {
        //roll up to funding level if that is where the reserve is taken
        //then use a NACHA trigger to move money into CARS, checking account info
        $reserve_level = (bool) ($config['config']['reserve_flag'] ?? 0);
        if (!$reserve_level) {
            //rollup to reserve level
            $this->createRollupTrigger($config, $parent_config);
        }
        else {
            //on reserve level: special NACHA trigger, move today's IN to CARS accounts
            //note that the Trigger Operator is GTE as this should also run on balance 0 (IN amount might have been released on the same day)
            $this->createNachaTrigger($config, TransactionCategory::RESERVE, TriggerOperator::GTE);
        }
    }

    /**
     * When moving money into Reserve, it needs to move from IPS to CARS (Collateral account).
     * For this we use a nacha trigger, but only want to move the additional money coming in today.
     * The remaining balance is already on CARS, or if money was taken out, it was taken from CARS.
     * We also do not want to debit the Reserve account upon said trigger.
     *
     * {@inheritDoc}
     */
    protected function executeNachaTrigger(Trigger $trigger)
    {
        /* @var \Models\Account\Reserve\ReserveAccountInfo $accountInfo */
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

    protected function createTransactionUponSettlementOutput()
    {

        return false;
    }
}