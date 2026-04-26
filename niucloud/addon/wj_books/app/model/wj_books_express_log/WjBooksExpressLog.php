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

namespace addon\wj_books\app\model\wj_books_express_log;

use core\base\BaseModel;
use think\model\relation\BelongsTo;

/**
 * 物流日志模型
 * Class WjBooksExpressLog
 * @package addon\wj_books\app\model\wj_books_express_log
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
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = false;
    
    /**
     * 搜索器:运单号
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchWaybillAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("waybill", 'like', '%' . $value . '%');
        }
    }
    
    /**
     * 搜索器:商家单号
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchShopbillAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("shopbill", 'like', '%' . $value . '%');
        }
    }
    
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
     * 搜索器:状态码
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchTypeCodeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where("type_code", '=', $value);
        }
    }
    
    /**
     * 搜索器:创建时间
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            $query->whereBetweenTime("create_time", $data['start_time'], $data['end_time']);
        }
    }

    /**
     * 搜索器:物流单号
     * @param $query
     * @param $value
     */
    public function searchExpressNoAttr($query, $value)
    {
        if ($value) {
            $query->where("express_no", 'like', "%$value%");
        }
    }

    /**
     * 搜索器:物流状态
     * @param $query
     * @param $value
     */
    public function searchExpressStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where("express_status", $value);
        }
    }

    /**
     * 关联订单
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo('addon\wj_books\app\model\wj_books_order\WjBooksOrder', 'order_id', 'id');
    }
} 