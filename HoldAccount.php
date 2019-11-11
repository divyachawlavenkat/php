<?php

namespace Components\Account\Hold;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Models\Account\Hold\HoldAccount as HoldAccountModel;
use Models\Account\Hold\HoldAccountInfo;
use Models\Account\Hold\HoldAccountTransaction;

/**
 * Class HoldAccount
 * @package Components\Account
 */
class HoldAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(HoldAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(HoldAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(HoldAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::HOLD_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::HOLD_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        $funding_flag = (bool) ($config['config']['funding_flag'] ?? false);
        return $funding_flag;
    }

}