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

namespace addon\home_service\app\service\core\cash_out;

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\dict\cash_out\CashOutDict;
use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\model\account\StoreAccount;
use addon\home_service\app\model\account\TechnicianAccount;
use addon\home_service\app\model\cash_out\CashOut;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\service\core\account\CoreStoreAccountService;
use addon\home_service\app\service\core\account\CoreTechnicianAccountService;
use app\dict\pay\TransferDict;
use app\service\core\pay\CoreTransferService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Cache;
use think\facade\Db;

/**
 * 订单
 * Class CoreCardOrderCreateService
 */
class  CoreCashOutService extends BaseCoreService
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
    public function getCashOutPage(array $where = [])
    {
        $field = 'id,site_id,cash_out_no,related_id,account_type,transfer_type,transfer_realname,transfer_mobile,transfer_bank,transfer_account,transfer_status,transfer_time,apply_money,rate,service_money,money,status,remark,create_time,refuse_reason';
        $search_model = $this->model->where($where)->with(['transfer'])->field($field)->append(['account_type_name', 'transfer_type_name', 'status_name', 'transfer_status_name'])->order('create_time desc');

        return $this->pageQuery($search_model);
    }

    /**
     * 提现详情
     * @param int $id
     * @return array
     */
    public function getCashOutInfo($data)
    {
        $field = 'id,site_id,cash_out_no,related_id,transfer_type,transfer_realname,transfer_mobile,transfer_bank,transfer_account,transfer_fail_reason,transfer_time,apply_money,rate,service_money,money,status,remark,create_time,refuse_reason, transfer_no, transfer_payee, transfer_payment_code';
        return $this->model->where([['id', '=', $data['id']], ['site_id', '=', $data['site_id']], ['source', '=', $data['source']], ['related_id', '=',$data['related_id']]])->with(['transfer'])->field($field)->append(['account_type_name', 'transfer_type_name', 'status_name', 'transfer_status_name'])->findOrEmpty()->toArray();
    }


    /**
     * 提现转账完成
     * @param $site_id
     * @param $transfer_no
     * @return true
     */
    public function transferFinish($site_id, $transfer_no)
    {
        Db::startTrans();
        try {
            $cash_out = $this->model->where(
                [
                    ['site_id', '=', $site_id],
                    ['transfer_no', '=', $transfer_no]
                ]
            )->findOrEmpty();

            if ($cash_out->isEmpty()) throw new CommonException('RECHARGE_LOG_NOT_EXIST');
            if ($cash_out['status'] != CashOutDict::WAIT_TRANSFER) throw new CommonException('CASHOUT_STATUS_NOT_IN_WAIT_TRANSFER');


            switch ($cash_out->source){
                case CashOutDict::TECHNICIAN:
                    $related_info = (new Technician())->where([['site_id', '=', $cash_out->site_id],['id', '=', $cash_out->related_id]])->findOrEmpty();
                    $account_model = (new TechnicianAccount());
                    break;
                case CashOutDict::STORE:
                    $related_info = (new Store())->where([['site_id', '=', $cash_out->site_id],['store_id', '=', $cash_out->related_id]])->findOrEmpty();
                    $account_model = (new StoreAccount());
                    break;
                default:
                    throw new CommonException('CASH_OUT_SOURCE_FAIL');
                    break;
            }

            $account = $account_model->where([
                ['status', '=', 0],
                ['from_type', '=', AccountDict::CASH_OUT],
                ['related_id', '=', $cash_out->cash_out_no]
            ])->field('status,account_data')->lock(true)->find();
            $account->account_sum = $related_info[AccountDict::COMMISSION];
            $account->payment_time = time();
            $account->status = 1;
            $account->save();


            //减去提现中金额
            $this->give($site_id, $cash_out);
            $cash_out->save([
                'status' => CashOutDict::TRANSFERED,
                'transfer_time' => time()
            ]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 累加提现金额
     * @param int $site_id
     * @param CashOut $cash_out
     * @return true
     */
    public function give(int $site_id, CashOut $cash_out)
    {
        switch ($cash_out->source){
            case CashOutDict::TECHNICIAN:
                $related_info = (new Technician())->where([['site_id', '=', $cash_out->site_id],['id', '=', $cash_out->related_id]])->findOrEmpty();
                break;
            case CashOutDict::STORE:
                return true;
                break;
            default:
                throw new CommonException('CASH_OUT_SOURCE_FAIL');
                break;
        }
        if ($related_info->isEmpty()) throw new CommonException('CASH_OUT_ACCOUNT_NOT_EXIST');
        $related_info->withdraw_get = (float)$related_info->withdraw_get + (float)$cash_out->apply_money;
        $related_info->save();
        return true;
    }

    /**
     * 申请提现
     * @param array $data
     * @return true
     */
    public function apply( array $data)
    {
        switch ($data['source']){
            case CashOutDict::TECHNICIAN:
                $cash_out_info = (new Technician())->where([['site_id', '=', $data['site_id']], ['id', '=', $data['related_id']]])->findOrEmpty();
                break;
            case CashOutDict::STORE:
                $cash_out_info = (new Store())->where([['site_id', '=', $data['site_id']], ['store_id', '=', $data['related_id']]])->findOrEmpty();
                break;
            default:
                throw new CommonException('CASH_OUT_ACCOUNT_NOT_EXIST');
                break;
        }
        if ($cash_out_info->isEmpty()) throw new CommonException('CASH_OUT_ACCOUNT_NOT_EXIST');

        $config = (new CoreCashOutConfigService())->getCashOutConfig($data['site_id']);
        $is_open = $config['is_open'];
        if ($is_open == 0) throw new CommonException('CASHOUT_NOT_OPEN');
        $apply_money = $data['apply_money'];
        if ($apply_money < $config['min']) throw new CommonException('CASHOUT_MONEY_TOO_LITTLE');
        $transfer_type = $data['transfer_type'];
        if (!in_array($transfer_type, $config['transfer_type'])) throw new CommonException('CASHOUT_TYPE_NOT_OPEN');
        $service_money = format_round_money($apply_money * $config['rate'] / 100);
        $min = $config['min'];
        if ($apply_money < $min) throw new CommonException('CASHOUT_MONEY_TOO_LITTLE');
//        $apply_money, $transfer_type, $transfer_realname, $transfer_mobile, $transfer_bank, $transfer_account
        $money = $apply_money - $service_money;
        $account_type = $data['account_type'] ?? AccountDict::COMMISSION;


        $cash_out_account = [];
        if ($transfer_type != TransferDict::WECHAT) {
            $cash_out_account_where['site_id'] = $data['site_id'];
            $cash_out_account_where['source'] = $data['source'];
            $cash_out_account_where['related_id'] = $data['related_id'];
            $cash_out_account_where['account_id'] = $data['account_id'];
            $cash_out_account = (new CoreCashOutAccountService())->getInfo($cash_out_account_where);
            if (empty($cash_out_account)) throw new CommonException('CASH_OUT_ACCOUNT_NOT_EXIST');
        } else {
//            $data_transfer_payee = $data['transfer_payee'] ?? [];
//            if (empty($data_transfer_payee)) throw new CommonException('CASH_OUT_ACCOUNT_NOT_FOUND_VALUE');//转账到微信零钱缺少参数
//            $transfer_payee = [
//                'open_id' => $data_transfer_payee['open_id'] ?? '',
//                'channel' => $data_transfer_payee['channel'] ?? '',
//            ];
            $transfer_payee = [];
        }

        Db::startTrans();
        try {
            $cash_out_no = $this->createCashOutNo($data['site_id']);
            $save_data = [
                'site_id' => $data['site_id'],
                'source' => $data['source'],
                'related_id' => $data['related_id'],
                'cash_out_no' => $cash_out_no,
                'status' => CashOutDict::WAIT_TRANSFER,
                'account_type' => $account_type,
                'apply_money' => $apply_money,
                'service_money' => $service_money,
                'money' => $money,
                'transfer_type' => $transfer_type,
                'transfer_realname' => $cash_out_account['realname'] ?? '',
                'transfer_mobile' => $cash_out_info['mobile'] ?? '',
                'transfer_bank' => $cash_out_account['bank_name'] ?? '',
                'transfer_account' => $cash_out_account['account_no'] ?? '',
                'transfer_payment_code' => $cash_out_account['transfer_payment_code'] ?? '',//收款码
                'transfer_payee' => $transfer_payee ?? [],//对接线上转账数据
                'rate' => $config['rate'],
            ];
            $cash_out = $this->model->create($save_data);
            switch ($data['source']){
                case CashOutDict::TECHNICIAN:
                    $cash_out_account_service = new CoreTechnicianAccountService();
                    $memo = get_lang('TECHNICIAN_APPLY_CASHOUT');
                    break;
                case CashOutDict::STORE:
                    $cash_out_account_service = new CoreStoreAccountService();
                    $memo = get_lang('STORE_APPLY_CASHOUT');
                    break;
                default:
                    break;
            }
            //扣除对应账户金额
            $cash_out_account_service->addLog($data['site_id'], $data['related_id'], $account_type, -$apply_money, AccountDict::CASH_OUT, $memo, $cash_out_no, 0);
            $cash_out_info->commission -= $apply_money;
            $cash_out_info->save();
            Db::commit();
            return $cash_out['id'];
        } catch ( \Exception $e ) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 创建订单编号
     * @param int $site_id
     * @return string
     */
    public function createCashOutNo(int $site_id)
    {
        $time_str = date('YmdHi');
        $max_no = Cache::get('home_service_cash_out_no_' . $site_id . '_' . $time_str);

        if (!isset($max_no) || empty($max_no)) {
            $max_no = 1;
        } else {
            ++$max_no;
        }
        $cash_out_no = $time_str . $site_id . sprintf('%03d', $max_no);
        Cache::set('home_service_cash_out_no_' . $site_id . '_' . $time_str, $max_no);
        return $cash_out_no;
    }

    /**
     * 转账
     * @param int $site_id
     * @param int $id
     * @param array $data
     * @return true
     */
    public function transfer(int $site_id, int $id, array $data = [])
    {
        Db::startTrans();
        try {
            $transfer_type = $data['transfer_type'] ?? '';

            $cash_out = $this->find($site_id, $id);
            if ($cash_out->isEmpty()) throw new CommonException('RECHARGE_LOG_NOT_EXIST');
            if ($cash_out['status'] != CashOutDict::WAIT_TRANSFER) throw new CommonException('CASHOUT_STATUS_NOT_IN_WAIT_TRANSFER');
            $transfer_no = $cash_out['transfer_no'];
            if (!$transfer_no) {
                switch ($cash_out['source']){
                    case CashOutDict::TECHNICIAN:
                        $trade_type = CashOutDict::TECHNICIAN_CASH_OUT;
                        $main_type = CashOutDict::TECHNICIAN;
                        $remark = get_lang('TECHNICIAN_CASHOUT_TRANSFER');
                        break;
                    case CashOutDict::STORE:
                        $trade_type = CashOutDict::STORE_CASH_OUT;
                        $main_type = CashOutDict::STORE;
                        $remark  = get_lang('STORE_CASHOUT_TRANSFER');
                        break;
                    default:
                        throw new CommonException('CASH_OUT_SOURCE_FAIL');
                        break;
                }

                $transfer_no = (new CoreTransferService())->create($site_id, $main_type, $cash_out['related_id'], $cash_out['money'], $trade_type, $remark);
                $cash_out->save(
                    [
                        'transfer_no' => $transfer_no,
                    ]
                );
            }

            if ($transfer_type != TransferDict::OFFLINE) {
                $data['transfer_type'] = $cash_out['transfer_type'];
                $data['transfer_realname'] = $cash_out['transfer_realname'];
                $data['transfer_mobile'] = $cash_out['transfer_mobile'];
                $data['transfer_bank'] = $cash_out['transfer_bank'];
                $data['transfer_account'] = $cash_out['transfer_account'];
                $data['transfer_payment_code'] = $cash_out['transfer_payment_code'];
                $transfer_type = $cash_out['transfer_type'];
                if ($transfer_type == TransferDict::WECHAT) {//如果是转账到微信钱包，则需要获取openid
                    //根据转账方式和会员的授权信息来判断可以使用的转账方式
                    $data['transfer_payee'] = [
                        'open_id' => $data['open_id'] ?? '',
                        'channel' => $data['channel'] ?? '',
                    ];
                }
            } else {
                $transfer_type = $cash_out['transfer_type'];
            }

            $result = (new CoreTransferService())->transfer($site_id, $transfer_no, $transfer_type, $data);
            if ($cash_out['source'] == CashOutDict::TECHNICIAN){
                event('NotificationEvent', [
                    'identity' => [NoticeDict::TECHNICIAN],
                    'type' => NoticeDict::CASH_OUT_SUCCESS,
                    'notice_source' => NoticeDict::BILL,
                    'order_id' => 0,
                    'technician_id' => $cash_out['related_id'],
                    'member_id' => 0,
                    'site_id' => $site_id,
                ]);
            }
            Db::commit();
            return $result;
            // 提交事务

        } catch (\Exception $e) {
            Db::rollback();
            // 回滚事务
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 返还用户的对应账户
     * @param int $site_id
     * @param CashOut $cash_out
     * @return true
     */
    public function giveback(int $site_id, CashOut $cash_out)
    {
        switch ($cash_out->source){
            case CashOutDict::TECHNICIAN:
                $related_info = (new Technician())->where([['site_id', '=', $site_id],['id', '=', $cash_out->related_id]])->findOrEmpty();

                $cash_out_account_model = new TechnicianAccount();
                break;
            case CashOutDict::STORE:
                $related_info = (new Store())->where([['site_id', '=', $site_id],['store_id', '=', $cash_out->related_id]])->findOrEmpty();

                $cash_out_account_model = new StoreAccount();
                break;
            default:
                throw new CommonException('CASH_OUT_SOURCE_FAIL');
                break;
        }
        if ($related_info->isEmpty()) throw new CommonException('CASH_OUT_ACCOUNT_NOT_EXIST');
        $related_info->commission += $cash_out->apply_money;
        $related_info->save();

        $account = $cash_out_account_model->where([
            ['status', '=', 0],
            ['from_type', '=', AccountDict::CASH_OUT],
            ['related_id', '=', $cash_out->cash_out_no]
        ])->field('status,account_data')->lock(true)->findOrEmpty();

        if ($account->isEmpty())  throw new CommonException('CASH_OUT_ACCOUNT_NOT_EXIST');
        $account->delete();

        return true;
    }

    /**
     * 当前可用的转账方式
     * @param $site_id
     * @return array|array[]
     */
    public function getTransferType($site_id)
    {
        $config = (new CoreMemberConfigService())->getCashOutConfig($site_id);
        return TransferDict::getTransferType($config['transfer_type'], false);
    }

    /**
     * 备注
     * @param int $site_id
     * @param int $cash_out_id
     * @param array $data
     * @return true
     */
    public function remark(int $site_id, int $cash_out_id, array $data)
    {
        $cash_out = $this->model->where([['site_id', '=', $site_id], ['id', '=', $cash_out_id]])->findOrEmpty();
        $cash_out->save([
            'remark' => $data['remark']
        ]);
        return true;
    }


    public function checkTransferStatus(int $site_id, int $id){
        $cash_out = $this->find($site_id, $id);
        $core_transfer_service = new CoreTransferService();
        $status = $core_transfer_service->check($site_id, [
            'transfer_no' => $cash_out['transfer_no']
        ]);
        return true;
    }

    /**
     * 取消提现
     * @param int $site_id
     * @param int $cash_id
     * @return true
     */
    public function cancel(int $site_id, int $cash_id){

        Db::startTrans();
        try {
            $cash_out = $this->find($site_id, $cash_id);
            if ($cash_out->isEmpty()) throw new CommonException('RECHARGE_LOG_NOT_EXIST');
            if ($cash_out['status'] != CashOutDict::WAIT_TRANSFER) throw new CommonException('CASHOUT_STATUS_NOT_IN_CANCEL');
            $cash_out->save([
                'status' => CashOutDict::CANCEL,
            ]);
            $this->giveback($site_id, $cash_out);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 获取对象
     * @param int $site_id
     * @param int $id
     * @return CashOut|array|mixed|Model
     */
    public function find(int $site_id, int $id)
    {
        return $this->model->where([
            ['site_id', '=', $site_id],
            ['id', '=', $id],
        ])->findOrEmpty();
    }
}
