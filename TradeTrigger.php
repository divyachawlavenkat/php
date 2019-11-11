<?php

namespace Components\Account\Trade;

use Components\Account\AbstractAccount;
use Components\Account\AbstractTrigger;
use Constants\AccountGeneral;
use Constants\AccountType;
use Constants\EntityType;
use Constants\PFacMode;
use Constants\SettlementMethod;
use Constants\TransactionCategory;
use Core\Exceptions\ApiException;
use Models\Merchant;
use Models\MerchantNode;
use Models\MerchantSubGroup;
use Models\Outlet;
use Models\Trigger\Trigger;

/**
 * Class TradeTrigger
 * @package Components\Account\Trade
 * @property \Components\Account\Trade\TradeAccount $sourceAccount
 */
class TradeTrigger extends AbstractTrigger
{
    const RESERVE_SETTING_FIXED = 0;

    const RESERVE_SETTING_PERC = 1;

    const RESERVE_TARGET_NO = 0;

    const RESERVE_TARGET_YES = 1;

    /**
     * This property is being used to keep track of the actual revenue, which is decreased when a reserve is taken.
     * Due to the unique nature of the Trade Account where we do not change any balances, we have to track it this way.
     * @var float
     */
    private $merchant_revenue;

    /**
     * {@inheritdoc}
     */
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        $pfac_mode = $config['pfac']['pfac_mode'] ?? 0;
        $outlet_level = $this->sourceAccount->getAccountOwnerNode()->entity_type == EntityType::OUTLET;
        if (!$outlet_level) {
            // Trade account triggers are reserved for the outlet level.
            return;
        }

