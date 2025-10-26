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

namespace addon\home_service\app\model\store;

use addon\home_service\app\model\traits\nearby\scopeNearbyTrait;
use app\dict\sys\FileDict;
use app\model\member\Member;
use core\base\BaseModel;
use think\db\Query;
use think\model\relation\HasOne;

/**
 * 门店模型
 * Class Technician
 * @package app\model\o2o_technician
 */
class Store extends BaseModel
{
    use scopeNearbyTrait;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'store_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_store';


    /**
     * 会员关联
     * @return HasOne
     */
    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id')->withField('member_id, member_no, username, mobile, nickname, headimg')->joinType('inner');
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
     * 店铺名称搜索器
     * @param $value
     */
    public function searchStoreNameAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('store_name', 'like', '%' . $value . '%');
        }
    }


    /**
     * 站点id搜索器
     * @param $value
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('site_id', '=', $value);
        }
    }


    /**
     * 会员id搜索器
     * @param $value
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('member_id', '=', $value);
        }
    }


    /**
     * 联系人名称搜索器
     * @param $value
     */
    public function searchContactNameAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('contact_name', 'like', '%' . $value . '%');
        }
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


}
