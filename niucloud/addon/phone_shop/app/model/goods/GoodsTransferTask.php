<?php
declare(strict_types=1);

namespace addon\phone_shop\app\model\goods;

use core\base\BaseModel;

/** 商城商品 Excel 导入、导出任务 */
class GoodsTransferTask extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'phone_shop_goods_transfer_task';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $json = ['request_json', 'result_json'];
    protected $jsonAssoc = true;

    public function searchSiteIdAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) $query->where('site_id', '=', (int)$value);
    }

    public function searchTaskTypeAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) $query->where('task_type', '=', (string)$value);
    }

    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) $query->where('status', '=', (string)$value);
    }
}