        if ($pfac_mode == PFacMode::INSTRUCTIONAL) {
            $this->createSidewaysTrigger(AccountType::CHARGEBACK_ACCOUNT, $config, TransactionCategory::CHARGEBACK);
            $this->createSidewaysTrigger(AccountType::INSTRUCTIONAL_HOLD_ACCOUNT, $config, TransactionCategory::FUNDING);
        }
        elseif ($pfac_mode == PFacMode::MANAGED) {

            $reserve = (bool) ($config['config']['reserve_flag'] ?? 0);
            $rolling = (bool) ($config['config']['reserve_type'] ? ($config['config']['reserve_type'] == AccountGeneral::RESERVE_TYPE_ROLLING) : false);

            // reserve triggers should always be created before revenue trigger.
            if ($reserve && $rolling) {
                $this->createSidewaysTrigger(AccountType::ROLLING_RESERVE_ACCOUNT, $config, TransactionCategory::RESERVE);
            }
            elseif ($reserve) {
                $this->createSidewaysTrigger(AccountType::RESERVE_ACCOUNT, $config, TransactionCategory::RESERVE);
            }

            $this->createSidewaysTrigger(AccountType::REVENUE_ACCOUNT, $config, TransactionCategory::FUNDING);

            $this->createSidewaysTrigger(AccountType::CHARGEBACK_ACCOUNT, $config, TransactionCategory::CHARGEBACK);
            $settlement_method = $config['merchant']['settlement_method'] ?? null;// Net settlement
            if ($settlement_method == SettlementMethod::NET_SETTLEMENT) {
                $this->createSidewaysTrigger(AccountType::MERCHANT_FEE_ACCOUNT, $config, TransactionCategory::FEE);
            }
            // Gross settlement
            else if ($settlement_method == SettlementMethod::GROSS_SETTLEMENT) {
                $this->createSidewaysTrigger(AccountType::GROSS_FEE_ACCOUNT, $config, TransactionCategory::FEE);
            }
            else {
                $this->logger->error('Unsupported PFac mode: ' . json_encode($config['pfac']));
                // 'E_13-01-001' => 'Unexpected system error. We are not able to process your request now.',
                throw new ApiException(ApiException::SYSTEM_ERROR, 1301001);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function processTriggers(array $triggers, array $skipped_nodes = [])
    {
        //TODO we do not have a locking mechanism here?? Think if we should just copy from abstract?

        //check that a source account has been set
        if (!$this->sourceAccount) {
            //'E_12-50-007' => 'Source account must be set to process triggers',
            throw new ApiException(ApiException::REQUEST_FAILED, 1250007);
        }

        //only condition is that trade account is not yet processed
        if ($this->sourceAccount->isProcessed()) {
            //'E_12-50-011' => 'Trade account has already been processed',
            throw new ApiException(ApiException::REQUEST_FAILED, 1250011);
        }

        //set merchant revenue (here, so it will be set when we go through the triggers!)
        $this->merchant_revenue = $this->sourceAccount->getMerchantRevenue();

        // Check to ensure all related data was parsed successfully.
        if (!$this->sourceAccount->isParsed()) {
            // TODO: notify? might be lots of emails if no transactions happen for the corresponding outlet.
            $this->logger->info('Trade account data parsed flag not set. Trade Account ID: ' . $this->sourceAccount->getId());
            return;
        }

        //loop through all given triggers
        foreach ($triggers as $trigger) {
            //check trigger source is this account
            if ($trigger->source_account_type != $this->sourceAccount->getAccountType() || //make sure account type matches
                $trigger->source_account_node_id != $this->sourceAccount->getAccountOwnerNode()->getPrimaryNodeId() || //make sure account node matches
                ($trigger->source_account_type != AccountType::TRADE_ACCOUNT && $trigger->source_account_id != $this->sourceAccount->getId())) { //make sure ID matches, except for Trade Account!
                //'E_12-50-008' => 'Trigger source account is not the given source account.',
                throw new ApiException(ApiException::REQUEST_FAILED, 1250008);
            }

            //call executeTrigger function with a given trigger (balance will be determined inside it)
            $this->executeTrigger($trigger);
            $this->updateExecutionDate($trigger);

            //publish info for the account we moved money to! ♥
            $this->mQueue->publishDelayedMessages();
        }

        //mark account as processed so that info will be rolled up
        $this->sourceAccount->markProcessedAndQueue();
    }

    /**
     * {@inheritdoc}
     */
    protected function executeTrigger(Trigger $trigger)
    {
        //load trigger target
        $this->targetAccount = $this->loadAccountById($trigger->target_account_id, $trigger->target_account_type);

        //determine balance to move based on trigger target (as we move from different columns for trade account
        switch ($trigger->target_account_type) {
            case AccountType::INSTRUCTIONAL_HOLD_ACCOUNT:
                //move everything
                $move_amount = $this->sourceAccount->getNetTransactionAmount();
                break;
            case AccountType::CHARGEBACK_ACCOUNT:
                //move chargeback amount minus reversals (if this results in negative amount means the merchant gets money, still move it!)
                $move_amount = bcsub(abs($this->sourceAccount->getChargeback()), $this->sourceAccount->getChargebackReversal(), 4);
                break;
            case AccountType::REVENUE_ACCOUNT:
                //move revenue, but minus the reserved amount. For this, we keep track using a property (reserve would have already been taken at this point)
                $move_amount = $this->merchant_revenue;
                break;
            case AccountType::ROLLING_RESERVE_ACCOUNT:
            case AccountType::RESERVE_ACCOUNT:
                //move reserve amount (maximum as much as is in merchant_revenue, and as much as can still go into target without breaking limit)
                $move_amount = $this->calculateReserve($this->targetAccount);

                //bonus: we now subtract the reserve amount from merchant revenue property, so that on revenue, we only move what is left!
                $this->merchant_revenue = bcsub($this->merchant_revenue, $move_amount, 4);
                break;
            case AccountType::MERCHANT_FEE_ACCOUNT:
            case AccountType::GROSS_FEE_ACCOUNT:
                //move fees + chargeback processing fee
                $move_amount = bcadd($this->sourceAccount->getFee(), $this->sourceAccount->getChargebackProcessing(), 4);
                break;
            default:
                //'E_12-50-012' => 'Unexpected target account for Trade Account trigger',
                throw new ApiException(ApiException::REQUEST_FAILED, 1250012);
        }

        //if move amount is 0, no need for a transaction
        if ($move_amount != 0) {
            $this->sourceAccount->moveAmount($this->targetAccount, $move_amount, $trigger->transaction_category_id);
        }

        //return amount moved
        return $move_amount;
    }

    private function calculateReserve(AbstractAccount $targetReserveAcc)
    {
        //account owner node is always asn outlet on Trade acc
        $ownerNode = $this->sourceAccount->getAccountOwnerNode();

        //load respective outlet for its configuration
        /* @var \Models\Outlet $outletModel */
        $outletModel = $this->modelLoader->load(Outlet::class);
        $outlet = $outletModel->getById($ownerNode->entity_id);

        //we do not need to check if reserve is being taken, as that's why the trigger exists.
        //we check if we take percentage or fixed amount
        if ($outlet->reserve_setting == self::RESERVE_SETTING_PERC) {
            $reserve_perc = $outlet->reserve_trans_perc;

            //get the decimal so we can calculate
            $reserve_perc_dec = bcdiv($reserve_perc, 100, 6);

            //take percentage of transaction amount (we calculate reserve from whole amount, not just merchant revenue)
            $reserve_amount = bcmul($this->merchant_revenue, $reserve_perc_dec, 4);
        }
        else { //fixed amount
            $reserve_amount = $outlet->reserve_daily_amount;
        }

        //if merchant revenue is less than reserve we are trying to take, we just take the full merchant revenue
        if ($this->merchant_revenue < $reserve_amount) {
            $reserve_amount = $this->merchant_revenue;
        }

        //if there is a target on reserve, check how close we are to reaching it
        //reserve level may be funding level, we then have to check the target on a different account...
        if ($outlet->reserve_flag == 0) {

            //lock this entity! account info for today may not exist yet, so we cannot rely on locking that
            $this->lockMerchantNode($outlet->reserve_merchant_node_id);

            //load reserve level parent entity (after lock, so other transactions are now waiting before loading this)
            /* @var MerchantNode $merchEntityModel */
            $merchEntityModel = $this->modelLoader->load(MerchantNode::class);
            $reserveEntity = $merchEntityModel->getById($outlet->reserve_merchant_node_id);

            //load actual owner model, to get reserve limit - this will never be a pfac node
            switch ($reserveEntity->entity_type) {
                case EntityType::OUTLET: //should never happen
                    /* @var Outlet $ownerModel */
                    $ownerModel = $this->modelLoader->load(Outlet::class);
                    break;
                case EntityType::SUB_MERCHANT_GROUP:
                    /* @var MerchantSubGroup $ownerModel */
                    $ownerModel = $this->modelLoader->load(MerchantSubGroup::class);
                    break;
                case EntityType::MERCHANT:
                    /* @var Merchant $ownerModel */
                    $ownerModel = $this->modelLoader->load(Merchant::class);
                    break;
                default:
                    throw new ApiException(ApiException::SYSTEM_ERROR . 1301001);
            }

            $reserveOwner = $ownerModel->getById($ownerNode->entity_id);

            if ($reserveOwner->set_reserve_target == self::RESERVE_TARGET_YES) {
                //load funding level reserve account
                /* @var \Components\Account\Reserve\ReserveAccount|\Components\Account\RollingReserve\RollingReserveAccount $fundingReserveAcc */
                $fundingReserveAcc = $this->loadAccount($reserveEntity, $targetReserveAcc->getCurrency(), $targetReserveAcc->getAccountType());
                $fundingReserveAcc->getCurrentAccountInfo();

                //get the current account info
                /* @var \Models\Account\Reserve\ReserveAccountInfo|\Models\Account\RollingReserve\RollingReserveAccountInfo $currentAccountInfo */
                $currentAccountInfo = $fundingReserveAcc->getCurrentAccountInfo();

                //default today's reserved amount to 0, if the current account info is from today, use the amount on there (as there might have not been a new one today yet)
                $reserved_today = 0;
                if ($currentAccountInfo->date_from == date('Y-m-d')) {
                    $reserved_today = $currentAccountInfo->reserved_today;
                }

                //sum up reserved today and account balance to get the full current reserve
                $full_amount_reserved = bcadd($fundingReserveAcc->getBalance(), $reserved_today, 4);
                //subtract full current reserve from target amount to find out how much more is required
                $missing_reserve = bcsub($outlet->reserve_target_amount, $full_amount_reserved, 4);

                //if the required amount is less than the reserve we are trying to take, only take the required amount
                if ($missing_reserve < $reserve_amount) {
                    $reserve_amount = $missing_reserve;
                }

                //lastly, update the funding reserve account info with the new amount reserved today
                $fundingReserveAcc->updateReservedToday($reserve_amount);
            }
        }
        else { //else reserve is taken on outlet level, which means we can just look at amount on reserve account
            if ($outlet->set_reserve_target == self::RESERVE_TARGET_YES) {
                //subtract reserve balance from target amount to find out how much more is required
                $missing_reserve = bcsub($outlet->reserve_target_amount, $targetReserveAcc->getBalance(), 4);

                //if the required amount is less than the reserve we are trying to take, only take the required amount
                if ($missing_reserve < $reserve_amount) {
                    $reserve_amount = $missing_reserve;
                }
            }
        }

        //return ultimate reserve amount (to fill up limit or what can be taken from revenue or how much reserve calculated)
        return $reserve_amount;
    }

    /**
     * Locks the transaction to that merchant entity row so that no second process overwrites the data or accesses it.
     * This way, we can process the account info for that node without worrying about clashes.
     *
     * @author Adrian
     * @since 2.0.0
     * @date_created 2019-08-02
     * @date_modified ---
     * @modified_by ---
     *
     * @param int $entity_id Merchant Entity ID
     */
    private function lockMerchantNode($entity_id)
    {
        $date_now = date('Y-m-d H:i:s');

        $phql = "UPDATE Models\MerchantNode SET date_updated = '" . $date_now . "'  WHERE merchant_node_id = " . $entity_id;

        //execute the query
        $this->modelsManager->executeQuery($phql);
    }
}