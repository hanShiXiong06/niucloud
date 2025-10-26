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

namespace addon\home_service\app\service\admin\store;


use addon\home_service\app\dict\technician\TechnicianDict;
use addon\home_service\app\service\core\store\CoreTechnicianService;
use core\base\BaseAdminService;
use addon\home_service\app\model\store\StoreTechnician;
use core\exception\AdminException;


/**
 * 门店师傅
 * Class TechnicianService
 * @package app\service\admin\technician
 */
class TechnicianService extends BaseAdminService
{


    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreTechnician();
    }


    /**
     * 门店师傅分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,technician_id,store_id,order_rate,create_time,update_time,status';
        $order = 'create_time desc';
        $with_where = [];
        if (isset($where['status']) && !empty($where['status'])) $with_where[] = ['store_technician.status', '=', $where['status']];
        if (isset($where['store_id']) && !empty($where['store_id'])) $with_where[] = ['store_technician.store_id', '=', $where['store_id']];
        if (isset($where['real_name']) && !empty($where['real_name'])) $with_where[] = ['technician.real_name', 'like', "%" . $where['real_name'] . "%"];
        $search_model = $this->model
            ->where([['store_technician.site_id', '=', $this->site_id]])
            ->field($field)
            ->withJoin([
                'technician' => ['real_name', 'headimg', 'mobile', 'id', 'order_num', 'achievement', 'evaluate_avg_scores', 'status'],
            ])
            ->where($with_where)
            ->order($order)->append([]);
        $list = $this->pageQuery($search_model, function ($item, $key) {
            $technician = $item['technician'];
            if (!empty($technician)) {
                $technician->append(['status_name']);
            }
        });
        return $list;
    }


    /**
     * 设置门店师傅分成比例
     * @param array $data
     * @return bool
     */
    public function setTechnicianRate(array $data)
    {
        if (empty($data['store_id'])) throw new AdminException('HOME_SERVICE_STORE_NOT_EXIST');
        if (empty($data['technician_id'])) throw new AdminException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');

        $data['distribute_type'] = TechnicianDict::CUSTOMIZE;
        $data['site_id'] = $this->site_id;
        $data['edit_order_rate'] = true;
        (new CoreTechnicianService())->storeTechnicianRate($data);
        return true;
    }


    /**
     * 查询数量
     * @param array $data
     * @return bool
     */
    public function getCount($where)
    {
        $count = $this->model->withSearch(["store_id"], $where)->where([['site_id', '=', $this->site_id], ['status', '=', 1]])->count();
        return $count;
    }


}
