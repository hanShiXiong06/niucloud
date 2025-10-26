<?php

namespace addon\home_service\app\service\core\account;

use addon\home_service\app\dict\notice\NoticeDict;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\model\account\TechnicianAccount;
use think\Model;


/**
 * 师傅站点业务
 */
class CoreTechnicianAccountService extends BaseCoreService
{


    public function __construct()
    {
        parent::__construct();
        $this->model = new TechnicianAccount();
    }


    /**
     * 订单佣金发放  临时
     */
    public function orderCommissGrant(&$order, $is_refund = false)
    {
        $account_data = bcadd($order['technician_commission'], $order['technician_additional_commission'], 2);
        if ($account_data > 0) {
            $prefix = $is_refund ? '退款' : '完成';
            $no = $order['order_no'];
            $from_type = $is_refund ? AccountDict::ORDER_REFUND_COMMISSION : AccountDict::ORDER_COMMISSION;
            $memo = $order['order_name'] . "-{$prefix}完成订单分成奖励";
            return $this->addLog($order['site_id'], $order['technician_id'], AccountDict::COMMISSION, $account_data, $from_type, $memo, $no, 0, $order['category_id']);
        }
    }


    /**
     * 订单佣金发放   释放
     */
    public function orderCommissRelease($order_data, $is_refund = false)
    {
        $technician_id = $order_data['technician_id'] ?? 0;
        if (empty($technician_id)) return true;
        $account_type = AccountDict::COMMISSION;
        $technician_model = new Technician();
        $technician_info = $technician_model->where([
            ['id', '=', $technician_id]
        ])->field($account_type)->lock(true)->find();
        if (empty($technician_info)) throw new CommonException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');
        $related_id = $order_data['order_no'];
        $from_type = $is_refund ? AccountDict::ORDER_REFUND_COMMISSION : AccountDict::ORDER_COMMISSION;
        $account = $this->model->where([
            ['status', '=', 0],
            ['from_type', '=', $from_type],
            ['related_id', '=', $related_id]
        ])->field('status,account_data')->lock(true)->find();
        if (!empty($account)) {
            $account->account_sum = bcadd($technician_info[$account_type], $account->account_data, 2);
            $account->payment_time = time();
            $account->status = 1;
            $account->save();
            (new Technician())->where([['id', '=', $technician_id]])
                ->inc($account_type . "_get", $account->account_data)
                ->inc($account_type, $account->account_data)
                ->update();

            event('NotificationEvent', [
                'identity' => [NoticeDict::TECHNICIAN],
                'type' => NoticeDict::COMMISSION_CREDITED,
                'notice_source' => NoticeDict::BILL,
                'order_id' => $order_data['order_id'],
                'technician_id' => $order_data['technician_id'],
                'member_id' => $order_data['member_id'],
                'site_id' => $order_data->site_id,
            ]);
        }
        return true;

    }


    /**
     * 师傅端账单统一入口
     */
    public function addLog(int $site_id, int $technician_id, string $account_type, $account_data, string $from_type, string $memo, $related_id = 0, $status = 0,$category_id=0)
    {
        $technician_model = new Technician();
        //账户检测
        $technician_info = $technician_model->where([
            ['id', '=', $technician_id]
        ])->field($account_type)->lock(true)->find();
        if (empty($technician_info)) throw new CommonException('O2O_TECHNICIAN_NOT_EXIST');
        if ($status == 1) {
            $account_new_data = round((float)$technician_info[$account_type] + (float)$account_data, 2);
            if ($account_new_data < 0) {
                throw new CommonException('ACCOUNT_INSUFFICIENT');
            }
        }
        $data = array(
            'site_id' => $site_id,
            'technician_id' => $technician_id,
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
                    $account_type_get = $technician_info[$account_type . "_get"] + $account_data;
                } else {
                    $account_type_get = $technician_info[$account_type . "_get"];
                }
                $technician_model->update([
                    $account_type => $account_new_data,
                    $account_type . "_get" => $account_type_get,
                ], [
                    'id' => $technician_id
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

        $field = 'payment_time,create_time,technician_id,account_type,account_data,account_sum,from_type,memo,related_id,status';
        $left_where = [];
        if (isset($where['site_id']) && !empty($where['site_id'])) {
            $left_where[] = ['technician_account.site_id', '=', $where['site_id']];
        }
        $search_model = $this->model
            ->withSearch(['join_create_time', 'technician_id'], $where)
            ->field($field)
            ->where($left_where)
            ->order('create_time desc')
            ->withJoin(
                [
                    'technician' => ['id', 'real_name', 'mobile']
                ]
            )
            ->append(['from_type_name', 'status_name']);
        $res = $this->pageQuery($search_model);
//        dd(Db::name('home_service_technician_account')->getLastSql());
        return $res;


    }


}
