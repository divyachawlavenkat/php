<?php

namespace Components\Account\ChargebackRecovery;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Constants\HierarchyType;
use Models\Account\ChargebackRecovery\ChargebackRecoveryAccount as ChargebackRecoveryAccountModel;
use Models\Account\ChargebackRecovery\ChargebackRecoveryAccountInfo;
use Models\Account\ChargebackRecovery\ChargebackRecoveryAccountTransaction;

/**
 * Class ChargebackRecoveryAccount
 * @package Components\Account
 */
class ChargebackRecoveryAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(ChargebackRecoveryAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(ChargebackRecoveryAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(ChargebackRecoveryAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::CHARGEBACK_RECOVERY_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::CHARGEBACK_RECOVERY_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        //we should have this account on the same levels as chargeback account, so always currently
        return true;
    }

}