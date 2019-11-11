<?php

namespace Components\Account\InstructionalHold;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Constants\PFacMode;
use Models\Account\InstructionalHold\InstructionalHoldAccount as InstructionalHoldAccountModel;
use Models\Account\InstructionalHold\InstructionalHoldAccountInfo;
use Models\Account\InstructionalHold\InstructionalHoldAccountTransaction;

/**
 * Class InstructionalHoldAccount
 * @package Components\Account
 */
class InstructionalHoldAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(InstructionalHoldAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(InstructionalHoldAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(InstructionalHoldAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::INSTRUCTIONAL_HOLD_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::INSTRUCTIONAL_HOLD_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        //always for instructional funding
        if (isset($config['pfac']['pfac_mode']) && $config['pfac']['pfac_mode'] == PFacMode::INSTRUCTIONAL) {
            return true;
        }
        return false;
    }

}