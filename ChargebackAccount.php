<?php

namespace Components\Account\Chargeback;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Models\Account\Chargeback\ChargebackAccount as ChargebackAccountModel;
use Models\Account\Chargeback\ChargebackAccountInfo;
use Models\Account\Chargeback\ChargebackAccountTransaction;

/**
 * Class ChargebackAccount
 * @package Components\Account
 */
class ChargebackAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(ChargebackAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(ChargebackAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(ChargebackAccountModel::class, true);
    }

    /**
     * Returns constant par account type ID for the respective account.
     * This function will have to be implemented in every child.
     *
     * @return int
     */
    public function getAccountType()
    {
        return AccountType::CHARGEBACK_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::CHARGEBACK_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        // it seems charge back account is always required, no matter if charge back is managed externally or inside our system.
        // in the case of externally managed, PFAC can still instruct to move some money to charge back account.
        return true;
    }

}