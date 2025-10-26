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

namespace addon\home_service\app\service\core\technician;


use core\base\BaseCoreService;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\dict\technician\TechnicianDict;


/**
 * 师傅服务层
 */
class CoreTechnicianService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Technician();
    }


    /**
     * 获取师傅列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,real_name,mobile,status,member_id,headimg,certificate,province_id,city_id,
        district_id,full_address,lng,lat,store_id,category_id,level_id,source,site_id,create_time,distribute_type,order_rate';
        $order = 'technician.create_time desc';
        $with_where = [];
        if (isset($where['mobile']) && !empty($where['mobile'])) $with_where[] = ['technician.mobile', 'like', "%" . $where['mobile'] . "%"];
        if (isset($where['status']) && !empty($where['status'])) $with_where[] = ['technician.status', '=', $where['status']];
        if (isset($where['store_id']) && !empty($where['store_id'])) $with_where[] = ['technician.store_id', '=', $where['store_id']];
        if (isset($where['no_in_technician_ids']) && !empty($where['no_in_technician_ids'])) $with_where[] = ['technician.id', 'NOT IN', $where['no_in_technician_ids']];
        $search_model = $this->model->where([['technician.site_id', '=', $where['site_id'] ?? 0]])
            ->withSearch(["real_name", "create_time", "category_id"], $where)
            ->field($field)
            ->withJoin([
                'member' => ['member_id', 'username', 'mobile', 'nickname', 'headimg'],
                'level' => ['level_id', 'level_name', 'order_rate']
            ])
            ->where($with_where)
            ->with([
                'store' => function ($query) {
                    $query->field('store_id,contact_name,store_name, mobile');
                },
            ])
            ->order($order)->append(['headimg_mid', 'distribute_name', 'status_name', 'category_name']);
        $list = $this->pageQuery($search_model);
        return $list;
    }


    /**
     * 获取师傅比例
     * @param array $where
     * @return string
     */
    public function getTechnicianRate($site_id, $id, $store_id = 0)
    {
        $field = 'id,distribute_type,order_rate';
        $info = $this->model->where([['technician.site_id', '=', $site_id], ['id', '=', $id]])
            ->field($field)
            ->withJoin([
                'level' => ['level_id', 'order_rate']
            ])
            ->with([
                'storeTechnician' => function ($query) use ($store_id) {
                    $query->field('technician_id,order_rate,store_id')->where([['store_id', '=', $store_id]]);
                }
            ])
            ->findOrEmpty()->toArray();
        if (!empty($info['storeTechnician'])) {
            return $info['storeTechnician']['order_rate'];
        } elseif ($info['distribute_type'] == TechnicianDict::CUSTOMIZE) {
            return $info['order_rate'];
        } else {
            return $info['level']['order_rate'];
        }
    }


}
