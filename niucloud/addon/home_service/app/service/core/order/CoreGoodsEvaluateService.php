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

namespace addon\home_service\app\service\core\order;


use addon\home_service\app\dict\order\EvaluateDict;
use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\model\order\Evaluate;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\technician\Technician;

use addon\home_service\app\service\core\store\CoreTechnicianService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 商品评价服务层
 */
class CoreGoodsEvaluateService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Evaluate();
    }

    /**
     * 添加评价
     * @param $data
     */
    public function addEvaluate($data)
    {
        $data = [
            'site_id' => $data['site_id'],
            'order_id' => $data[ 'order_id' ] ?? 0,
            'order_goods_id' => $data[ 'order_goods_id' ] ?? 0,
            'store_id' => $data[ 'store_id' ] ?? 0,
            'technician_id' => $data[ 'technician_id' ] ?? 0,
            'goods_id' => $data[ 'goods_id' ],
            'member_id' => $data[ 'member_id' ] ?? 0,
            'member_head' => $data[ 'member_head' ],
            'member_name' => $data[ 'member_name' ],
            'content' => $data[ 'content' ],
            'images' => $data[ 'images' ],
            'is_anonymous' => $data[ 'is_anonymous' ],
            'scores' => $data[ 'scores' ],
            'is_audit' => $data[ 'is_audit' ] ?? 1,
            'create_time' => isset($data[ 'create_time' ]) ? strtotime($data[ 'create_time' ]) : time(),
            'update_time' => 0
        ];
        $res = $this->model->create($data);
        $this->addStat($data);

        return $res->evaluate_id;
    }

    /**
     * 评价自动通过审核
     * @param $evaluate_id
     */
    public function autoAdoptExamine($evaluate_id)
    {
        $evaluate = $this->model->where([['evaluate_id', '=', $evaluate_id]])->findOrEmpty();
        if ($evaluate->isEmpty()) return true;
        if ($evaluate->is_audit != EvaluateDict::AUDIT) return true;

        $evaluate->is_audit = EvaluateDict::AUDIT_ADOPT;
        $evaluate->save();

        return true;
    }

    /**
     * 增加统计数据
     * @param $data
     */
    public function addStat($data)
    {
        if($data[ 'order_id' ] > 0) (new Order())->where([['order_id', '=', $data[ 'order_id' ]]])->update(['is_evaluate' => 1]);
        // 无需审核的增加评论统计数

        Db::startTrans();
        try {
            if ($data['is_audit'] == EvaluateDict::AUDIT_NO) {
                (new Goods())->where([['goods_id', '=', $data['goods_id']]])->inc('evaluate_num', 1) ->update();

                if (!empty($data['technician_id'])){
                    //师傅表算平均分
                    $technician_scores  = (new Evaluate())
                        ->where([
                            ['site_id', '=', $data['site_id']],
                            ['technician_id', '=', $data['technician_id']]
                        ])
                        ->column('scores');
                    // 平均分
                    $avg_scores = round(array_sum($technician_scores) / count($technician_scores), 1);
                    // 好评率
                    $positive_num = 0;
                    foreach ($technician_scores as $value){
                        if ($value > 3) $positive_num += 1;
                    }
                    $technician_positive_rate = number_format(($positive_num / count($technician_scores)) * 100, 1);
                    (new Technician())->where([['site_id', '=',$data['site_id']], ['id', '=', $data['technician_id']]])->update(['evaluate_avg_scores' => $avg_scores, 'positive_rate' => $technician_positive_rate]);
                }

                if (!empty($data['store_id'])){
                    //门店表算平均分
                    $store_total_avg_scores = (new Evaluate())
                        ->where([
                            ['site_id', '=', $data['site_id']],
                            ['store_id', '=', $data['store_id']]
                        ])
                        ->avg('scores');
                    $store_total_avg_scores = round($store_total_avg_scores, 1);
                    (new Store())->where([['site_id', '=',$data['site_id']], ['store_id', '=', $data['store_id']]])->update(['evaluate_avg_scores' => $store_total_avg_scores]);

                    $store_scores = (new Evaluate())->where([['site_id', '=',$data['site_id']], ['store_id', '=', $data['store_id']], ['technician_id', '=', $data['technician_id']]])->column('scores');
                    //平均分
                    $avg_scores = round(array_sum($store_scores) / count($store_scores), 1);

                    //好评率
                    $positive_num = 0;
                    foreach ($store_scores as $value){
                        if ($value > 3) $positive_num += 1;
                    }
                    $positive_rate = number_format(($positive_num / count($store_scores))  * 100, 1) ;

                    $stat_data = [
                        'evaluate_avg_scores' => $avg_scores,
                        'positive_rate' => $positive_rate,
                    ];
                    CoreTechnicianService::addStat($data['site_id'],$data['store_id'],$data['technician_id'],$stat_data);
                }
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage() . $e->getfile() . $e->getline());
        }





    }

}
