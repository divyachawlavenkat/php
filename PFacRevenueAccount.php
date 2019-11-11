<?php

namespace Components\Account\PFacRevenue;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Constants\HierarchyType;
use Models\Account\PFacRevenue\PFacRevenueAccount as PFacRevenueAccountModel;
use Models\Account\PFacRevenue\PFacRevenueAccountInfo;
use Models\Account\PFacRevenue\PFacRevenueAccountTransaction;

/**
 * Class PFacRevenueAccount
 * @package Components\Account
 */
class PFacRevenueAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(PFacRevenueAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(PFacRevenueAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(PFacRevenueAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::PFAC_REVENUE_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::PFAC_REVENUE_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        //this account is only for PFAC nodes on funding level
        if ($this->getHierarchyType() == HierarchyType::PFAC) {
            $funding = (bool) ($config['config']['funding_flag'] ?? 0);
            if ($funding) {
                return true;
            }
        }

        return false;
    }

}