<?php

namespace Components\Account\GrossFee;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Constants\SettlementMethod;
use Models\Account\GrossFee\GrossFeeAccount as GrossFeeAccountModel;
use Models\Account\GrossFee\GrossFeeAccountInfo;
use Models\Account\GrossFee\GrossFeeAccountTransaction;

/**
 * Class GrossFeeAccount
 * @package Components\Account
 */
class GrossFeeAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(GrossFeeAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(GrossFeeAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(GrossFeeAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::GROSS_FEE_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::GROSS_FEE_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        if (isset($config['merchant']['settlement_method']) && $config['merchant']['settlement_method'] == SettlementMethod::GROSS_SETTLEMENT) {
            return true;
        }

        return false;
    }

}