<?php

namespace addon\home_service\app\service\api\member;


use addon\home_service\app\dict\store\StoreDict;
use addon\home_service\app\model\store\StoreApplication;
use addon\home_service\app\service\core\store\CoreStoreApplicationService;
use core\base\BaseApiService;
use core\exception\ApiException;


/**
 * 门店统计业务
 */
class StoreApplicationService extends BaseApiService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreApplication();
    }

    /**
     * 获取用户门店申请信息
     * @return array
     */
    public function getStoreApplicationInfo()
    {
        return $this->model->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['source', '=', 'member'],
        ])->findOrEmpty()->toArray();
    }

    /**
     * 门店申请
     * @param $data
     * @return array
     */
    public function StoreApply($data)
    {
        $storeApplicationInfo = $this->getStoreApplicationInfo();
        if (!empty($storeApplicationInfo)) throw new ApiException('HOME_SERVICE_NOT_REPEAT_APPLY');
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $data['source'] = 'member';
        $data['audit_status'] = StoreDict::PENDING_EXAMINE;
        return (new CoreStoreApplicationService())->apply($data);
    }

    /**
     * 门店申请
     * @param $id
     * @param $data
     * @return array
     */
    public function StoreApplyEdit($id, $data)
    {
        $data['site_id'] = $this->site_id;
        return (new CoreStoreApplicationService())->update($id, $data);
    }
}
