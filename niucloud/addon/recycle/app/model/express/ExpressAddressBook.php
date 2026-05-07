<?php
declare(strict_types=1);

namespace addon\recycle\app\model\express;

use core\base\BaseModel;

/**
 * 快递常用地址
 */
class ExpressAddressBook extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'express_address_book';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('site_id', '=', $value);
        }
    }

    public function searchAddressTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('address_type', '=', $value);
        }
    }
}
