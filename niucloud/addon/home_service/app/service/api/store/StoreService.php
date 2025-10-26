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

namespace addon\home_service\app\service\api\store;

use addon\home_service\app\model\store\Store;
use core\base\BaseApiService;
use addon\home_service\app\service\core\store\CoreStoreService;
use core\exception\ApiException;

/**
 * 门店服务层
 * Class TechnicianService
 * @package app\service\api\technician
 */
class StoreService extends BaseApiService
{

    use StoreTrait;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Store();
        $this->checkStore();
    }


    /**
     * 获取门店详情
     * @param array $where
     * @return array
     */
    public function getStoreInfo()
    {
        return (new CoreStoreService)->getInfo($this->store_info['store_id']);
    }

    /**
     * 编辑门店联系人信息
     * @param array $data
     * @return bool
     */
    public function editContact($data)
    {
        $store_id = $this->store_id;
        return (new CoreStoreService)->editContact($store_id,$data);
    }

    /**
     * 获取门店列表
     * @param array $where
     * @return array
     */
    public function getList(array $where = [])
    {
        $where['member_id'] = $this->member_id;
        $where['site_id'] = $this->site_id;
        return (new CoreStoreService)->getStoreList($where);
    }

    /**
     * 切换门店
     * @param int $store_id
     * @return bool
     */
    public function storeSwitch($store_id)
    {
        $store_info = $this->model->where([['site_id', '=', $this->site_id], ['store_id', '=', $store_id], ['member_id', '=', $this->member_id]])->findOrEmpty();
        if (empty($store_id) || $store_info->isEmpty()) throw new ApiException('HOME_SERVICE_STORE_NOT_EXIST');
        $current_store_id = $this->store_id;
        return (new CoreStoreService)->storeSwitch($current_store_id,$store_id,$this->site_id,$this->member_id);
    }




}
