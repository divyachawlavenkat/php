<?php

namespace Components\Account\Reserve;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountGeneral;
use Constants\AccountType;
use DateTime;
use Models\Account\Reserve\ReserveAccount as ReserveAccountModel;
use Models\Account\Reserve\ReserveAccountInfo;
use Models\Account\Reserve\ReserveAccountTransaction;

/**
 * Class ReserveAccount
 * @package Components\Account
 */
class ReserveAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(ReserveAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(ReserveAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(ReserveAccountModel::class, true);
    }

    /**
     * Returns constant par account type ID for the respective account.
     * This function will have to be implemented in every child.
     *
     * @return int
     */
    public function getAccountType()
    {
        return AccountType::RESERVE_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::RESERVE_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        //if reserve taken for this merchant, have reserve acc from outlet level (check take_reserve_flag and also match type of reserve)
        $take_reserves = (bool) ($config['merchant']['take_reserves_flag'] ?? 0);
        $rolling = (isset($config['config']['reserve_type']) ? ($config['config']['reserve_type'] == AccountGeneral::RESERVE_TYPE_ROLLING) : false);
        return $take_reserves && (!$rolling);
    }

    /**
     * Update today's account info's reserved_today field,
     * using the passed additional reserved amount.
     * If there is no account info for today, create it.
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-08-01
     *
     * @param float $additional_reserved_amount
     *
     * @return float full reserved today amount
     * @throws \Core\Exceptions\ApiException
     */
    public function updateReservedToday($additional_reserved_amount)
    {
        //lazy load
        $accountInfo = $this->getAccountInfo();

        //get DateTime for both dates on midnight, so we can diff
        $today = new DateTime();
        $latest = new DateTime($accountInfo->date_added);
        $today->setTime(0, 0);
        $latest->setTime(0, 0);

        //if there is at least 1 day difference, create a new account info
        if ($today->diff($latest)->days != 0) {
            $accountInfo = $this->createAccountInfo($accountInfo->balance, $accountInfo->info_balance);
        }

        /* @var ReserveAccountInfo $accountInfo */
        $accountInfo->reserved_today += $additional_reserved_amount;
        $accountInfo->save();

        return $accountInfo->reserved_today;
    }

}