<?php

namespace Components\Account\MerchantFee;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Constants\PFacMode;
use Constants\SettlementMethod;
use Models\Account\MerchantFee\MerchantFeeAccount as MerchantFeeAccountModel;
use Models\Account\MerchantFee\MerchantFeeAccountInfo;
use Models\Account\MerchantFee\MerchantFeeAccountTransaction;

/**
 * Class MerchantFeeAccount
 * @package Components\Account
 */
class MerchantFeeAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(MerchantFeeAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(MerchantFeeAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(MerchantFeeAccountModel::class, true);
    }

    /**
     * Returns constant par account type ID for the respective account.
     * This function will have to be implemented in every child.
     *
     * @return int
     */
    public function getAccountType()
    {
        return AccountType::MERCHANT_FEE_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::MERCHANT_FEE_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        //Merchant fee account is only need for NET settlement (or Instructional), for gross we will use gross account
        if (isset($config['pfac']['pfac_mode']) && $config['pfac']['pfac_mode'] == PFacMode::INSTRUCTIONAL) {
            return true;
        }
        else {  // MANAGED funding
            if (isset($config['config']['settlement_method']) && $config['config']['settlement_method'] == SettlementMethod::NET_SETTLEMENT) {   // 1:GROSS 0:NET
                return true;
            }
        }
        return false;
    }

}