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

namespace addon\home_service\app\service\admin\order;

use addon\home_service\app\dict\order\EvaluateDict;
use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\model\order\Evaluate;
use addon\home_service\app\service\core\order\CoreGoodsEvaluateService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;


/**
 * 商品评价服务层
 */
class EvaluateService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Evaluate();
    }


    /**
     * 获取各状态订单的精确统计
     */
    /**
     * 获取各状态订单的精确统计
     */
    public function getTaskStatus($data = [])
    {
        try {
            // 获取所有可能的状态列表（格式：[状态值 => 状态名称]）
            $allStatus = EvaluateDict::getStatus();
            if (empty($allStatus)) {
                return [
                    'total' => 0,
                    'status_list' => []
                ];
            }
            $stats_where=[ ['site_id', '=', $this->site_id]];
            if(isset($data['store_id'])  &&  !empty($data['store_id']))     $stats_where[]=['store_id', '=', $data['store_id']];
            // 分组统计各状态数量
            $stats = $this->model->where($stats_where)
                ->group('is_audit') // 使用group方法
                ->field('is_audit, COUNT(*) as count')
                ->select();

            // 转换为数组并构建状态映射
            $statsArray = $stats ? $stats->toArray() : [];
            $statsMap = [];
            foreach ($statsArray as $item) {
                // 确保状态值为整数，与字典保持一致
                $status = (int)$item['is_audit'];
                $statsMap[$status] = (int)$item['count'];
            }
            // 计算总数量
            $total = array_sum($statsMap);

            // 构建结果列表（确保所有状态都被包含，即使数量为0）
            $statusList = [];
            foreach ($allStatus as $statusValue => $statusName) {
                $statusValue = (int)$statusValue; // 转换为整数，避免类型不一致
                $statusList[] = [
                    'is_audit' => $statusValue,
                    'name' => $statusName,
                    'count' => $statsMap[$statusValue] ?? 0
                ];
            }

            return [
                'total' => $total,
                'status_list' => $statusList
            ];
        } catch (\Exception $e) {
            // 返回错误信息或默认空数据
            return [
                'total' => 0,
                'status_list' => [],
                'error' => '获取统计数据失败'
            ];
        }
    }


    /**
     * 获取商品评价列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'evaluate_id,site_id,order_id,store_id,technician_id,goods_id,member_id,content,images,is_anonymous,scores,is_audit,explain_first,create_time';
        $order = 'create_time desc';
        $join_where = [];
        if (isset($where['member_search']) && $where['member_search'] != '') $join_where[] = ['member.member_no|member.nickname|member.username|member.mobile', 'like', "%" . $where['member_search'] . "%"];
        if (isset($where['technician_name']) && $where['technician_name'] != '') $join_where[] = ['technician.real_name', 'like', "%" . $where['technician_name'] . "%"];
        if (isset($where['store_name']) && $where['store_name'] != '') $join_where[] = ['store.store_name', 'like', "%" . $where['store_name'] . "%"];
        if (isset($where['technician_id']) && $where['technician_id'] != '') $join_where[] = ['evaluate.technician_id', '=', $where['technician_id']];
        if (isset($where['order_no']) && $where['order_no'] != '') $join_where[] = ['order.order_no', 'like', "%" . $where['order_no'] . "%"];
        $search_model = $this->model
            ->where([
                ['evaluate.site_id', '=', $this->site_id]]
            )
            ->where($join_where)
            ->withSearch(['is_audit', 'join_create_time', 'store_id'], $where)
            ->field($field)
            ->withJoin(
                [
                    'order' => ['order_no', 'order_name'],
                    'member' => ['nickname', 'member_id'],
                    'technician' => ['real_name'],
                    'store' => ['store_name', 'mobile', 'store_id'],
                ], 'left')
            ->order($order)->append(['audit_name','image_mid']);
        $list = $this->pageQuery($search_model, function ($item, $key) {
        });
        return $list;
    }

    /**
     * 获取评价信息
     * @param int $id
     * @return array
     */
    public function getDetail(int $order_id)
    {
        $field = 'evaluate_id,site_id,order_id,goods_id,member_id,content,images,is_anonymous,scores,is_audit,explain_first,create_time';
        $info = $this->model->field($field)->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 删除商品评价
     * @param int $id
     * @return bool
     */
    public function del(int $evaluate_id)
    {
        $model = $this->model->where([['evaluate_id', '=', $evaluate_id], ['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();

        (new Goods())->where([['goods_id', '=', $model->goods_id]])->dec('evaluate_num', 1)->update();
        return $res;
    }

    /**
     * 审核通过
     * @param $evaluate_id
     * @return bool
     */
    public function auditAdopt($evaluate_id)
    {
        $this->model->where([['evaluate_id', '=', $evaluate_id], ['site_id', '=', $this->site_id]])->update(['is_audit' => EvaluateDict::AUDIT_ADOPT]);
        $evaluate_info = $this->model->where([['evaluate_id', '=', $evaluate_id], ['site_id', '=', $this->site_id]])->findOrEmpty();
        if ($evaluate_info->isEmpty()) return false;
        (new CoreGoodsEvaluateService())->addStat($evaluate_info);
        return true;
    }

    /**
     * 审核拒绝
     * @param $evaluate_id
     * @return bool
     */
    public function auditRefuse($evaluate_id)
    {
        $this->model->where([['evaluate_id', '=', $evaluate_id], ['site_id', '=', $this->site_id]])->update(['is_audit' => EvaluateDict::AUDIT_REFUSE]);
        return true;
    }

    /**
     * 评价回复
     * @param $evaluate_id
     * @param $data
     * @return bool
     */
    public function reply($evaluate_id, $data)
    {
        $this->model->where([['evaluate_id', '=', $evaluate_id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 批量通过
     * @param $evaluate_ids
     * @return bool
     */
    public function batchAdopt($evaluate_ids)
    {
        $evaluate_list = $this->model->where([['site_id', '=', $this->site_id], ['evaluate_id', 'in', $evaluate_ids], ['is_audit', '=', EvaluateDict::AUDIT]])->column('evaluate_id, goods_id');
        $goods_ids = array_column($evaluate_list, 'goods_id');
        $evaluate_ids = array_column($evaluate_list, 'evaluate_id');
        Db::startTrans();
        try {
            if (!empty($evaluate_list)) {
                $this->model->where([['site_id', '=', $this->site_id], ['evaluate_id', 'in', $evaluate_ids]])->update(['is_audit' => EvaluateDict::AUDIT_ADOPT]);
                foreach ($goods_ids as $value) {
                    (new Goods())->where([['goods_id', '=', $value]])->inc('evaluate_num', 1)->update();
                }
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 批量拒绝
     * @param $evaluate_ids
     * @return bool
     */
    public function batchRefuse($evaluate_ids)
    {
        $evaluate_list = $this->model->where([['site_id', '=', $this->site_id], ['evaluate_id', 'in', $evaluate_ids], ['is_audit', '=', EvaluateDict::AUDIT]])->column('evaluate_id, is_audit');
        if (!empty($evaluate_list)) {
            $this->model->where([['site_id', '=', $this->site_id], ['evaluate_id', 'in', $evaluate_ids]])->update(['is_audit' => EvaluateDict::AUDIT_REFUSE]);
        }
        return true;
    }

    /**
     * 批量删除
     * @param $evaluate_ids
     * @return bool
     */
    public function batchDel($evaluate_ids)
    {
        $evaluate_list = $this->model->where([['site_id', '=', $this->site_id], ['evaluate_id', 'in', $evaluate_ids]])->column('is_audit, goods_id');

        Db::startTrans();
        try {
            $this->model->where([['site_id', '=', $this->site_id], ['evaluate_id', 'in', $evaluate_ids]])->delete();
            foreach ($evaluate_list as $value) {
                switch ($value['is_audit']) {
                    case EvaluateDict::AUDIT_ADOPT:
                        (new Goods())->where([['goods_id', '=', $value['goods_id']]])->dec('evaluate_num', 1)->update();
                        break;
                }
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }

    }

}
