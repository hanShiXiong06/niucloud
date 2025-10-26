<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\model\order;

use core\base\BaseModel;

/**
 * 订单标签
 */
class OrderLabel extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'label_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_order_label';

    //类型
    protected $type = [

    ];


    /**
     * 标签名称
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchLabelNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('label_name', 'like', '%' . $value . '%');
        }
    }


}
