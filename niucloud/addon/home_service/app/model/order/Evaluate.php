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

namespace addon\home_service\app\model\order;


use addon\home_service\app\dict\order\EvaluateDict;
use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\technician\Technician;
use app\model\member\Member;
use addon\home_service\app\model\order\Order;
use app\dict\sys\FileDict;
use core\base\BaseModel;
use think\db\Query;

/**
 * 商品评价模型
 * Class Evaluate
 * @package addon\home_service\app\model\goods
 */
class Evaluate extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'evaluate_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_goods_evaluate';

    // 设置json类型字段
    protected $json = ['images'];

    // 设置JSON数据返回数组
    protected $jsonAssoc = true;


    /**
     * 评分搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchScoresAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("scores", "in", $value);
        }
    }

    public function searchGoodsIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("goods_id", "=", $value);
        }
    }


    public function searchTechnicianIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("technician_id", "=", $value);
        }
    }

    public function searchStoreIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("evaluate.store_id", "=", $value);
        }
    }


    public function searchIsAuditAttr($query, $value, $data)
    {
        if ($value != '') {
            if (!empty($data['technician_id']) && $value == EvaluateDict::AUDIT_ADOPT){
                $query->where("is_audit", "in", [EvaluateDict::AUDIT_NO,EvaluateDict::AUDIT_ADOPT]);
            }else{
                $query->where("is_audit", "=", $value);
            }
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
            $query->whereBetweenTime('evaluate.create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['evaluate.create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['evaluate.create_time', '<=', $end_time]]);
        }
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
            $query->whereBetweenTime('create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['create_time', '<=', $end_time]]);
        }
    }


    /**
     * 审核状态转换
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getAuditNameAttr($value, $data)
    {
        return EvaluateDict::getStatus()[$data['is_audit']] ?? '';
    }

    /**
     * 缩略图生成-小图
     * @param $value
     * @param $data
     * @return array|mixed
     * @throws \Exception
     */
    public function getImageSmallAttr($value, $data)
    {
        if (!empty($data['images'])) {
            $small_arr = [];
            foreach ($data['images'] as $k => $v) {
                $small_arr[] = get_thumb_images($data['site_id'], $v, FileDict::SMALL);
            }
            return $small_arr;
        }
        return [];
    }

    /**
     * 缩略图生成-大图
     * @param $value
     * @param $data
     * @return array|mixed
     * @throws \Exception
     */
    public function getImageBigAttr($value, $data)
    {
        if (!empty($data['images'])) {
            $small_arr = [];
            foreach ($data['images'] as $k => $v) {
                $small_arr[] = get_thumb_images($data['site_id'], $v, FileDict::BIG);
            }
            return $small_arr;
        }
        return [];
    }

    /**
     * 缩略图生成-中图
     * @param $value
     * @param $data
     * @return array|mixed
     * @throws \Exception
     */
    public function getImageMidAttr($value, $data)
    {
        if (!empty($data['images'])) {
            $small_arr = [];
            foreach ($data['images'] as $k => $v) {
                $small_arr[] = get_thumb_images($data['site_id'], $v, FileDict::MID);
            }
            return $small_arr;
        }
        return [];
    }


    public function getMemberNameAttr($value, $data)
    {
        if (isset($data['is_anonymous']) && $data['is_anonymous'] == 1) {
            return '匿名买家';
        }
        return $value;
    }

    public function getAnonymousNameAttr($value, $data)
    {
        if ($data['is_anonymous'] == 1) return '已匿名';
        if ($data['is_anonymous'] == 2) return '未匿名';
    }


    /**
     * 关联商品表
     */
    public function goods()
    {
        return $this->hasOne(Goods::class, 'goods_id', 'goods_id')->withField('site_id, goods_id, goods_name, goods_cover')
            ->append(['goods_cover_thumb_small', 'goods_cover_thumb_mid']);
    }

    public function orderItem()
    {
        return $this->hasOne(OrderItem::class, 'order_goods_id', 'order_goods_id');
    }


    public function order()
    {
        return $this->hasOne(Order::class, 'order_id', 'order_id');
    }


    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id');
    }


    /**
     * 师傅
     * @return \think\model\relation\HasMany
     */
    public function technician()
    {
        return $this->hasOne(Technician::class, 'id', 'technician_id');
    }

    public function store()
    {
        return $this->hasOne(Store::class, 'store_id', 'store_id');
    }


}
