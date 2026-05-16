<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;
use app\model\sys\SysUser;
use app\model\member\Member;

use addon\recycle\app\dict\order\RecycleOrderDict;

/**
 * 回收设备模型
 * Class RecycleDevice
 * @package addon\recycle\app\model
 */
class RecycleDevice extends BaseModel
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
    protected $name = 'recycle_device';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    protected $json = ['info'];

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
     * 支付时间字段
     * @var string
     */
    protected $payTime = 'pay_time';

    /**
     * 追加属性
     * @var array
     */
    protected $append = [
        'status_name',
        'category_name',
        'nickname'
    ];

    /**
     * 获取设备状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        return RecycleOrderDict::getDeviceStatus($data['status'] ?? '');
    }

    /**
     * 获取分类名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getCategoryNameAttr($value, $data)
    {
        $categories = [
            1 => '手机',
            2 => '平板',
            3 => '笔记本',
            4 => '手表',
            5 => '其他'
        ];
        return $categories[$data['category_id'] ?? 1] ?? '手机';
    }

    /**
     * 获取质检状态名称
     * @param $value
     * @param $data
     * @return string
     */
    // public function getCheckStatusNameAttr($value, $data)
    // {
    //     $status = [
    //         0 => '未质检',
    //         1 => '质检中',
    //         2 => '已质检'
    //     ];
    //     return $status[$data['check_status']] ?? '';
    // }

    /**
     * 关联订单表
     * @return \think\model\relation\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(RecycleOrder::class, 'order_id', 'id');
    }

    /**
     * 关联退货设备表
     * @return \think\model\relation\HasOne
     */
    public function returnDevice()
    {
        return $this->hasOne(RecycleReturnDevice::class, 'device_id', 'id');
    }

    /**
     * 质检图片获取器
     * @param $value
     * @return array
     */
    public function getCheckImagesAttr($value)
    {
        return ''.$value;
    }

    /**
     * 质检图片修改器
     * @param $value
     * @return string
     */
    public function setCheckImagesAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    /**
     * 搜索器 本表 imei 模糊匹配
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchImeiAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('imei', 'like', "%{$value}%");
        }
    }

    /**
     * 搜索器 本表 model 模糊匹配
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchModelAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('model', 'like', "%{$value}%");
        }
    }

    /**
     * 搜索器 本表 status 精确匹配
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器 本表 create_at 日期范围查询
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchUpdateAtAttr($query, $value, $data)
    {
        if (is_array($value)) {
            if (!empty($value[0]) && !empty($value[1])) {
                $query->whereBetweenTime('update_at', $value[0], $value[1]);
            }
        }
    }

    // 质检员关联查询 sys_user  本表 check_uid 关联 sys_user 的 id
    public function checkUser()
    {
        return $this->belongsTo(SysUser::class, 'check_uid', 'uid')
        ->field('uid,username,real_name');
    }

    // 定价员关联查询 sys_user  本表 price_uid 关联 sys_user 的 id
    public function priceUser()
    {
        return $this->belongsTo(SysUser::class, 'price_uid', 'uid')
        ->field('uid,username,real_name');
    }

    // 供应商关联查询 member  本表 member_id 关联 member 的 id
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id')
        ->field('member_id,nickname');
         
    }

    // 将nickname 放到第一层数组中
    public function getNicknameAttr($value, $data)
    {
        // 检查member_id是否存在
        if (!isset($data['member_id']) || empty($data['member_id'])) {
            return '未知';
        }
        
        $member = new Member();
        $member = $member->where('member_id', $data['member_id'])->field('nickname')->find();
        return $member['nickname'] ?? '未知';  
    }
 
}
