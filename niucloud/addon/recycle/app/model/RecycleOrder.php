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
     * 签收时间字段
     * @var string
     */
    protected $signTime = 'sign_at';

    /**
     * 完成时间字段
     * @var string
     */
    protected $completeTime = 'complete_at';

    /**
     * 打款时间字段
     * @var string
     */
    protected $payTime = 'pay_time';

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

    /**
     * 参数映射 - 将前端参数映射为搜索器参数
     * @param array $params 前端传入的参数
     * @return array 映射后的搜索器参数
     */
    public static function mapSearchParams(array $params): array
    {
        $searchParams = [];
        
        // 定义参数映射规则
        $mappingRules = [
            // 前端参数 => 搜索器参数
            'order_no' => 'order_no',           // 订单号
            'order_id' => 'id',                 // 订单ID
            'express_no' => 'express_no',       // 快递单号
            'remark' => 'remark',               // 备注
            'device_imei' => 'imei',            // 设备IMEI
            'device_model' => 'device_model',   // 设备型号
            'user_nickname' => 'customer_name', // 用户昵称
            'user_mobile' => 'customer_phone',  // 用户手机
            'keyword' => 'keyword',             // 关键词综合搜索
            'search' => 'search',               // 通用搜索
            'member_id' => 'member_id',         // 会员ID
            'status' => 'status',               // 订单状态
            'delivery_type' => 'delivery_type', // 配送方式
        ];
        
        // 应用映射规则
        foreach ($mappingRules as $frontParam => $searchParam) {
            if (isset($params[$frontParam]) && $params[$frontParam] !== '') {
                $value = $params[$frontParam];
                
                // 处理逗号分隔的字符串（转为数组）
                if (is_string($value) && strpos($value, ',') !== false) {
                    $value = explode(',', $value);
                }
                
                $searchParams[$searchParam] = $value;
            }
        }
        
        // 处理时间参数（支持多种格式）
        // 创建时间
        if (!empty($params['create_time_start']) && !empty($params['create_time_end'])) {
            // 格式1: create_time_start + create_time_end
            $searchParams['create_at'] = self::formatTimeRange($params['create_time_start'], $params['create_time_end']);
        } elseif (!empty($params['create_at']) && is_array($params['create_at']) && count($params['create_at']) == 2) {
            // 格式2: create_at 数组
            $searchParams['create_at'] = self::formatTimeRange($params['create_at'][0], $params['create_at'][1]);
        }
        
        // 打款时间
        if (!empty($params['pay_time']) && is_array($params['pay_time']) && count($params['pay_time']) == 2) {
            $searchParams['pay_time'] = self::formatTimeRange($params['pay_time'][0], $params['pay_time'][1]);
        }
        
        // 签收时间
        if (!empty($params['sign_at']) && is_array($params['sign_at']) && count($params['sign_at']) == 2) {
            $searchParams['sign_at'] = self::formatTimeRange($params['sign_at'][0], $params['sign_at'][1]);
        }
        
        // 完成时间
        if (!empty($params['complete_at']) && is_array($params['complete_at']) && count($params['complete_at']) == 2) {
            $searchParams['complete_at'] = self::formatTimeRange($params['complete_at'][0], $params['complete_at'][1]);
        }
        
        return $searchParams;
    }
    
    /**
     * 格式化时间范围
     * 如果开始和结束是同一天，自动扩展为当天的 00:00:00 到 23:59:59
     * 
     * @param string $start 开始时间
     * @param string $end 结束时间
     * @return array
     */
    private static function formatTimeRange(string $start, string $end): array
    {
        // 处理开始时间
        if (strlen($start) == 10) {
            // 如果只有日期，添加时间部分
            $start = $start . ' 00:00:00';
        }
        
        // 处理结束时间
        if (strlen($end) == 10) {
            // 如果只有日期，添加时间部分
            $end = $end . ' 23:59:59';
        }
        
        // 如果是同一天（比较日期部分）
        $startDate = substr($start, 0, 10);
        $endDate = substr($end, 0, 10);
        
        if ($startDate === $endDate) {
            // 确保是当天的完整时间范围
            return [$startDate . ' 00:00:00', $startDate . ' 23:59:59'];
        }
        
        return [$start, $end];
    }
    
    /**
     * 获取可用的搜索器列表
     * @return array
     */
    public static function getSearchFields(): array
    {
        return [
            'id', 'order_no', 'express_no', 'customer_name', 'customer_phone', 
            'status', 'delivery_type', 'create_at', 'remark', 'imei', 
            'device_model', 'search', 'keyword', 'member_id', 'delete_at', 'sign_at', 'complete_at', 'pay_time'
        ];
    }
    public function searchSignAtAttr($query, $value, $data)
    {
        if (is_array($value) && count($value) == 2) {
            if (!empty($value[0]) && !empty($value[1])) {
                $query->whereBetweenTime('sign_at', $value[0], $value[1]);
            }
        }
    }
    public function searchCompleteAtAttr($query, $value, $data)
    {
        if (is_array($value) && count($value) == 2) {
            if (!empty($value[0]) && !empty($value[1])) {
                $query->whereBetweenTime('complete_at', $value[0], $value[1]);
            }
        }
    }
    public function searchPayTimeAttr($query, $value, $data)
    {
        if (is_array($value) && count($value) == 2) {
            if (!empty($value[0]) && !empty($value[1])) {
                $query->whereBetweenTime('pay_time', $value[0], $value[1]);
            }
        }
    }
}
