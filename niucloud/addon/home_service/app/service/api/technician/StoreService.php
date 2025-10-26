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

namespace addon\home_service\app\service\api\technician;

use addon\home_service\app\model\store\Store;
use addon\home_service\app\service\core\order\SubStatusTrait;
use core\base\BaseApiService;
use addon\home_service\app\service\core\store\CoreStoreService;

/**
 * 门店服务层
 * Class TechnicianService
 * @package app\service\api\technician
 */
class StoreService extends BaseApiService
{

    use TechnicianTrait;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Store();
    }

    /**
     * 获取门店列表
     * @param array $where
     * @return array
     */
    public function getStoreList(array $where = [])
    {
        $where['site_id'] = $this->site_id;
        $field = 'is_default,member_id,store_id,store_name,mobile,headimg,contact_name,site_id,province_id,city_id,district_id,full_address,lng,lat,business_hours';
        $order = 'create_time desc';
        return $this->model
            ->withSearch(["store_name", "create_time", "site_id", "member_id"], $where)
            ->field($field)
            ->storeDistance($where['lat'] ?? 0, $where['lng'] ?? 0)
            ->order($order)
            ->append([])
            ->select()
            ->each(function($item) {
                // 直接在集合遍历中添加新字段
                $item->store_name_with_distance = (empty($item->store_name) ? '' : $item->store_name) . ' (' . $item->distance . 'km)';
            })
            ->toArray();
    }






    public function getMyStore()
    {
        $this->checkTechnician();
        if (!empty($this->technician_info['store_id'])) {
            return (new CoreStoreService)->getInfo($this->technician_info['store_id'] ?? 0);
        }
    }


}
