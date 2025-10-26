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

namespace addon\home_service\app\service\admin\cash_out;

use addon\home_service\app\dict\cash_out\CashOutDict;
use addon\home_service\app\model\cash_out\CashOut;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\service\core\cash_out\CoreCashOutService;
use app\dict\pay\TransferDict;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 提现服务层
 */
class CashOutService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new CashOut();
    }

    /**
     * 提现列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {

        $field = 'id,site_id,cash_out_no,related_id,account_type,transfer_type,transfer_realname,transfer_mobile,transfer_bank,transfer_account,transfer_fail_reason,transfer_status,transfer_time,apply_money,rate,service_money,money,status,remark,create_time,refuse_reason,transfer_no, transfer_payment_code';
        $related_where = [];
        $cash_out_where[] = ['cash_out.site_id', '=', $this->site_id];
        $cash_out_where[] = ['cash_out.source', '=', $where['source']];
        if (!empty($where['status'])){
            $cash_out_where[] = ['cash_out.status', '=', $where['status']];
        }

        $search_model = $search_model = $this->model->where($cash_out_where)->field($field)
            ->with(['transfer']);
        if ($where['source'] == CashOutDict::TECHNICIAN){
            if(!empty($where['keywords']))
            {
                $related_where = [['real_name', 'like', '%' . $where['keywords'] . "%" ]];
            }
            $search_model = $search_model->withJoin(["technician" =>function($query) use ($related_where){
                $query->field('real_name as related_name');
            }])->hidden(['technician'])->where($related_where);
        }elseif ($where['source'] == CashOutDict::STORE){
            if(!empty($where['keywords']))
            {
                $related_where = [['store_name', 'like', '%' . $where['keywords'] . "%" ]];
            }
            $search_model = $search_model->withJoin(["store" =>function($query) use ($related_where){
                $query->field('store_name as related_name');
            }])->hidden(['store'])->where($related_where);
        }
        $search_model->order('create_time desc')->append(['status_name', 'transfer_status_name', 'transfer_type_name', 'account_type_name']);
        return $this->pageQuery($search_model);
    }

    /**
     * 提现详情
     * @param int $cash_out_id
     * @return array
     */
    public function getInfo(int $cash_out_id)
    {
        $field = 'id,site_id,cash_out_no,source,related_id,account_type,transfer_type,transfer_realname,transfer_mobile,transfer_bank,transfer_account,transfer_fail_reason,transfer_status,transfer_time,apply_money,rate,service_money,money,status,remark,create_time,refuse_reason,transfer_no, transfer_payment_code';
        $cash_out_info = $this->model->where([['id', '=', $cash_out_id], ['site_id', '=', $this->site_id]])->with(['transfer'])->field($field)->append(['status_name', 'transfer_status_name', 'transfer_type_name', 'account_type_name'])->findOrEmpty()->toArray();
        if (!empty($cash_out_info)){
            $real_name = '';
            if ($cash_out_info['source'] == CashOutDict::TECHNICIAN){
                $real_name = (new Technician())->where([['site_id', '=', $this->site_id], ['id', '=', $cash_out_info['related_id']]])->value('real_name');
            }elseif ($cash_out_info['source'] == CashOutDict::STORE){
                $real_name = (new Store())->where([['site_id', '=', $this->site_id], ['store_id', '=', $cash_out_info['related_id']]])->value('store_name');
            }
            $cash_out_info['real_name'] = $real_name;
        }
        return $cash_out_info;
    }

    /**
     * 转账
     * @param int $cash_out_id
     * @param array $data
     * @return true
     */
    public function transfer(int $cash_out_id, array $data){
        $core_cash_out_service = new CoreCashOutService();
        $cash_out = $core_cash_out_service->find($this->site_id, $cash_out_id);
        if ($cash_out->isEmpty()) throw new CommonException('RECHARGE_LOG_NOT_EXIST');
        if ($cash_out['status'] != CashOutDict::WAIT_TRANSFER && $cash_out['transfer_type'] == TransferDict::WECHAT) throw new CommonException('CASH_OUT_WECHAT_ACCOUNT_NOT_ALLOW_ADMIN');
        return $core_cash_out_service->transfer($this->site_id, $cash_out_id, $data);
    }

    /**
     * 备注
     * @param int $cash_out_id
     * @param array $data
     * @return true
     */
    public function remark(int $cash_out_id, array $data){
        $core_cash_out_service = new CoreCashOutService();
        return $core_cash_out_service->remark($this->site_id, $cash_out_id, $data);
    }

    /**
     * 统计数据
     * @return array
     */
    public function stat()
    {
        $stat = [];
        //已提现
        $stat['transfered'] = $this->model->where([['status', '=', CashOutDict::TRANSFERED], ['site_id', '=', $this->site_id]])->sum("apply_money");
        //所有金额（包括提现中，已提现）
        $all_money = $this->model->where([['status', '>=', 0], ['site_id', '=', $this->site_id]])->sum("apply_money");

        $stat['cash_outing'] = $all_money - $stat['transfered'];
        return $stat;
    }

    /**
     * 检测实际的转账状态
     * @param int $id
     * @return true
     */
    public function checkTransferStatus(int $id){
        $core_cash_out_service = new CoreCashOutService();
        return $core_cash_out_service->checkTransferStatus($this->site_id, $id);
    }

    /**
     * 取消体现
     * @param int $cash_out_id
     * @return true|null
     */
    public function cancel(int $cash_out_id){
        $core_cash_out_service = new CoreCashOutService();
        return $core_cash_out_service->cancel($this->site_id, $cash_out_id);
    }

}
