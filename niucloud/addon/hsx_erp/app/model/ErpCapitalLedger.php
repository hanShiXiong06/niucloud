<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

/**
 * 资金账目往来流水（账户的每一笔收/付）
 */
class ErpCapitalLedger extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'erp_capital_ledger';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = false;
}
