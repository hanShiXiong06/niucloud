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

use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\service\api\member\TechnicianCollectService;
use core\base\BaseApiService;
use think\facade\Db;
use core\exception\CommonException;
use addon\home_service\app\service\api\order\EvaluateService;

/**
 * 师傅查询
 * Class TechnicianService
 * @package app\service\api\technician
 */
class TechnicianSearch extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Technician();
    }


    /**
     * 订单分页列表
     * @param array $where
     * @return mixed
     */
    public function getTechnicianSearchList(array $where)
    {

        $field = 'id,real_name,mobile,status,member_id,headimg,certificate,province_id,city_id,
        district_id,full_address,lng,lat,store_id,category_id,level_id,source,site_id,create_time,distribute_type,order_rate,order_num,achievement,evaluate_avg_scores';
        $order = 'create_time desc';
        $with_where = [];

        // 默认获取的数量，可通过where参数覆盖
        $limit = $where['limit'] ?? 10;

        $search_model = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(["real_name", "create_time", "store_id", "level_id", "source"], $where)
            ->field($field)
            ->nearby($where['lat'] ?? 0, $where['lng'] ?? 0, $where['distance']);

        // 添加状态过滤，如果有status参数
        if (isset($where['status']) && $where['status'] !== '') {
            $search_model->where('status', '=', $where['status']);
        }
        $list = $search_model->where($with_where)
            ->with([
                'level' => function ($query) {
                    $query->field('level_id,level_name');
                }

            ])
            ->order($order)
            ->append(['distribute_name', 'category_name'])
            ->limit($limit)
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            // 添加师傅评价信息
            if (isset($where['goods_id']) && !empty($where['goods_id'])) {
                $item['evaluate'] = (new EvaluateService())->getLatestByGoodsAndTechnician($where['goods_id'], $item['id']);
            }

            // 处理距离信息，如果有坐标参数
            if (isset($where['lat']) && isset($where['lng']) && $where['lat'] > 0 && $where['lng'] > 0 && !empty($item['distance'])) {
                $item['distance_text'] = $item['distance'] < 1 ? round($item['distance'] * 1000) . 'm' : round($item['distance'], 1) . 'km';
            }

            $item['is_collect_technician'] = (new TechnicianCollectService())->getTechnicianIsCollect($item['id']);
        }

        return $list;
    }
}
