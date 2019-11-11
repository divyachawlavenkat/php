<?php

namespace Components\Account\Hold;

use Components\Account\AbstractTrigger;

/**
 * Class HoldTrigger
 * @package Components\Account\Hold
 */
class HoldTrigger extends AbstractTrigger
{
    public function createTriggers($config, $parent_config, $pfac_data)
    {
        //Do not need a trigger, as this is manual.
        //In the event of manual release of the being hold credit, NACHA file shall be sent to move the credit back to Merchant’s Settlement Account.  //TODO:
        return false;
    }

}