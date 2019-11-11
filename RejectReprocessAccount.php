<?php

namespace Components\Account\RejectReprocess;

use Components\Account\AbstractAccount;
use Components\Account\AccountInterface;
use Constants\AccountCode;
use Constants\AccountType;
use Constants\EntityType;
use Core\Exceptions\ApiException;
use Models\Account\RejectReprocess\RejectReprocessAccount as RejectReprocessAccountModel;
use Models\Account\RejectReprocess\RejectReprocessAccountInfo;
use Models\Account\RejectReprocess\RejectReprocessAccountTransaction;
use Models\Account\SettlementReject\SettlementRejectAccountTransaction;

/**
 * Class RejectReprocessAccount
 * @package Components\Account
 */
class RejectReprocessAccount extends AbstractAccount
{
    /**
     * {@inheritdoc}
     */
    public function __construct($accountEntity, $currency)
    {
        parent::__construct($accountEntity, $currency);
        $this->accountInfoModel = $this->modelLoader->load(RejectReprocessAccountInfo::class, true);
        $this->accountTransactionModel = $this->modelLoader->load(RejectReprocessAccountTransaction::class, true);
        $this->accountModel = $this->modelLoader->load(RejectReprocessAccountModel::class, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountType()
    {
        return AccountType::REJECT_REPROCESS_ACCOUNT;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountCode()
    {
        return AccountCode::REJECT_REPROCESS_ACCOUNT;
    }

    /**
     * {@inheritdoc}
     */
    protected function isAccountNeeded($config)
    {
        //only create on the root levels (MERCHANT/PFAC)
        if ($this->getAccountOwnerNode()->entity_type == EntityType::MERCHANT
            || $this->getAccountOwnerNode()->entity_type == EntityType::PFAC) {
            return true;
        }
        return false;
    }

    /**
     * Creates the reprocess reject transaction.
     *
     * @param SettlementRejectAccountTransaction $settlementRejectTrans
     * @param int $transaction_category
     *
     * @return RejectReprocessAccountTransaction
     * @throws \Core\Exceptions\ApiException
     */
    public function createReprocessRejectTransaction(SettlementRejectAccountTransaction $settlementRejectTrans, int $transaction_category)
    {
        /** @var RejectReprocessAccountTransaction $rejectReprocessTrans */
        $rejectReprocessTrans = $this->modelLoader->load(RejectReprocessAccountTransaction::class, true);
        $rejectReprocessTrans->reject_reprocess_account_id = $this->getId();  // getAccountId;
        $rejectReprocessTrans->settlement_batch_entry_id = $settlementRejectTrans->settlement_batch_entry_id;
        $rejectReprocessTrans->transaction_type_id = $settlementRejectTrans->transaction_type_id;
        $rejectReprocessTrans->hierarchy_type = $settlementRejectTrans->hierarchy_type;
        $rejectReprocessTrans->alliance_unique_id = $settlementRejectTrans->alliance_unique_id;
        $rejectReprocessTrans->pfac_node_id = $settlementRejectTrans->pfac_node_id;
        $rejectReprocessTrans->node_id = $settlementRejectTrans->node_id;
        $rejectReprocessTrans->link_account_id = $settlementRejectTrans->link_account_id;
        $rejectReprocessTrans->linked_account_type_id = $settlementRejectTrans->linked_account_type_id;
        $rejectReprocessTrans->initial_linked_account_type_id = $settlementRejectTrans->initial_linked_account_type_id;
        $rejectReprocessTrans->linked_acc_transaction_id = $settlementRejectTrans->linked_acc_transaction_id;
        $rejectReprocessTrans->amount = $settlementRejectTrans->amount;
        $rejectReprocessTrans->currency = $settlementRejectTrans->currency;
        $rejectReprocessTrans->transaction_category_id = $transaction_category;
        $rejectReprocessTrans->merchant_credit_responsibility = $settlementRejectTrans->merchant_credit_responsibility;

        //straightaway mark processed as we are currently doing exactly that
        $rejectReprocessTrans->processed = RejectReprocessAccountTransaction::PROCESSED_SUCCESSFULLY;

        $rejectReprocessTrans->create();
        return $this->lastTransaction = $rejectReprocessTrans;
    }

    public function debit(AccountInterface $to, float $amount, int $transaction_category_id = NULL, $directive_instruction = false, $description = null)
    {
        //debit should never happen on this account
        throw new ApiException(ApiException::SYSTEM_ERROR, 1301001);
    }

    public function credit(AccountInterface $from, float $amount, int $transaction_category_id = NULL, $description = null)
    {
        //credit should never happen on this account
        throw new ApiException(ApiException::SYSTEM_ERROR, 1301001);

    }

    // FIXME: no balance.
}