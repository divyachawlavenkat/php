<?php

namespace Components\Account\ReserveRelease;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Models\Account\ReserveRelease\ReserveReleaseAccount as ReserveReleaseAccountModel;
use Models\Account\ReserveRelease\ReserveReleaseAccountInfo;
use Models\Account\ReserveRelease\ReserveReleaseAccountTransaction;

/**
 * Class ReserveReleaseAccount
 * @package Components\Account
 */
class ReserveReleaseAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(ReserveReleaseAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(ReserveReleaseAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(ReserveReleaseAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::RESERVE_RELEASE_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::RESERVE_RELEASE_ACCOUNT;
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