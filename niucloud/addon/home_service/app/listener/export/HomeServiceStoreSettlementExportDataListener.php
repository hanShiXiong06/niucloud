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

namespace addon\home_service\app\listener\export;



use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\model\account\StoreAccount;

/**
 * 门店结算导出数据源查询
 * Class HomeServiceStoreSettlementExportDataListener
 * @package addon\home_service\app\listener\export
 */
class HomeServiceStoreSettlementExportDataListener
{

    public function handle($param)
    {
        $data = [];
        if ($param['type'] == 'home_service_store_settlement') {
            $store_where = [];
            if (!empty($where['store_name'])) {
                $store_where[] = ['store_name', 'like', '%' . $where['store_name'] . '%'];
            }

            $search_model = (new StoreAccount())->field([
                'store_id',
                "DATE_FORMAT(FROM_UNIXTIME(payment_time), '%Y-%m')" => 'month',
                'SUM(account_data)' => 'total_amount',
                'COUNT(*)' => 'total_count',
            ])->withJoin([
                'store'=>function($query) use ($store_where){
                    $query->field('store_name')->where($store_where);
                }
            ])->hidden(['store'])->where([
                ['store_account.site_id', '=', $param['site_id']],
                ['status', '=', 1],
                ['from_type', 'in',[AccountDict::ORDER_COMMISSION,AccountDict::ORDER_REFUND_COMMISSION]]
            ])
                ->group("store_account.store_id, DATE_FORMAT(FROM_UNIXTIME(payment_time), '%Y-%m')")
                ->order('month DESC');
            if ($param['page']['page'] > 0 && $param['page']['limit'] > 0) {
                $data = $search_model->page($param['page']['page'], $param['page']['limit'])->select()->toArray();
            } else {
                $data = $search_model->select()->toArray();
            }

            foreach ($data as $key => $val) {
                $data[$key]['store_name'] = !empty($val['store_name']) ? $val['store_name'] : '';
                $data[$key]['month'] = !empty($val['month']) ? $val['month'] : '';
                $data[$key]['total_count'] = $val['total_count']."\t";
                $data[$key]['total_amount'] = $val['total_amount']."\t";
            }
        }
        return $data;
    }
}
