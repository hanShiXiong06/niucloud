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

namespace addon\recycle\app\service\api\recycle_category;

use addon\recycle\app\model\recycle_category\RecycleCategory;
use addon\recycle\app\service\core\RecycleCategory\CoreRecycleCategoryService;
use addon\recycle\app\model\ShopAddress;
use addon\recycle\app\model\recycle_category\RecycleCategoryConfig;
use core\base\BaseApiService;
use app\model\member\Member;

/**
 * 二手机分类服务层
 * Class RecycleCategoryService
 * @package addon\recycle\app\service\admin\recycle_category
 */
class RecycleCategoryService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleCategory();
        $this->Address = new ShopAddress();
    }

    /**
     * 查询商品分类树结构
     * @return array
     */
    public function getTree()
    {
        // 查询用户的身份
        $getMemberInfo = $this->getMemberInfo();
        $is_use = !empty($getMemberInfo['memberLevelData']['level_benefits']['hsx_quote']['is_use']) ? 1 : 0;
        
        if($this->site_id !== 0) {
            $config = (new RecycleCategoryConfig())->where([
                ['site_id', '=', $this->site_id]
            ])->findOrEmpty()->toArray();
        }
        
        $site_id = empty($config) || empty($config['is_enable']) ? $this->site_id : $this->site_id.",0";
        return (new CoreRecycleCategoryService())->getTree([['site_id', 'in', "{$site_id}"], ['is_show', '=', 1]], $is_use);
    }

    public function hot($where = []){
        $field = 'category_id,site_id,category_name,image,level,pid,category_full_name,is_show,sort,create_time,update_time,images, need_vip';
        $order = 'sort desc';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] , ['need_vip' , '=', 1] , ['is_show' , '=', 1] ])->withSearch(["category_name","level","pid","category_full_name","is_show","sort","create_time","update_time"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }
    /**
     * 获取商家的收货地址列表
     * @return array
     */
    public function address_list()
    {
        $field = 'id,contact_name,mobile,province_id,city_id,district_id,address,full_address,lat,lng,is_delivery_address,is_refund_address,is_default_delivery,is_default_refund';
        $order = '';
        
        $search_model = $this->Address->where([['site_id', '=', $this->site_id]])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取会员信息
     * @return array
     */
    public function getMemberInfo()
    {
        $member_model = new Member();
        $member_field = 'member_level';
        $member_info = $member_model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->field($member_field)
            ->with([
                // 会员等级
                'memberLevelData' => function($query) {
                    $query->field('level_id, site_id, level_name, status, level_benefits, level_gifts');
                },
            ])
            ->findOrEmpty()->toArray();

        return $member_info;
    }
}
