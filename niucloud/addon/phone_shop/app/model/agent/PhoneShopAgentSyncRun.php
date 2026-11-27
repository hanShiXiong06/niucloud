<?php

namespace addon\phone_shop\app\model\agent;

use core\base\BaseModel;

/** 主从站商品同步批次台账。 */
class PhoneShopAgentSyncRun extends BaseModel
{
    protected $pk = 'run_id';
    protected $name = 'phone_shop_agent_sync_run';
    protected $json = ['failure_summary', 'error_samples'];
    protected $jsonAssoc = true;
}
