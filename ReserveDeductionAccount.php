<?php

namespace Components\Account\ReserveDeduction;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Models\Account\ReserveDeduction\ReserveDeductionAccount as ReserveDeductionAccountModel;
use Models\Account\ReserveDeduction\ReserveDeductionAccountInfo;
use Models\Account\ReserveDeduction\ReserveDeductionAccountTransaction;

/**
 * Class ReserveDeductionAccount.php
 * @package Components\Account
 */
class ReserveDeductionAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(ReserveDeductionAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(ReserveDeductionAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(ReserveDeductionAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::RESERVE_DEDUCTION_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::RESERVE_DEDUCTION_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        //we only need this from the level where we take reserve
        $reserve = (bool) ($config['config']['reserve_flag'] ?? 0);
        return $reserve;
    }

}