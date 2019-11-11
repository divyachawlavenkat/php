<?php

namespace Components\Account\Trade;

use Components\Account\AbstractAccount;
use Constants\AccountCode;
use Constants\AccountType;
use Constants\EntityType;
use Core\Constants\MessageQueue;
use Core\Exceptions\ApiException;
use DateTime;
use Models\Account\Trade\TradeAccount as TradeAccountModel;
use Models\Account\Trade\TradeAccountInfo;
use Models\Account\Trade\TradeAccountTransaction;

/**
 * Class TradeAccount
 * @package Components\Account
 * @property  \Models\Account\Trade\TradeAccount $accountModel
 * @property  \Models\Account\Trade\TradeAccountTransaction $accountTransactionModel
 * @property  \Models\Account\Trade\TradeAccountInfo $accountInfoModel/**
 * @method \Models\Account\Trade\TradeAccount getAccount()
 */
class TradeAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(TradeAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(TradeAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(TradeAccountModel::class, true);
    }

    /**
     * Returns constant par account type ID for the respective account.
     * This function will have to be implemented in every child.
     *
     * @return int
     */
    public function getAccountType()
    {
        return AccountType::TRADE_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::TRADE_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        //This account will be always need so always create this account
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function createAccount()
    {
        //currency and owner entity from constructor parameters
        // FIXME: account model might have other values set that should be re-initialised.
        $this->accountModel->{$this->accountModel->getIdField()} = null;
        $this->accountModel->hierarchy_type = $this->getHierarchyType();
        $this->accountModel->node_id = $this->accountOwnerNode->getPrimaryNodeId();
        $this->accountModel->currency = $this->currency;
        $this->accountModel->processing_date = date('Y-m-d');  // set processing date to current day.

        //if this is not for an outlet node, set processing flag to "permanent" (otherwise defaults to 0)
        if ($this->accountOwnerNode->entity_type != EntityType::OUTLET) {
            $this->accountModel->processed_flag = TradeAccountModel::PERMANENT;
        }

        //assign to property, so that it is available for getters
        $this->accountModel->create();
        $this->setAccount($this->accountModel);

        return $this->accountModel;
    }

    /**
     * Create an account info for today, default all values to 0 as this should here always be exclusively for this day
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-06-18
     *
     * @param bool $initial_creation used to skip updating old account info for first time creation
     *
     * @param float $prev_balance not used, only kept for parent compatibility
     * @param float $info_prev_balance not used, only kept for parent compatibility
     *
     * @return \Models\Account\Trade\TradeAccountInfo|bool
     * @throws \Core\Exceptions\ApiException
     */
    protected function createAccountInfo($prev_balance, $info_prev_balance, $initial_creation = false, $persist = false)
    {
        //do not create an account info on outlet level
        if ($this->accountOwnerNode->entity_type == EntityType::OUTLET) {
            return false;
        }


        //To set the account ID, we get the ID field name from the model, as these are not generic
        $this->accountInfoModel->trade_account_id = $this->getId();
        $this->accountInfoModel->node_id = $this->getAccount()->node_id;
        $this->accountInfoModel->hierarchy_type = $this->getHierarchyType();

        //date from should be today, date to can be default
        $this->accountInfoModel->date_from = date('Y-m-d');

        //currency of the related account
        $this->accountInfoModel->currency = $this->currency;

        if ($initial_creation) {
            // FIXME: other fields might need to be re-initialised.
            $this->accountInfoModel->{$this->accountInfoModel->getIdField()} = null;
            //assign to property, so that it is available for getters
            $this->accountInfoModel->create();
        }
        else {
            //for non-outlet entities:
            //update the old account with date_to yesterday (skip on initial creation)
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $oldAccountInfo = $this->getAccountInfo(); //will return most recent info
            $oldAccountInfo->date_to = $yesterday;
            $oldAccountInfo->save();
        }
        $this->setAccountInfo($this->accountInfoModel);

        return $this->accountInfoModel;
    }

    /**
     * Lazy load account information. Override to avoid DB query for outlet level, where it is always false
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-07-22
     *
     * @return \Models\Account\Trade\TradeAccountInfo|false
     */
    protected function getAccountInfo()
    {
        //if outlet level, always return false - we do not have trade account info on outlet level
        if ($this->accountOwnerNode->entity_type == EntityType::OUTLET) {
            return false;
        }

        //else BAU
        return parent::getAccountInfo();
    }

    /**
     * Mark the trade account as processed after all triggers have been executed.
     * This queues the trade account to rollup all data to the parent account infos.
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-07-22
     */
    public function markProcessedAndQueue()
    {
        //check flag to be processed
        /* @var TradeAccountModel $account */
        $account = $this->getAccount();
        $account->processed_flag = TradeAccountModel::PROCESSED;
        $account->date_processed = date('Y-m-d');
        $account->save();

        //queue account so info can be updated everywhere
        $queue_data = ['trade_account_id' => $this->getId(), 'processing_date' => $account->processing_date];
        $this->mQueue->publish(json_encode($queue_data), MessageQueue::EXC_ACCOUNT_INFO_ROLLUP, MessageQueue::ROUT_ACCOUNT_INFO_TRADE);
    }

    /**
     * Override because we DO NOT update any Trade Account balances.
     *
     * @param float $amount
     * @param int $transaction_type
     *
     * @return bool
     */
    protected function updateAccountBalance($amount, $transaction_type)
    {
        return false;
    }

    /**
     * Override because we DO NOT update or roll up transaction data as account info.
     * The account info on Trade Account works very different in that it essentially reflects the whole account,
     * and all data is rolled up to higher nodes.
     *
     * @param \Models\Account\AbstractAccountTransaction $transaction
     * @param bool $info_rollup_only
     *
     * @return bool
     */
    public function updateAccountInfo($transaction, $info_rollup_only)
    {
        return false;
    }

    /**
     * Description
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-07-24
     *
     * @param \Components\Account\Trade\TradeAccount $tradeAccount outlet level trade account with actual data
     *
     * @return TradeAccountInfo|bool the updated info
     * @throws \Core\Exceptions\ApiException
     */
    public function updateTradeAccountInfo($tradeAccount)
    {
        //if this trade account is outlet, return false - we do not keep account information on outlet
        if ($this->accountOwnerNode->entity_type == EntityType::OUTLET) {
            return false;
        }

        //if the passed trade account is NOT outlet level, throw exception, as that is the only account info we should be rolling up
        if ($tradeAccount->getAccountOwnerNode()->entity_type != EntityType::OUTLET) {
            throw new ApiException(ApiException::SYSTEM_ERROR . 1301001);
        }

        //lazy load
        $accountInfo = $this->getAccountInfo();

        //get DateTime for both dates on midnight, so we can diff
        $today = new DateTime();
        $latest = new DateTime($accountInfo->date_added);
        $today->setTime(0, 0);
        $latest->setTime(0, 0);

        //if there is at least 1 day difference, create a new account info
        if ($today->diff($latest)->days != 0) {
            $accountInfo = $this->createAccountInfo(0, 0);
        }

        /* @var \Models\Account\Trade\TradeAccount $tradeAccModel */
        $tradeAccModel = $tradeAccount->getAccount();

        //update all trade account fields onto this account info
        $accountInfo->transaction_amount += $tradeAccModel->transaction_amount;
        $accountInfo->refund_amount += $tradeAccModel->refund_amount;
        $accountInfo->net_transaction_amount += $tradeAccModel->net_transaction_amount;
        $accountInfo->merchant_revenue += $tradeAccModel->merchant_revenue;
        $accountInfo->passthrough_amount += $tradeAccModel->passthrough_amount;
        $accountInfo->rejected_amount += $tradeAccModel->rejected_amount;
        $accountInfo->fee += $tradeAccModel->fee;
        $accountInfo->acquirer_fee += $tradeAccModel->acquirer_fee;
        $accountInfo->scheme_fee += $tradeAccModel->scheme_fee;
        $accountInfo->interchange_fee += $tradeAccModel->interchange_fee;
        $accountInfo->chargeback += $tradeAccModel->chargeback;
        $accountInfo->chargeback_reversal += $tradeAccModel->chargeback_reversal;
        $accountInfo->deposit_adjustments += $tradeAccModel->deposit_adjustments;

        //save the update
        $accountInfo->save();

        return $accountInfo;
    }

    /**
     * Getter for processed_flag
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-07-24
     *
     * @return int
     * @throws \Core\Exceptions\ApiException
     */
    public function isProcessed()
    {
        return (bool) $this->getAccount()->processed_flag;
    }

    /**
     * Getter for net_transaction_amount
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-07-24
     *
     * @return float
     * @throws \Core\Exceptions\ApiException
     */
    public function getNetTransactionAmount()
    {
        return $this->getAccount()->net_transaction_amount;
    }

    /**
     * Getter for merchant_revenue
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-07-24
     *
     * @return float
     * @throws \Core\Exceptions\ApiException
     */
    public function getMerchantRevenue()
    {
        return $this->getAccount()->merchant_revenue;
    }

    /**
     * Getter for fee
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-07-24
     *
     * @return float
     * @throws \Core\Exceptions\ApiException
     */
    public function getFee()
    {
        return $this->getAccount()->fee;
    }

    /**
     * Getter for chargeback
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-07-24
     *
     * @return float
     * @throws \Core\Exceptions\ApiException
     */
    public function getChargeback()
    {
        return $this->getAccount()->chargeback;
    }

    /**
     * Getter for chargeback_reversal
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-07-24
     *
     * @return float
     * @throws \Core\Exceptions\ApiException
     */
    public function getChargebackReversal()
    {
        return $this->getAccount()->chargeback_reversal;
    }

    /**
     * Getter for chargeback_processing
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-08-01
     *
     * @return float
     * @throws \Core\Exceptions\ApiException
     */
    public function getChargebackProcessing()
    {
        return $this->getAccount()->chargeback_processing;
    }

    /**
     * Getter for rejected_amount
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-08-01
     *
     * @return float
     * @throws \Core\Exceptions\ApiException
     */
    public function getRejectedAmount()
    {
        return $this->getAccount()->rejected_amount;
    }

    /**
     * Checks if trade account DFM data parsed flag is set.
     * @throws ApiException
     * @return bool
     */
    public function isParsed()
    {
        return $this->getAccount()->isParsed();
    }

    /**
     * Getter for deposit_adjustments
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-08-01
     *
     * @return float
     * @throws \Core\Exceptions\ApiException
     */
    public function getDepositAdjustments()
    {
        return $this->getAccount()->deposit_adjustments;
    }
}