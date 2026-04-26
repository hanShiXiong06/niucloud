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

namespace addon\wj_books\app\model\wj_books_order;

use core\base\BaseModel;

/**
 * 物流回调日志模型
 * Class WjBooksExpressLog
 * @package addon\wj_books\app\model\wj_books_order
 */
class WjBooksExpressLog extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'wj_books_express_log';

    /**
     * 搜索器:订单ID
     * @param $query
     * @param $value
     */
    public function searchOrderIdAttr($query, $value)
    {
        if ($value) {
            $query->where("order_id", $value);
        }
    }

    /**
     * 搜索器:运单号
     * @param $query
     * @param $value
     */
    public function searchWaybillAttr($query, $value)
    {
        if ($value) {
            $query->where("waybill", $value);
        }
    }

    /**
     * 搜索器:状态码
     * @param $query
     * @param $value
     */
    public function searchTypeCodeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where("type_code", $value);
        }
    }
} 