<?php
namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 收款方式信息模型
 */
class PhoneShopPaymentInfo extends BaseModel
{
    /**
     * 数据表名
     * @var string
     */
    protected $name = 'payment_info';
    
    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';
    
    /**
     * 允许写入的字段
     * @var array
     */
    protected $allowField = [
        'member_id',
        'pay_type',
        'account',
        'qrcode_image',
        'is_default',
        'create_time',
        'update_time'
    ];
    
    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;
    
    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_time';
    
    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'update_time';
    
    /**
     * 追加的字段
     * @var array
     */
    protected $append = [];
} 