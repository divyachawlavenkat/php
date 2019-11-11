<?php

namespace Components\Account\Revenue;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Models\Account\Revenue\RevenueAccount as RevenueAccountModel;
use Models\Account\Revenue\RevenueAccountInfo;
use Models\Account\Revenue\RevenueAccountTransaction;

/**
 * Class RevenueAccount
 * @package Components\Account
 */
class RevenueAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(RevenueAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(RevenueAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(RevenueAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::REVENUE_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::REVENUE_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        return true;
    }

}