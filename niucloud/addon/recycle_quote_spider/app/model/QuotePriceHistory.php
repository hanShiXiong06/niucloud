<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\model;

use core\base\BaseModel;

class QuotePriceHistory extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quote_spider_price_history';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = false;
    protected $json = ['columns', 'final_prices'];
    protected $jsonAssoc = true;
}
