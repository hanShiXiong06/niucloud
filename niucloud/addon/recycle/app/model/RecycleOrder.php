<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;
use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\model\recycle_user_address\RecycleUserAddress;
use addon\recycle\app\model\RecycleDevice;
use app\model\member\Member;
use app\model\site\Site;

/**
 * 回收订单模型
 * Class RecycleOrder
 * @package addon\recycle\app\model
 */
class RecycleOrder extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /*软删除*/
    protected $deleteTime = 'delete_at';
  
    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_order';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_at';

    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'update_at';

    /**
     * 追加属性
     * @var array
     */
    protected $append = [
        'status_name',
        'delivery_type_name'
    ];

    /**
     * 搜索字段
     * @var array
     */
    protected $searchFields = [
        'order_no' => '%like%',
        'status' => '=',
        'delivery_type' => '=',
        'customer_name' => '%like%',
        'customer_phone' => '%like%',
        'remark' => '%like%',
        'create_at' => 'between',
         'member_id' => '='
    ];

    /**
     * 获取订单状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        $status = RecycleOrderDict::getOrderStatus($data['status'] ?? '');
        return $status['name'] ?? '';
    }

    /**
     * 获取配送方式名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getDeliveryTypeNameAttr($value, $data)
    {
        
        return $data['delivery_type'] == 1 ? '快递':'自送';
    }

    /**
     * 关联设备表
     * @return \think\model\relation\HasMany
     */
    public function devices()
    {
        return $this->hasMany(RecycleDevice::class, 'order_id', 'id');
    }

    /**
     * 关联会员表
     * @return \think\model\relation\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function recycleUserAddress()
    {
        return $this->belongsTo(RecycleUserAddress::class, 'member_id', 'member_id');
    }

    /**
     * 关联站点表
     * @return \think\model\relation\BelongsTo
     */
    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id', 'site_id');
    }

    /**
     * 获取带前缀的表名
     * @param string $table
     * @return string
     */
    protected function getTable($table = null)
    {
        if ($table === null) {
            return $this->getConfig('prefix') . $this->name;
        }
        return $this->getConfig('prefix') . $table;
    }

    // 搜索器 本表 id 模糊匹配
    public function searchIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('id', 'like', "%{$value}%");
        }
    }
    
    // 搜索器 本表 express_no 模糊匹配
    public function searchExpressNoAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('express_no', 'like', "%{$value}%");
        }
    }
    
    // 搜索器 通过设备IMEI搜索订单
    public function searchImeiAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->whereExists(function($subQuery) use ($value) {
                $subQuery->table($this->getTable('recycle_device'))
                         ->whereRaw($this->getTable('recycle_device') . '.order_id = ' . $this->getTable() . '.id')
                         ->where('imei', 'like', "%{$value}%");
            });
        }
    }
    
    // 搜索器 通过设备型号搜索订单
    public function searchDeviceModelAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->whereExists(function($subQuery) use ($value) {
                $subQuery->table($this->getTable('recycle_device'))
                         ->whereRaw($this->getTable('recycle_device') . '.order_id = ' . $this->getTable() . '.id')
                         ->where('model', 'like', "%{$value}%");
            });
        }
    }

    // 搜索器 本表 customer_name 模糊匹配
    public function searchCustomerNameAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('customer_name', 'like', "%{$value}%");
        }
    }

    // 搜索器 本表 customer_phone 模糊匹配
    public function searchCustomerPhoneAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('customer_phone', 'like', "%{$value}%");
        }
    }

    // 搜索器 本表 remark 模糊匹配
    public function searchRemarkAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('remark', 'like', "%{$value}%");
        }
    }

    // 搜索器 本表 create_at 日期范围查询
    public function searchCreateAtAttr($query, $value, $data)
    {
        if (is_array($value) && count($value) == 2) {
            if (!empty($value[0]) && !empty($value[1])) {
                $query->whereBetweenTime('create_at', $value[0], $value[1]);
            }
        } elseif (is_string($value) && !empty($value)) {
            // 支持单个日期查询（当天）
            $query->whereTime('create_at', 'between', [$value . ' 00:00:00', $value . ' 23:59:59']);
        }
    }

    // 搜索器 本表 status 精确匹配
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            if (is_array($value)) {
                // 支持多状态查询
                $query->whereIn('status', $value);
            } else {
                $query->where('status', $value);
            }
        }
    }

    // 搜索器 本表 delivery_type 精确匹配
    public function searchDeliveryTypeAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            if (is_array($value)) {
                // 支持多配送方式查询
                $query->whereIn('delivery_type', $value);
            } else {
                $query->where('delivery_type', $value);
            }
        }
    }

    // 搜索器 本表 order_no 模糊匹配
    public function searchOrderNoAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('order_no', 'like', "%{$value}%");
        }
    }
    
    // 搜索器 通用搜索 - 支持多字段模糊搜索
    public function searchSearchAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where(function($subQuery) use ($value) {
                $subQuery->whereOr([
                    ['id', 'like', "%{$value}%"],
                    ['order_no', 'like', "%{$value}%"],
                    ['express_no', 'like', "%{$value}%"],
                    ['customer_name', 'like', "%{$value}%"],
                    ['customer_phone', 'like', "%{$value}%"],
                    ['remark', 'like', "%{$value}%"]
                ]);
            })->whereOr(function($subQuery) use ($value) {
                // 同时搜索关联的设备信息
                $subQuery->whereExists(function($deviceQuery) use ($value) {
                    $deviceQuery->table($this->getTable('recycle_device'))
                               ->whereRaw($this->getTable('recycle_device') . '.order_id = ' . $this->getTable() . '.id')
                               ->where(function($deviceSubQuery) use ($value) {
                                   $deviceSubQuery->whereOr([
                                       ['imei', 'like', "%{$value}%"],
                                       ['model', 'like', "%{$value}%"]
                                   ]);
                               });
                });
            });
        }
    }

    // 搜索器 关键词搜索 - 支持订单号、用户昵称、手机号、IMEI的联合搜索
    public function searchKeywordAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where(function($subQuery) use ($value) {
                // 搜索本表字段
                $subQuery->whereOr([
                    ['id', 'like', "%{$value}%"],
                    ['order_no', 'like', "%{$value}%"],
                    ['customer_name', 'like', "%{$value}%"],
                    ['customer_phone', 'like', "%{$value}%"]
                ]);
            })->whereOr(function($subQuery) use ($value) {
                // 搜索关联设备
                $subQuery->whereExists(function($deviceQuery) use ($value) {
                    $deviceQuery->table($this->getTable('recycle_device'))
                               ->whereRaw($this->getTable('recycle_device') . '.order_id = ' . $this->getTable() . '.id')
                               ->where('imei', 'like', "%{$value}%");
                });
            })->whereOr(function($subQuery) use ($value) {
                // 搜索关联会员
                $subQuery->whereExists(function($memberQuery) use ($value) {
                    $memberQuery->table($this->getTable('member'))
                               ->whereRaw($this->getTable('member') . '.member_id = ' . $this->getTable() . '.member_id')
                               ->where(function($memberSubQuery) use ($value) {
                                   $memberSubQuery->whereOr([
                                       ['nickname', 'like', "%{$value}%"],
                                       ['mobile', 'like', "%{$value}%"]
                                   ]);
                               });
                });
            });
        }
    }

    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('member_id', $value);
        }
    }
    // 搜索器 本表 delete_at 不等于 0
    public function searchDeleteAtAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('delete_at', $value);
        }
    }
}
