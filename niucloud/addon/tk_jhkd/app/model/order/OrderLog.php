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

namespace addon\tk_jhkd\app\model\order;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 订单日志模型
 * Class OrderLog
 * @package addon\tk_jhkd\app\model\order
 */
class OrderLog extends BaseModel
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
    protected $name = 'tkjhkd_order_log';

    

    

    /**
     * 搜索器:订单日志
     * @param $value
     * @param $data
     */
    public function searchIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("id", $value);
        }
    }
    
    /**
     * 搜索器:订单日志订单id
     * @param $value
     * @param $data
     */
    public function searchOrderIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_id", $value);
        }
    }
    
    /**
     * 搜索器:订单日志操作
     * @param $value
     * @param $data
     */
    public function searchActionAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("action", $value);
        }
    }
    
    /**
     * 搜索器:订单日志操作身份
     * @param $value
     * @param $data
     */
    public function searchMainTypeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("main_type", $value);
        }
    }
    
    /**
     * 搜索器:订单日志创建时间
     * @param $value
     * @param $data
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("create_time", $value);
        }
    }
    
    

    

    
}
