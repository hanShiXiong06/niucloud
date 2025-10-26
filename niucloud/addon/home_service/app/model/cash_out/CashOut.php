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

namespace addon\home_service\app\model\cash_out;

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\dict\cash_out\CashOutDict;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\technician\Technician;
use app\dict\pay\TransferDict;
use app\model\pay\Transfer;
use core\base\BaseModel;
use think\model\relation\HasOne;

/**
 * 提现模型
 */
class CashOut extends BaseModel
{


    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_cash_out';


    /**
     * 转账信息
     * @return HasOne
     */
    public function transfer()
    {
        return $this->hasOne(Transfer::class, 'transfer_no', 'transfer_no')->joinType('left')
            ->withField('transfer_no,  transfer_type, transfer_realname, transfer_mobile, transfer_bank, transfer_account, transfer_voucher, transfer_remark, transfer_fail_reason, transfer_status, package_info, extra')->append(['transfer_status_name', 'transfer_type_name']);
    }

    /**
     * 账户类型名称
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getAccountTypeNameAttr($value, $data)
    {
        if (empty($data['account_type']))
            return '';
        return AccountDict::getAccountType()[$data['account_type']] ?? '';
    }

    /**
     * 提现状态名称
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getStatusNameAttr($value, $data)
    {
        if (empty($data['status']))
            return '';
        return CashOutDict::getStatus()[$data['status']] ?? '';
    }

    /**
     * 转账方式名称
     * @param $value
     * @param $data
     * @return array|mixed|string
     */
    public function getTransferTypeNameAttr($value, $data)
    {
        if (empty($data['transfer_type']))
            return '';
        $temp = TransferDict::getTransferType()[$data['transfer_type']] ?? [];
        return $temp['name'] ?? '';
    }

    /**
     * 转账状态名称
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getTransferStatusNameAttr($value, $data)
    {
        if (empty($data['transfer_status']))
            return '';
        return TransferDict::getStatus()[$data['transfer_status']] ?? '';
    }

    /**
     * 门店信息
     * @return HasOne
     */
    public function store()
    {
        return $this->hasOne(Store::class, 'store_id', 'related_id');
    }

    /**
     * 师傅信息
     * @return HasOne
     */
    public function technician()
    {
        return $this->hasOne(Technician::class, 'id', 'related_id');
    }
}
