<?php

namespace addon\hsx_phone_query\app\model;

use core\base\BaseModel;

/**
 * 手机查询订单模型
 */
class HsxPhoneQueryOrder extends BaseModel
{
    protected $pk = 'order_id';

    protected $name = 'hsx_phone_query_order';

    public function member()
    {
        return $this->belongsTo('app\model\member\Member', 'member_id', 'member_id')
            ->field('member_id,nickname,headimg,mobile');
    }

    public function getStatusNameAttr($value, $data)
    {
        return \addon\hsx_phone_query\app\dict\HsxPhoneQueryOrderDict::getStatus()[$data['status'] ?? 0] ?? '';
    }
}
