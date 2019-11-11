<?php

namespace Components\Account\RejectReprocess;

use Components\Account\AbstractTrigger;
use Constants\AccountGeneral;
use Constants\AccountType;
use Constants\TransactionCategory;
use Models\Account\RejectReprocess\RejectReprocessAccountTransaction;
use Models\Trigger\Trigger;

/**
 * Class RejectReprocessTrigger
 * @package Components\Account\RejectReprocess
 */
class RejectReprocessTrigger extends AbstractTrigger
{
    /**
     * Create account trigger.
     *
     * @param $config
     * @param $parent_config
     * @param $pfac_data
     *
     * @return bool
     */
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        //the reject reprocess logic gets handled by an API
        return false;
    }

}