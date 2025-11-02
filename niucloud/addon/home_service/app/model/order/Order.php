<?php


namespace addon\home_service\app\model\order;

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\MemberOrderDict;
use addon\home_service\app\dict\order\TechnicianOrderDict;
use addon\home_service\app\dict\order\StoreOrderDict;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\traits\nearby\scopeNearbyTrait;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use app\dict\common\ChannelDict;
use app\model\member\Member;
use app\model\pay\Pay;
use core\base\BaseModel;
use think\Model;
use think\model\concern\SoftDelete;
use addon\home_service\app\model\goods\GoodsCategory;


/**
 *  卡项订单
 * Class O2oGoodsCategory
 * @package app\model\o2o_goods_category
 */
class Order extends BaseModel
{
    use scopeNearbyTrait;

    public $role; // 模型自身的角色属性


    public static $contextRole = null;


    // 提供设置角色的方法
    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }


    use SoftDelete;


    const ROLE_MEMBER = 'member';
    const ROLE_TECHNICIAN = 'technician';
    const ROLE_STORE = 'store';
    const ROLE_SYSTEM = 'system';


    /**
     * 定义软删除标记字段
     * @var string
     */
    protected $deleteTime = 'delete_time';

    /**
     * 定义软删除字段的默认值
     * @var int
     */
    protected $defaultSoftDelete = 0;


    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'order_id';


    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_order';

    protected $type = [
        'pay_time' => 'timestamp',
        'create_time' => 'timestamp',
        'close_time' => 'timestamp',
        'service_time' => 'timestamp',
        'dispatch_time' => 'timestamp',
        'finish_time' => 'timestamp',

        'take_photos_time' => 'timestamp',
        'service_finish_time' => 'timestamp',

    ];


    /**
     * 登录渠道字段转化
     * @param $value
     * @return mixed
     */
    public function getOrderFromNameAttr($value, $data)
    {
        if (isset($data['order_from'])) {
            return ChannelDict::getType()[$data['order_from']] ?? '';
        }
    }


    /**
     * 订单状态筛选器
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构造器
     * @param mixed $targetStatus 目标筛选状态（如：待服务、异常订单）
     * @param array $data 模型数据（兼容保留）
     */
    public function searchOrderStatusAttr($query, $targetStatus, $data)
    {
        // 1. 空值直接返回，避免无效逻辑
        if (empty($targetStatus)) {
            return;
        }
        // 2. 特殊处理：筛选“异常订单”（单独分支，提前返回减少嵌套）
        if ($targetStatus === OrderDict::ABNORMAL_ORDER) {
            $query->where('is_abnormal', 1);
            return;
        }
        // 3. 普通状态筛选：按角色映射字典类（集中管理映射关系）
        $roleDictMap = [
            self::ROLE_MEMBER => MemberOrderDict::class,
            self::ROLE_TECHNICIAN => TechnicianOrderDict::class,
            self::ROLE_SYSTEM => OrderDict::class,
            self::ROLE_STORE => StoreOrderDict::class,
        ];
        $dictClass = $roleDictMap[self::$contextRole] ?? OrderDict::class;
        // 4. 安全获取状态列表（避免方法不存在/空数组问题）
        $orderStatusList = method_exists($dictClass, 'getSearchStatus')
            ? ($dictClass::getSearchStatus($targetStatus) ?? [$targetStatus])
            : [$targetStatus];
        if (empty($orderStatusList)) {
            $query->whereRaw('1 = 0');
            return;
        }
        // 6. 基础状态筛选 + 特殊状态附加条件（抽离独立判断，清晰）
        $query->whereIn('order_status', $orderStatusList);
        $this->addSpecialStatusFilter($query, $targetStatus);
    }

    /**
     * 特殊状态的附加筛选（如：待服务过滤退款）
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构造器
     * @param mixed $targetStatus 目标筛选状态
     */
    private function addSpecialStatusFilter($query, $targetStatus)
    {
        // 待服务状态：过滤“退款中”的订单（后续新增特殊规则直接加 if 即可）
        if ($targetStatus === OrderDict::WAIT_SERVICE) {
            $query->where('refund_status', '=', '');
        }
    }


    /**
     * 订单状态  门店特有子查询
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchTrueOrderStatusAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('order_status', '=', $value);
        }
    }


    /**
     * 订单状态
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOrderNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('order_name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 是否结算
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchIsSettlementAttr($query, $value, $data)
    {

        if ($value != '') {
            $query->where('is_settlement', '=', $value);
        }
    }


    /**
     * 标签id
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchLabelIdAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('label_id', '=', $value);
        }
    }


    public function searchOrderIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('order_id', '=', $value);
        }
    }


    /**
     * 会员id搜索
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('member_id', '=', $value);
        }
    }

    /**
     * 订单号
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOrderNoAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('order_no', 'like', '%' . $value . '%');
        }
    }

    /**
     * 订单来源
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOrderFromAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('order_from', '=', $value);
        }
    }


    /**
     * 创建时间搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchJoinCreateTimeAttr($query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('order.create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['order.create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['order.create_time', '<=', $end_time]]);
        }
    }


    /**
     * 创建时间搜索器
     * @param $value
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['create_time', '<=', $end_time]]);
        }
    }

    /**
     * 支付时间筛选
     * @param $query
     * @param $value
     * @param $data
     * @return void
     */
    public function searchPayTimeAttr($query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('pay_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['pay_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['pay_time', '<=', $end_time]]);
        }
    }

    /**
     * 购买会员筛选
     * @param $query
     * @param $value
     * @param $data
     * @return void
     */
    public function searchMemberSearchTextAttr($query, $value, $data)
    {
        if ($value) {
            $member_ids = (new Member)->where([['username|member_no|nickname|mobile', 'like', '%' . $value . '%'], ['site_id', '=', $data['site_id']]])->column('member_id');
            if ($member_ids) $query->where('member_id', 'in', $member_ids);
        }
    }

    /**
     * 技师筛选
     * @param $query
     * @param $value
     * @param $data
     * @return void
     */
    public function searchTechnicianSearchTextAttr($query, $value, $data)
    {
        if ($value) {
            $ids = (new Technician())->where([['real_name|mobile', 'like', '%' . $value . '%'], ['site_id', '=', $data['site_id']]])->column('id');
            if ($ids) {
                $query->where('technician_id', 'in', $ids);
            } else {
                $query->where('technician_id', 'in', '-1');
            }
        }
    }


    /**
     * 服务类型
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCategoryIdsAttr($query, $value, $data)
    {
        if ($value != '') {
            $category_arr = explode(',', $value);
            $query->where('category_id', 'in', $category_arr);
        }
    }

    /**
     * 服务类型
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCategoryIdAttr($query, $value, $data)
    {
        if ($value != 'all') {
            $query->where('category_id', '=', $value);
        }
    }

    /**
     * @param $value
     * @param $data
     * @return mixed|void
     * @throws \Exception
     */
    public function getOrderStatusInfoAttr($value, $data)
    {
        if (isset($data['order_status'])) {
            $dictMap = [
                self::ROLE_MEMBER => MemberOrderDict::class,
                self::ROLE_TECHNICIAN => TechnicianOrderDict::class,
                self::ROLE_SYSTEM => OrderDict::class,
                self::ROLE_STORE => StoreOrderDict::class,
            ];
            $dictClass = $dictMap[self::$contextRole] ?? OrderDict::class;
            $res = $dictClass::getStatus($data['order_status'], $data);
            return $res;
        }
    }


    /**
     * @param $value
     * @param $data 是否结算
     * @return mixed|void
     * @throws \Exception
     */
    public function getSettlementNameAttr($value, $data)
    {
        if (isset($data['is_settlement'])) {
            return AccountDict::getStatus()[$data['is_settlement']] ?? '';
        }
    }







    /**
     * 正常订单的业务提醒（含超时、服务中、验收倒计时、自动退款倒计时）
     * @param mixed $value 模型属性值（未使用，兼容保留）
     * @param array $data 订单模型数据
     * @return array 提醒信息（含类型、文本、时间数组）
     */
    /**
     * 正常订单的业务提醒（含超时、服务中、验收倒计时、自动退款倒计时）
     * @param mixed $value 模型属性值（未使用，兼容保留）
     * @param array $data 订单模型数据
     * @return array 提醒信息（含类型、文本、时间数组）
     */
    public function getTimeReminderAttr($value, $data)
    {
        // 1. 基础校验：无订单状态直接返回空
        if (!isset($data['order_status'])) {
            return [];
        }

        // -------------------------- 共用工具逻辑（抽离复用） --------------------------
        // 1.1 计算时间差（天/时/分/秒），支持按需返回秒数
        $calcTime = function (int $diff, bool $withSeconds = false): array {
            $days = floor($diff / 86400);
            $remaining = $diff % 86400;
            $hours = floor($remaining / 3600);
            $remaining %= 3600;
            $minutes = floor($remaining / 60);
            $seconds = $remaining % 60;
            return $withSeconds ? [$days, $hours, $minutes, $seconds] : [$days, $hours, $minutes];
        };

        // 1.2 格式化时间文本（最多2个单位，大单位优先）
        $formatTimeText = function (int $days, int $hours, int $minutes, int $seconds = 0): string {
            $parts = [];
            $days > 0 && $parts[] = "{$days}天";
            !$parts && $hours > 0 && $parts[] = "{$hours}小时";
            count($parts) < 2 && $hours > 0 && !in_array("{$hours}小时", $parts) && $parts[] = "{$hours}小时";
            count($parts) < 2 && $minutes > 0 && $parts[] = "{$minutes}分钟";
            count($parts) < 2 && $seconds > 0 && $parts[] = "{$seconds}秒";
            return implode('', array_slice($parts, 0, 2));
        };

        // 1.3 统一处理“不足1分钟补全”+构建time_array
        $handleTimeAndBuildArray = function (int $days, int $hours, int $minutes, int $seconds = 0): array {
            // 不足1分钟按1分钟算，秒数清零
            if ($days === 0 && $hours === 0 && $minutes === 0) {
                $minutes = 1;
                $seconds = 0;
            }
            // 统一返回time_array（d/h/m/s，确保字段完整）
            return [
                'time_array' => compact('days', 'hours', 'minutes', 'seconds'),
                'days' => $days,
                'hours' => $hours,
                'minutes' => $minutes,
                'seconds' => $seconds
            ];
        };

        // -------------------------- 场景1：自动退款倒计时（优先处理，提前返回） --------------------------
        if (isset($data['refund_status']) && !empty($data['refund_status']) && !empty($data['auto_refund_time'])) {
            // 计算“自动退款”剩余时间（非负）
            $refundCountdownDiff = max(0, $data['auto_refund_time'] - time());
            list($days, $hours, $minutes, $seconds) = $calcTime($refundCountdownDiff, true);

            // 处理时间补全+构建time_array
            $timeData = $handleTimeAndBuildArray($days, $hours, $minutes, $seconds);

            return [
                'type' => 'auto_refund_time',
                'text' => "距自动通过审核还剩" . $formatTimeText(
                        $timeData['days'],
                        $timeData['hours'],
                        $timeData['minutes'],
                        $timeData['seconds']
                    ),
                'time_array' => $timeData['time_array']
            ];
        }

        // -------------------------- 场景2：普通订单状态提醒（无else，逻辑更扁平） --------------------------
        switch ($data['order_status']) {
            // 派单
            case OrderDict::WAIT_DISPATCH:
                if (empty($data['dispatch_timeout_time'])) return [];
                $checkCountdownDiff = max(0, $data['dispatch_timeout_time'] - time());
                list($days, $hours, $minutes, $seconds) = $calcTime($checkCountdownDiff, true);
                $timeData = $handleTimeAndBuildArray($days, $hours, $minutes, $seconds);
                return [
                    'color' => "#00216B",
                    'type' => 'check_countdown',
                    'text' => "派单截止时间还剩",
                    'time_array' => $timeData['time_array']
                ];
                return [];
            // 待服务：超时/即将超时
            case OrderDict::WAIT_SERVICE:
                if (empty($data['reserve_service_time_stamp'])) return [];

                $reserveTime = $data['reserve_service_time_stamp'];
                $currentTime = time();
                $isOvertime = $currentTime > $reserveTime;
                $timeDiff = $isOvertime ? $currentTime - $reserveTime : $reserveTime - $currentTime;

                // 计算时间（无需秒数）
                list($days, $hours, $minutes) = $calcTime($timeDiff);
                // 处理时间补全+构建time_array（秒数默认0）
                $timeData = $handleTimeAndBuildArray($days, $hours, $minutes, 0);

                // 已超时
                if ($isOvertime) {
                    $text = "您已超时" . ($days ? "{$days}天{$minutes}分钟" : ($hours ? "{$hours}小时{$minutes}分钟" : "{$minutes}分钟"));
                    return ['type' => 'timeout', 'text' => $text, 'time_array' => $timeData['time_array'], 'color' => "#00216B"];
                }


                // 即将超时（配置兼容：避免config不存在报错）
                $orderConfig = (new CoreOrderConfigService())->getOrderConfig($data['site_id'] ?? 0) ?? [];
                $aboutToTimeout = $orderConfig['order_time']['about_to_timeout_time'] ?? 0;
                if ($timeDiff <= $aboutToTimeout * 60) {
                    $text = "即将超时(还剩" . ($days ? "{$days}天{$minutes}分钟" : ($hours ? "{$hours}小时{$minutes}分钟" : "{$minutes}分钟")) . ")";
                    return ['type' => 'about_to_timeout', 'text' => $text, 'time_array' => $timeData['time_array']];
                }
                return [];
            // 服务中：已服务时长
            case OrderDict::IN_SERVICE:
                if (empty($data['service_time'])) return [];

                $serviceDurationDiff = time() - $data['service_time'];
                list($days, $hours, $minutes, $seconds) = $calcTime($serviceDurationDiff, true);
                $timeData = $handleTimeAndBuildArray($days, $hours, $minutes, $seconds);

                return [
                    'color' => "#004FFF",
                    'type' => 'service_time',
                    'text' => "已服务" . $formatTimeText(
                            $timeData['days'],
                            $timeData['hours'],
                            $timeData['minutes'],
                            $timeData['seconds']
                        ),
                    'time_array' => $timeData['time_array']
                ];

            // 待验收：验收倒计时
            case OrderDict::WAIT_CHECK:
                if (empty($data['auto_check_time'])) return [];
                $checkCountdownDiff = max(0, $data['auto_check_time'] - time());
                list($days, $hours, $minutes, $seconds) = $calcTime($checkCountdownDiff, true);
                $timeData = $handleTimeAndBuildArray($days, $hours, $minutes, $seconds);
                return [
                    'color' => "#00CA83",
                    'type' => 'check_countdown',
                    'text' => "距离验收还剩" . $formatTimeText(
                            $timeData['days'],
                            $timeData['hours'],
                            $timeData['minutes'],
                            $timeData['seconds']
                        ),
                    'time_array' => $timeData['time_array']
                ];
            // 服务完成
            case OrderDict::FINISH:
                if (!isset($data['service_finish_time']) || !isset($data['service_time'])) return [];
                $serviceDurationDiff = $data['service_finish_time'] - $data['service_time'];
                list($days, $hours, $minutes, $seconds) = $calcTime($serviceDurationDiff, true);
                $timeData = $handleTimeAndBuildArray($days, $hours, $minutes, $seconds);
                return [
                    'type' => 'order_finish',
                    'text' => $formatTimeText(
                        $timeData['days'],
                        $timeData['hours'],
                        $timeData['minutes'],
                        $timeData['seconds']
                    ),
                    'time_array' => $timeData['time_array']
                ];
            // 其他状态：返回空
            default:
                return [];
        }
    }


    /**
     * 抢单类型
     * @return \think\model\relation\HasOne
     */
    public function getGrabTypeNameAttr($value, $data)
    {
        return (isset($data['store_id']) && !empty($data['store_id'])) ? "派单" : "自抢";
    }

    /**
     * 技师名称
     * @return \think\model\relation\HasOne
     */
    public function getTechnicianNameAttr($value, $data)
    {
        return (new Technician())->where([['id', '=', $data['technician_id']]])->value('real_name');
    }


    /**
     * 回访状态
     * @return \think\model\relation\HasOne
     */
    public function getFollowStatusNameAttr($value, $data)
    {
        return (isset($data['follow_id']) && !empty($data['follow_id'])) ? "已回访" : "待回访";
    }


    public function item()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id')->append(['item_image_thumb_small', 'refund_status_name', 'item_image_thumb_mid']);
    }

    /**
     * 关联会员
     * @return \think\model\relation\HasOne
     */
    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id');
    }

    /**
     * 订单日志
     * @return \think\model\relation\HasMany
     */
    public function orderlog()
    {
        return $this->hasMany(OrderLog::class, 'order_id', 'order_id');
    }

    /**
     * 技师
     * @return \think\model\relation\HasMany
     */
    public function technician()
    {
        return $this->hasOne(Technician::class, 'id', 'technician_id');
    }

    /**
     * 支付记录
     * @return \think\model\relation\HasOne
     */
    public function pay()
    {
        return $this->hasOne(Pay::class, 'out_trade_no', 'out_trade_no');
    }

    public function refund()
    {
        return $this->hasOne(OrderRefund::class, 'refund_no', 'refund_no')->bind(['refund_id' => 'refund_id']);
    }


    public function store()
    {
        return $this->hasOne(Store::class, 'store_id', 'store_id');
    }

    public static function formatTime($timestamp)
    {
        $weekMap = ['日', '一', '二', '三', '四', '五', '六'];
        return date("n月j日 周", $timestamp) . $weekMap[date("w", $timestamp)] . date(" H:i", $timestamp);
    }


    /**
     * 关联分类
     * @return \think\model\relation\HasOne
     */
    public function goodsCategory()
    {
        return $this->hasOne(GoodsCategory::class, 'category_id', 'category_id');
    }


    /**
     * 订单标签
     * @return \think\model\relation\HasOne
     */
    public function label()
    {
        return $this->hasOne(OrderLabel::class, 'label_id', 'label_id');
    }


    /**
     * 订单 评价
     * @return \think\model\relation\HasOne
     */
    public function evaluate()
    {
        return $this->hasOne(Evaluate::class, 'order_id', 'order_id');
    }


    /**
     * 订单  回访
     * @return \think\model\relation\HasOne
     */
    public function orderfollow()
    {
        return $this->hasOne(OrderFollow::class, 'order_id', 'order_id');
    }


    /**
     * 订单回访
     * @return \think\model\relation\HasOne
     */
    public function follow()
    {
        return $this->hasOne(OrderFollow::class, 'follow_id', 'follow_id')
            ->with([
                'sysUser' => function ($query) {
                    $query->field('username,uid');
                }
            ]);

    }

    public function itemImage()
    {
        return $this->hasOne(OrderItem::class, 'order_id', 'order_id')->where('item_id', '>', 0)->append(['item_image_thumb_small'])->bind(['item_image', 'item_image_thumb_small']);
    }


}


