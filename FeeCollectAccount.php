<?php

namespace Components\Account\FeeCollect;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Constants\PFacMode;
use Constants\SettlementMethod;
use Models\Account\FeeCollect\FeeCollectAccount as FeeCollectAccountModel;
use Models\Account\FeeCollect\FeeCollectAccountInfo;
use Models\Account\FeeCollect\FeeCollectAccountTransaction;

/**
 * Class FeeCollectAccount
 * @package Components\Account
 */
class FeeCollectAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(FeeCollectAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(FeeCollectAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(FeeCollectAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::FEE_COLLECT_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::FEE_COLLECT_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        //only create on billing level (Gross Fee and Service Fee roll up to billing level before sideways)
        $billing_level = (bool) ($config['config']['billing_flag'] ?? 0);
        if ($billing_level && isset($config['pfac']['pfac_mode']) && $config['pfac']['pfac_mode'] == PFacMode::MANAGED) {
            if (isset($config['merchant']['settlement_method']) && $config['merchant']['settlement_method'] == SettlementMethod::GROSS_SETTLEMENT) {
                return true;
            }
        }
        return false;
    }

}