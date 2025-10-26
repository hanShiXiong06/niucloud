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

namespace addon\home_service\app\service\admin\settlement;

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\model\account\TechnicianAccount;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 师傅结算服务层
 */
class TechnicianSettlementService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new TechnicianAccount();
    }

    /**
     * 列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $technician_where = [];
        if (!empty($where['technician_name'])) {
            $technician_where[] = ['real_name', 'like', '%' . $where['technician_name'] . '%'];
        }

        $search_model = $this->model->field([
            'technician_id',
            "DATE_FORMAT(FROM_UNIXTIME(payment_time), '%Y-%m')" => 'month',
            'SUM(account_data)' => 'total_amount',
            'COUNT(*)' => 'total_count',
        ])->withJoin([
            'technician'=>function($query) use ($technician_where){
                $query->field('real_name')->where($technician_where);
            }
        ])->hidden(['technician'])->where([
            ['technician_account.site_id', '=', $this->site_id],
            ['technician_account.status', '=', 1],
            ['from_type', 'in',[AccountDict::ORDER_COMMISSION,AccountDict::ORDER_REFUND_COMMISSION]]
        ])
            ->group("technician_account.technician_id, DATE_FORMAT(FROM_UNIXTIME(payment_time), '%Y-%m')")
            ->order('month DESC');

        return $this->pageQuery($search_model);
    }

    /**
     * 师傅结算账单列表
     * @param array $where
     * @return array
     */
    public function getAccountPage($where)
    {
        if (empty($where['technician_id']) && empty($where['month'])) throw new AdminException('GET_ACCOUNT_LIST_PARAM_ERROR');

        $search_model = $this->model
            ->field('id,technician_id,account_data,account_sum,related_id as order_no,payment_time,status,from_type')
            ->withSearch(['order_no'],$where)
            ->where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
                ['technician_id', '=', $where['technician_id']],
                ['from_type', 'in', [AccountDict::ORDER_COMMISSION,AccountDict::ORDER_REFUND_COMMISSION]],
            ])
            ->whereRaw("DATE_FORMAT(FROM_UNIXTIME(payment_time), '%Y-%m') = :month", ['month' => $where['month']])
            ->append(['status_name','from_type_name'])
            ->order('payment_time DESC');
        return $this->pageQuery($search_model,function($item){
            $item['payment_time'] = date('Y-m-d H:i:s',$item['pay_time']);
        });
    }

    /**
     * 师傅结算统计
     * @param array $where
     * @return array
     */
    public function getSettlementStat($where)
    {
        $technician_where = [];
        if (!empty($where['technicianName'])) {
            $technician_where[] = ['real_name', 'like', '%' . $where['technicianName'] . '%'];
        }
        if (!empty($where['create_time'])){
            $where['join_create_time'] = $where['create_time'];
        }
        $search_model = $this->model
            ->field([
                'technician_id',
                "COUNT(CASE WHEN technician_account.status = 1 THEN 1 END)" => 'settled_count',
                'SUM(account_data)' => 'total_amount',
                "SUM(CASE WHEN technician_account.status = 1 THEN account_data ELSE 0 END)" => 'settled_amount',
            ])->withSearch(['join_create_time'], $where)
            ->withJoin([
                'technician'=>function($query) use ($technician_where){
                    $query->field('real_name')->where($technician_where);
                }
            ])->hidden(['technician'])->where([
                ['technician_account.site_id', '=', $this->site_id],
                ['from_type', 'in',[AccountDict::ORDER_COMMISSION,AccountDict::ORDER_REFUND_COMMISSION]]
            ])->group('technician_account.technician_id');
        return $this->pageQuery($search_model);
    }

}
