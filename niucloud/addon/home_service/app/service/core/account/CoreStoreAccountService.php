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

namespace addon\home_service\app\service\core\account;

use addon\home_service\app\dict\notice\NoticeDict;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\account\StoreAccount;


/**
 * 师傅账户
 */
class CoreStoreAccountService extends BaseCoreService
{


    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreAccount();
    }


    /**
     * 订单佣金发放
     */
    public function orderCommissGrant(&$order, $status = 0, $is_refund = false)
    {
        $account_data = bcadd($order['store_commission'], $order['store_additional_commission'], 2);
        if ($account_data > 0) {
            $prefix = $is_refund ? '退款' : '完成';
            $no = $order['order_no'];
            $from_type = $is_refund ? AccountDict::ORDER_REFUND_COMMISSION : AccountDict::ORDER_COMMISSION;
            $memo = $order['order_name'] . "-{$prefix}订单分成奖励";
            return $this->addLog($order['site_id'], $order['store_id'], AccountDict::COMMISSION, $account_data, $from_type, $memo, $no, $status, $order['category_id']);
        }
    }

    /**
     * 订单佣金发放   释放
     */
    public function orderCommissRelease($order_data, $is_refund = false)
    {
        $store_id = $order_data['store_id'] ?? 0;
        if (empty($store_id)) return true;
        $account_type = AccountDict::COMMISSION;
        $store_model = new Store();
        $store_info = $store_model->where([
            ['store_id', '=', $store_id]
        ])->field($account_type)->lock(true)->find();
        if (empty($store_info)) throw new CommonException('HOME_SERVICE_STORE_NOT_EXIST');
        $related_id = $order_data['order_no'];
        $from_type = $is_refund ? AccountDict::ORDER_REFUND_COMMISSION : AccountDict::ORDER_COMMISSION;
        $account = $this->model->where([
            ['status', '=', 0],
            ['from_type', '=', $from_type],
            ['related_id', '=', $related_id]
        ])->field('status,account_data')->lock(true)->find();

        //is_settlement
        if (!empty($account)) {
            $account->account_sum = bcadd($store_info[$account_type], $account->account_data, 2);
            $account->payment_time = time();
            $account->status = 1;
            $account->save();
            (new Store())->where([['store_id', '=', $store_id]])
                ->inc($account_type . "_get", $account->account_data)
                ->inc($account_type, $account->account_data)
                ->update();
        }
        return true;
    }

    /**
     * 门店账单统一入口
     */
    public function addLog(int $site_id, int $store_id, string $account_type, $account_data, string $from_type, string $memo, $related_id = 0, $status = 0, $category_id = 0)
    {
        $store_model = new Store();
        //账户检测
        $store_info = $store_model->where([
            ['store_id', '=', $store_id]
        ])->field($account_type)->lock(true)->find();
        if (empty($store_info)) throw new CommonException('HOME_SERVICE_STORE_NOT_EXIST');
        if ($status == 1) {
            $account_new_data = round((float)$store_info[$account_type] + (float)$account_data, 2);
            if ($account_new_data < 0) {
                throw new CommonException('ACCOUNT_INSUFFICIENT');
            }
        }
        $data = array(
            'site_id' => $site_id,
            'store_id' => $store_id,
            'account_type' => $account_type,
            'account_data' => $account_data,
            "account_sum" => $account_new_data ?? 0,
            'from_type' => $from_type,
            'create_time' => time(),
            'memo' => $memo,
            'related_id' => $related_id,
            'status' => $status,
            'category_id' => $category_id,
        );

        Db::startTrans();
        try {
            $res = $this->model->create($data);
            if ($status == 1) {
                if ($account_data > 0) {
                    $account_type_get = $store_info[$account_type . "_get"] + $account_data;
                } else {
                    $account_type_get = $store_info[$account_type . "_get"];
                }
                $store_model->update([
                    $account_type => $account_new_data,
                    $account_type . "_get" => $account_type_get,
                ], [
                    'store_id' => $store_id
                ]);
            }
            Db::commit();
            return $res->id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 账户流水列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'payment_time,create_time,store_id,account_type,account_data,account_sum,from_type,memo,related_id,status';
        $left_where = [];
        if (isset($where['site_id']) && !empty($where['site_id'])) $left_where[] = ['store_account.site_id', '=', $where['site_id']];
        if (isset($where['store_id']) && !empty($where['store_id'])) $left_where[] = ['store_account.store_id', '=', $where['store_id']];
        $search_model = $this->model
            ->withSearch(['join_create_time'], $where)
            ->field($field)
            ->where($left_where)
            ->order('create_time desc')
            ->withJoin(
                [
                    'store' => ['store_id', 'store_name']
                ]
            )
            ->append(['from_type_name', 'status_name']);
        $res = $this->pageQuery($search_model);
        return $res;
    }


}
