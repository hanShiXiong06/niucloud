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

use addon\home_service\app\dict\technician\TechnicianDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\store\StoreTechnician;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\service\core\statistics\CoreEvaluateService;
use addon\home_service\app\service\core\statistics\CoreOrderService;
use addon\home_service\app\service\core\store\CoreTechnicianService;
use core\base\BaseApiService;
use core\exception\ApiException;
use core\exception\CommonException;

/**
 * 师傅服务层
 * Class TechnicianService
 * @package app\service\api\store
 */
class TechnicianService extends BaseApiService
{
    use StoreTrait;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Technician();
        $this->checkStore();
    }


    /**
     * 获取师傅列表  ok
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $store_technician_model = (new StoreTechnician());
        $search_model = $this->model->field('id as technician_id,site_id,order_num,real_name,mobile,status,member_id,headimg,certificate,full_address,category_id,create_time,store_id')
            ->where([
                ['technician.site_id', '=', $this->site_id],
                ['technician.store_id', '=', $this->store_id],
            ])->withSearch(['real_name', 'category_id', 'status'], $where)->withJoin([
                'member' => ['member_id', 'username', 'mobile', 'nickname', 'headimg'],
            ])->append(['headimg_mid', 'status_name', 'category_name'])->order('create_time desc');
        $list = $this->pageQuery($search_model, function ($item) use ($store_technician_model) {
            $store_technician_info = $store_technician_model->field('order_num,evaluate_avg_scores,order_rate')->where([
                ['site_id', '=', $this->site_id],
                ['store_id', '=', $this->store_id],
                ['technician_id', '=', $item['technician_id']],
            ])->find();
            $item['order_num'] = $store_technician_info['order_num'] ?? 0;
            $item['order_rate'] = $store_technician_info['order_rate'] ?? 0;
            $item['evaluate_avg_scores'] = $store_technician_info['evaluate_avg_scores'] ?? 0;
        });


        return $list;
    }

    /**
     * 获取师傅信息
     * @param int $technician_id
     * @return array
     */
    public function getInfo(int $technician_id)
    {
        $field = 'id,real_name,mobile,status,member_id,headimg,certificate,full_address,store_id,category_id,level_id,site_id,create_time,certificate,intro';
        $info = $this->model->where([['id', '=', $technician_id], ['site_id', '=', $this->site_id]])
            ->with(
                [
                    'member' => function ($query) {
                        $query->field('member_id,mobile,nickname, headimg');
                    },
                ]
            )->field($field)->append(['headimg_mid', 'status_name', 'category_name'])
            ->findOrEmpty()->toArray();
        $store_technician = (new StoreTechnician())->where([
            ['site_id', '=', $this->site_id],
            ['technician_id', '=', $technician_id],
            ['store_id', '=', $this->store_id],
            ['status', '=', 1],
        ])->findOrEmpty();

        $order_stat = (new Order())->field([
            "SUM(CASE WHEN is_abnormal = 1 THEN 1 ELSE 0 END) AS abnormal_count",
            "SUM(CASE WHEN refund_status != '' THEN 1 ELSE 0 END) AS refund_count"
        ])->where([
            ['site_id', '=', $this->site_id],
            ['technician_id', '=', $technician_id],
            ['store_id', '=', $this->store_id]
        ])->find();

        $info['certificate'] = !empty($info['certificate']) ? explode(',', $info['certificate']) : [];
        $info['order_count'] = $store_technician['order_num'] ?? 0;
        $info['abnormal_count'] = $order_stat['abnormal_count'] ?? 0;
        $info['refund_count'] = $order_stat['refund_count'] ?? 0;
        $info['positive_rate'] = isset($store_technician['positive_rate']) ? round($store_technician['positive_rate'], 1) . '%' : '0%';

        return $info;
    }

    /**
     * 设置门店师傅分成比例
     * @param array $data
     * @return bool
     */
    public function setTechnicianRate(array $data)
    {
        if (empty($data['technician_id'])) throw new ApiException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');

        $data['distribute_type'] = TechnicianDict::CUSTOMIZE;
        $data['site_id'] = $this->site_id;
        $data['store_id'] = $this->store_id;
        $data['edit_order_rate'] = true;
        (new CoreTechnicianService())->storeTechnicianRate($data);
        return true;
    }

}
