<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\model\technician;

use addon\home_service\app\dict\technician\TechnicianDict;
use addon\home_service\app\model\goods\GoodsCategory;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\store\StoreTechnician;
use addon\home_service\app\model\traits\technician\TechnicianScopeNearbyTrait;
use app\dict\sys\FileDict;
use app\model\member\Member;
use core\base\BaseModel;
use think\db\Query;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 师傅模型
 * Class Technician
 * @package app\model\o2o_technician
 */
class Technician extends BaseModel
{

    use TechnicianScopeNearbyTrait;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_technician';


    /**
     * 转化状态
     * @param $value
     * @param $data
     */
    public function getStatusNameAttr($value, $data)
    {
        return TechnicianDict::getTechnicianStatus()[$data['status']] ?? '';
    }


    /**
     * 分红方式状态
     * @param $value
     * @param $data
     */
    public function getDistributeNameAttr($value, $data)
    {
        return $data['distribute_type'] ? TechnicianDict::getTechnicianDistributeName()[$data['distribute_type']] : '';
    }


    /**
     * 来源类型
     * @param $value
     * @param $data
     */
    public function getSourceNameAttr($value, $data)
    {
        return $data['source'] ? TechnicianDict::getTechnicianSourceName()[$data['source']] : '';
    }


    /**
     * 头像
     * @param $value
     * @param $data
     */
    public function getHeadimgMidAttr($value, $data)
    {
        $thumb = '';
        if ($data['headimg'] != '') {
            $thumb = get_thumb_images($data['site_id'], $data['headimg'], FileDict::MID);
        }
        return $thumb;
    }

    /**
     * 头像
     * @param $value
     * @param $data
     */
    public function getHeadimgBigAttr($value, $data)
    {
        $thumb = '';
        if ($data['headimg'] != '') {
            $thumb = get_thumb_images($data['site_id'], $data['headimg'], FileDict::BIG);
        }
        return $thumb;
    }

    /**
     * 图片
     * @param $value
     * @param $data
     */
    public function getImagesMidAttr($value, $data)
    {
        $thumb_arr = [];
        if ($data['images'] != '') {
            $img_arr = explode(",", $data['images']);
            foreach ($img_arr as $item) {
                $thumb_arr[] = get_thumb_images($data['site_id'], $item, FileDict::MID);
            }
        }
        return $thumb_arr;
    }

    /**
     * 图片
     * @param $value
     * @param $data
     */
    public function getImagesBigAttr($value, $data)
    {
        $thumb_arr = [];
        if ($data['images'] != '') {
            $img_arr = explode(",", $data['images']);
            foreach ($img_arr as $item) {
                $thumb_arr[] = get_thumb_images($data['site_id'], $item, FileDict::BIG);
            }
        }
        return $thumb_arr;
    }


    /**
     * 创建时间搜索器
     * @param $value
     */
    public function searchCreateTimeAttr(Query $query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('technician.create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['technician.create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['technician.create_time', '<=', $end_time]]);
        }
    }


    /**
     * 姓名搜索器
     * @param $value
     */
    public function searchRealNameAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('technician.real_name', 'like', '%' . $value . '%');
        }
    }


    /**
     * 搜索器
     * @param $value
     */
    public function searchStoreIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('technician.store_id', '=', $value);
        }
    }


    /**
     * 搜索器
     * @param $value
     */
    public function searchLevelIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('level_id', '=', $value);
        }
    }

    public function searchSourceAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('technician.source', '=', $value);
        }

    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value != 'all') {
            $query->where('technician.status', '=', $value);
        }

    }

    public function searchCategoryIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->whereRaw("FIND_IN_SET(?, category_id)", [$value]);
        }

    }



    /**
     * 获取类目数据
     * @param $value
     * @param $data
     * @return array
     */
    public function getCategoryNameAttr($value, $data)
    {
        if (!isset($data['category_id']) || empty($data['category_id'])) return [];
        return (new GoodsCategory())->where([['category_id', 'in', $data['category_id']]])->field('category_name,category_id')->select()->toArray() ?? [];
    }


    /**
     * 会员关联
     * @return HasOne
     */
    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id')->withField('member_id, member_no, username, mobile, nickname, headimg')->joinType('inner');
    }

    /**
     * 门店关联
     * @return HasOne
     */
    public function store()
    {
        return $this->hasOne(Store::class, 'store_id', 'store_id');
    }


    /**
     * 师傅等级关联 - 修复类名拼写错误（TechnicianLevel -> TechnicianLevel）
     * @return HasOne
     */
    public function level()
    {
        return $this->hasOne(TechnicianLevel::class, 'level_id', 'level_id');
    }

    /**
     * 门店师傅分成比例关联
     * @return HasOne
     */
    public function storeTechnician()
    {
        return $this->hasOne(StoreTechnician::class, 'technician_id', 'id');
    }

    /**
     * 订单
     * @return HasOne
     */
    public function order()
    {
        return $this->hasOne(Order::class, 'technician_id', 'id');
    }

    /**
     * 订单
     * @return hasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'technician_id', 'id');
    }

    /**
     * 通过分类ID获取数量
     * @param $category_id
     * @return int
     * @throws \think\db\exception\DbException
     */
    public function getCountByCategoryID($category_id)
    {
        /**获取商品表中未删除且使用该分类的数据条数**/
        return $this->whereRaw("FIND_IN_SET(?, category_id)", [$category_id])->count();
    }
}
