<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;
use addon\recycle\app\dict\order\RecycleReturnOrderDict;
use addon\recycle\app\model\recycle_user_address\RecycleUserAddress;
use app\model\member\Member;
use app\model\site\Site;

/**
 * 退回订单模型
 * Class RecycleReturnOrder
 * @package addon\recycle\app\model
 */
class RecycleReturnOrder extends BaseModel
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
    protected $name = 'recycle_return_order';

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
     * 完成时间字段
     * @var string
     */
    protected $overTime = 'over_at';

    /**
     * 追加属性
     * @var array
     */
    protected $append = [
        'status_name'
    ];

    /**
     * 搜索字段
     * @var array
     */
    protected $searchFields = [
        'order_no' => '%like%',
        'status' => '=',
        'express_no' => '%like%',
        'express_company' => '%like%',
        'comment' => '%like%',
        'create_at' => 'between'
    ];

    /**
     * 获取订单状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        $status = RecycleReturnOrderDict::getOrderStatus($data['status'] ?? '');
        return $status['name'] ?? '';
    }

    /**
     * 关联退回设备表
     * @return \think\model\relation\HasMany
     */
    public function returnDevices()
    {
        return $this->hasMany(RecycleReturnDevice::class, 'return_order_id', 'id');
    }

    /**
     * 关联会员表
     * @return \think\model\relation\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
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
     * 关联原始订单表
     * @return \think\model\relation\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(RecycleOrder::class, 'order_id', 'id');
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

    // 搜索器 本表 express_company 模糊匹配
    public function searchExpressCompanyAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('express_company', 'like', "%{$value}%");
        }
    }

    // 搜索器 本表 comment 模糊匹配
    public function searchCommentAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('comment', 'like', "%{$value}%");
        }
    }

    // 搜索器 本表 create_at 日期范围查询
    public function searchCreateAtAttr($query, $value, $data)
    {
        if (is_array($value)) {
            if (!empty($value[0]) && !empty($value[1])) {
                $query->whereBetweenTime('create_at', $value[0], $value[1]);
            }
        }
    }

    // 搜索器 本表 status 精确匹配
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }

    // 搜索器 本表 order_no 模糊匹配
    public function searchOrderNoAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('order_no', 'like', "%{$value}%");
        }
    }

    // 搜索器 本表 delete_at 不等于 0
    public function searchDeleteAtAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('delete_at', $value);
        }
    }
    // memberAddress
    public function memberAddress(){
        // ShopAddress
        return $this->hasOne(RecycleUserAddress::class, 'member_id', 'member_id');
    }
}
