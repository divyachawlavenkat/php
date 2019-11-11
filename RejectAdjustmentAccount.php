<?php

namespace Components\Account\RejectAdjustment;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Constants\HierarchyType;
use Models\Account\RejectAdjustment\RejectAdjustmentAccount as RejectAdjustmentAccountModel;
use Models\Account\RejectAdjustment\RejectAdjustmentAccountInfo;
use Models\Account\RejectAdjustment\RejectAdjustmentAccountTransaction;

/**
 * Class RejectAdjustmentAccount
 * @package Components\Account
 */
class RejectAdjustmentAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(RejectAdjustmentAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(RejectAdjustmentAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(RejectAdjustmentAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::REJECT_ADJUSTMENT_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::REJECT_ADJUSTMENT_ACCOUNT;
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