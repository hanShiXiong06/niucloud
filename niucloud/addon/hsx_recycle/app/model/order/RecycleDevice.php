<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\phone_shop\app\model\goods\Category as PhoneShopGoodsCategory;
use addon\hsx_recycle\app\model\check\RecycleCheckTemplate;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use app\model\member\Member;
use app\model\sys\SysUser;
use app\dict\sys\FileDict;
use core\base\BaseModel;

/**
 * 回收设备模型
 * Class RecycleDevice
 * @package addon\hsx_recycle\app\model
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
        'nickname',
        'check_images_thumb_small',
        'check_images_seller_thumb_small',
        'check_images_buyer_thumb_small',
        'pay_status_name',
        'confirm_status_name',
        'dispose_type_name',
        'dispose_status_name',
        'sale_destination_name',
        'check_template_name',
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
     * 获取设备打款状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getPayStatusNameAttr($value, $data)
    {
        return RecycleOrderDict::getPayStatus($data['pay_status'] ?? RecycleOrderDict::PAY_STATUS_UNPAID);
    }

    /**
     * 获取设备报价确认状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getConfirmStatusNameAttr($value, $data)
    {
        return RecycleOrderDict::getConfirmStatus($data['confirm_status'] ?? RecycleOrderDict::CONFIRM_STATUS_PENDING);
    }

    public function getDisposeTypeNameAttr($value, $data): string
    {
        return RecycleOrderDict::getDisposeType($data['dispose_type'] ?? RecycleOrderDict::DISPOSE_TYPE_PENDING) ?: '未处置';
    }

    public function getDisposeStatusNameAttr($value, $data): string
    {
        return RecycleOrderDict::getDisposeStatus($data['dispose_status'] ?? RecycleOrderDict::DISPOSE_STATUS_PENDING) ?: '未处置';
    }

    public function getSaleDestinationNameAttr($value, $data): string
    {
        $destination = (string)($data['sale_destination'] ?? RecycleOrderDict::SALE_DESTINATION_MALL);
        return RecycleOrderDict::SALE_DESTINATION_TEXT[$destination] ?? '商城销售';
    }

    /**
     * 获取质检模板名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getCheckTemplateNameAttr($value, $data): string
    {
        $templateId = (int)($data['check_template_id'] ?? 0);
        if ($templateId <= 0) {
            return '';
        }

        return (string)(RecycleCheckTemplate::where([
            ['id', '=', $templateId],
            ['site_id', '=', (int)($data['site_id'] ?? 0)]
        ])->value('template_name') ?: '');
    }

    /**
     * 获取分类名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getCategoryNameAttr($value, $data)
    {
        $categoryId = (int)($data['category_id'] ?? 0);
        if ($categoryId > 0 && class_exists(PhoneShopGoodsCategory::class)) {
            try {
                $name = (new PhoneShopGoodsCategory())->where('category_id', $categoryId)->value('category_name');
                if (!empty($name)) {
                    return $name;
                }
            } catch (\Throwable $e) {
                // ignore query error and fallback to legacy map
            }
        }

        $categories = [
            1 => '手机',
            2 => '平板',
            3 => '笔记本',
            4 => '手表',
            5 => '其他'
        ];
        return $categories[$categoryId ?: 1] ?? '手机';
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

    public function consignmentOrder()
    {
        return $this->hasOne(RecycleConsignmentOrder::class, 'source_device_id', 'id');
    }

    public function paymentRecords()
    {
        return $this->hasMany(RecycleDevicePayment::class, 'device_id', 'id');
    }

    public function costAdjustments()
    {
        return $this->hasMany(RecycleDeviceCostAdjustment::class, 'device_id', 'id');
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
     * 卖家可见质检图片获取器
     * @param $value
     * @return string
     */
    public function getCheckImagesSellerAttr($value)
    {
        return '' . $value;
    }

    /**
     * 卖家可见质检图片修改器
     * @param $value
     * @return string
     */
    public function setCheckImagesSellerAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    /**
     * 买家可见质检图片获取器
     * @param $value
     * @return string
     */
    public function getCheckImagesBuyerAttr($value)
    {
        return '' . $value;
    }

    /**
     * 买家可见质检图片修改器
     * @param $value
     * @return string
     */
    public function setCheckImagesBuyerAttr($value)
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

    /**
     * 搜索器 导出状态筛选
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchExportStatusAttr($query, $value, $data)
    {
        if ($value === 'unexported') {
            $query->where(function ($q) {
                $q->where('export_time', 0)->whereOr('export_time', null);
            });
        }
        // 值为空或 'all' 时不加条件
    }
     /**
     * 搜索器 按设备ID列表筛选（用于勾选导出）
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchDeviceIdsAttr($query, $value, $data)
    {
        if (!empty($value) && is_array($value)) {
            $query->whereIn('id', $value);
        }
    }

    public function searchWarehouseTypeAttr($query, $value, $data)
    {
        if ($value === 'consign') {
            $query->where('dispose_type', RecycleOrderDict::DISPOSE_TYPE_CONSIGN);
        } elseif ($value === 'owned') {
            $query->where(function ($q) {
                $q->where('dispose_type', '<>', RecycleOrderDict::DISPOSE_TYPE_CONSIGN)
                    ->whereOr('dispose_type', null)
                    ->whereOr('dispose_type', '');
            });
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

    /**
     * 质检图片缩略图（小）获取器
     * @param $value
     * @param $data
     * @return array
     */
    public function getCheckImagesThumbSmallAttr($value, $data)
    {
        return $this->buildImageThumbs($data, 'check_images', FileDict::SMALL);
    }

    /**
     * 卖家质检图片缩略图（小）获取器
     * @param $value
     * @param $data
     * @return array
     */
    public function getCheckImagesSellerThumbSmallAttr($value, $data)
    {
        return $this->buildImageThumbs($data, 'check_images_seller', FileDict::SMALL);
    }

    /**
     * 买家质检图片缩略图（小）获取器
     * @param $value
     * @param $data
     * @return array
     */
    public function getCheckImagesBuyerThumbSmallAttr($value, $data)
    {
        return $this->buildImageThumbs($data, 'check_images_buyer', FileDict::SMALL);
    }

    /**
     * 质检图片缩略图（中）获取器
     * @param $value
     * @param $data
     * @return array
     */
    public function getCheckImagesThumbMidAttr($value, $data)
    {
        return $this->buildImageThumbs($data, 'check_images', FileDict::MID);
    }

    /**
     * 卖家质检图片缩略图（中）获取器
     * @param $value
     * @param $data
     * @return array
     */
    public function getCheckImagesSellerThumbMidAttr($value, $data)
    {
        return $this->buildImageThumbs($data, 'check_images_seller', FileDict::MID);
    }

    /**
     * 买家质检图片缩略图（中）获取器
     * @param $value
     * @param $data
     * @return array
     */
    public function getCheckImagesBuyerThumbMidAttr($value, $data)
    {
        return $this->buildImageThumbs($data, 'check_images_buyer', FileDict::MID);
    }

    /**
     * 构建逗号分隔图片字段的缩略图数组
     * @param array $data 模型数据
     * @param string $field 图片字段名
     * @param string $thumbType 缩略图类型 (small/mid/big)
     * @return array
     */
    private function buildImageThumbs(array $data, string $field, string $thumbType): array
    {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            return [];
        }
        $images = explode(',', (string)$data[$field]);
        $thumbArr = [];
        foreach ($images as $img) {
            $img = trim($img);
            if (empty($img)) continue;
            $siteId = $data['site_id'] ?? 0;
            $thumb = get_thumb_images($siteId, $img, $thumbType);
            if (!empty($thumb)) {
                $thumbArr[] = $thumb;
            }
        }
        return $thumbArr;
    }

}
