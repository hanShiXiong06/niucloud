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
use addon\home_service\app\model\account\StoreAccount;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 门店结算服务层
 */
class StoreSettlementService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreAccount();
    }

    /**
     * 门店结算列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $store_where = [];
        if (!empty($where['store_name'])) {
            $store_where[] = ['store_name', 'like', '%' . $where['store_name'] . '%'];
        }
        $search_model = $this->model->field([
            'store_id',
            "DATE_FORMAT(FROM_UNIXTIME(payment_time), '%Y-%m')" => 'month',
            'SUM(account_data)' => 'total_amount',
            'COUNT(*)' => 'total_count',
        ])->withJoin([
            'store'=>function($query) use ($store_where){
                $query->field('store_name')->where($store_where);
            }
        ])->hidden(['store'])->where([
            ['store_account.site_id', '=', $this->site_id],
            ['status', '=', 1],
            ['from_type', 'in',[AccountDict::ORDER_COMMISSION,AccountDict::ORDER_REFUND_COMMISSION]]
        ])
            ->group("store_account.store_id, DATE_FORMAT(FROM_UNIXTIME(payment_time), '%Y-%m')")
            ->order('month DESC');

        return $this->pageQuery($search_model);
    }

    /**
     * 门店结算账单列表
     * @param array $where
     * @return array
     */
    public function getAccountPage($where)
    {
        if (empty($where['store_id']) && empty($where['month'])) throw new AdminException('GET_ACCOUNT_LIST_PARAM_ERROR');

        $search_model = $this->model
            ->field('id,store_id,account_data,account_sum,related_id as order_no,payment_time,status,from_type')
            ->withSearch(['order_no'],$where)
            ->where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
                ['store_id', '=', $where['store_id']],
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
     * 门店结算统计
     * @param array $where
     * @return array
     */
    public function getSettlementStat($where)
    {
        $store_where = [];
        if (!empty($where['store_name'])) {
            $store_where[] = ['store_name', 'like', '%' . $where['store_name'] . '%'];
        }

        $search_model = $this->model
            ->field([
                'store_id',
                "COUNT(CASE WHEN status = 1 THEN 1 END)" => 'settled_count',
                'SUM(account_data)' => 'total_amount',
                "SUM(CASE WHEN status = 1 THEN account_data ELSE 0 END)" => 'settled_amount',
            ])->withJoin([
                'store'=>function($query) use ($store_where){
                    $query->field('store_name')->where($store_where);
                }
            ])->hidden(['store'])->where([
                ['store_account.site_id', '=', $this->site_id],
                ['from_type', 'in',[AccountDict::ORDER_COMMISSION,AccountDict::ORDER_REFUND_COMMISSION]]
            ])->group('store_account.store_id');
        return $this->pageQuery($search_model);
    }



}
