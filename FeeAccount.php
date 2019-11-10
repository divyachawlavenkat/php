<?php

namespace Components\Account\Fee;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Models\Account\Fee\FeeAccount as FeeAccountModel;
use Models\Account\Fee\FeeAccountInfo;
use Models\Account\Fee\FeeAccountTransaction;

/**
 * Class FeeAccount
 * @package Components\Account
 */
class FeeAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(FeeAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(FeeAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(FeeAccountModel::class, true);
    }

    /**
     * Returns constant par account type ID for the respective account.
     * This function will have to be implemented in every child.
     *
     * @return int
     */
    public function getAccountType()
    {
        return AccountType::FEE_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::FEE_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        // only create on billing level, as merchant fee rolls up already
        $billing_level = (bool) ($config['config']['billing_flag'] ?? 0);
        return $billing_level;
    }

}