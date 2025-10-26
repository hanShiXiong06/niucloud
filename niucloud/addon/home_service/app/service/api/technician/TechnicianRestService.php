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

use addon\home_service\app\service\core\technician\CoreTechnicianRestService;
use core\base\BaseApiService;

/**
 * 师傅休息服务层
 * Class TechnicianRestService
 * @package app\service\api\technician
 */
class TechnicianRestService extends BaseApiService
{

    use TechnicianTrait;

    public function __construct()
    {
        parent::__construct();
        $this->checkTechnician();
    }


    /**
     * 获取师傅休息记录
     * @param array $where
     * @return array
     */
    public function getRestMonthstats(array $where)
    {
        $where['site_id'] = $this->site_id;
        $where['technician_id'] = $this->technician_id;
        return (new CoreTechnicianRestService())->getRestMonthstats($where);
    }

    /**
     * 设置师傅休息
     * @param array $data
     * @return bool
     */
    public function setTechnicianRest(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['technician_id'] = $this->technician_id;
        return (new CoreTechnicianRestService())->setTechnicianRest($data);
    }




    /**
     * 取消师傅休息
     * @param array $data
     * @return bool
     */
    public function cancelRest(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['technician_id'] = $this->technician_id;
        return (new CoreTechnicianRestService())->cancelRest($data);

    }


}
