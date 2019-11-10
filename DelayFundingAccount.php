<?php

namespace Components\Account\DelayFunding;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Models\Account\DelayFunding\DelayFundingAccount as DelayFundingAccountModel;
use Models\Account\DelayFunding\DelayFundingAccountInfo;
use Models\Account\DelayFunding\DelayFundingAccountTransaction;

/**
 * Class DelayFundingAccount
 * @package Components\Account
 */
class DelayFundingAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(DelayFundingAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(DelayFundingAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(DelayFundingAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::DELAY_FUNDING_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::DELAY_FUNDING_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        $delay_funding_flag = (bool) ($config['config']['delay_funding_flag'] ?? false);
        $funding_flag = (bool) ($config['config']['funding_flag'] ?? false);

        if ($delay_funding_flag && $funding_flag) {
            return true;
        }

        return false;
    }

    protected function infoRollupSpecialLogic(\Models\Account\AbstractAccountInfo $accountInfo)
    {
        /* @var DelayFundingAccountInfo $accountInfo */
        $accountInfo->processed_flag = 2; //rollup info means nothing to process

        return parent::infoRollupSpecialLogic($accountInfo);
    }
}