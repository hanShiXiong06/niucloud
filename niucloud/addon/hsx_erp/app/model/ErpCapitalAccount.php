<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

/**
 * 资金账户（现金/微信/支付宝/银行卡等，各记余额）
 */
class ErpCapitalAccount extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'erp_capital_account';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = 'update_at';
}
