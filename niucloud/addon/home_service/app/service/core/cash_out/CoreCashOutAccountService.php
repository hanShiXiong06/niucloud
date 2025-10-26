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

use addon\home_service\app\dict\cash_out\CashOutDict;
use addon\home_service\app\model\cash_out\CashOutAccount;
use core\base\BaseCoreService;
/**
 * 提现账户
 * Class CoreCardOrderCreateService
 */
class  CoreCashOutAccountService extends BaseCoreService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new CashOutAccount();
    }


    /**
     * 提现账户列表
     * @param array $where
     * @return array
     */
    public function getAccountPage(array $where = [])
    {
        $field = 'account_id,site_id,related_id,account_type,bank_name,realname,account_no';
        $search_model = $this->model->where($where)->field($field)->order('create_time desc');

        return $this->pageQuery($search_model);
    }

    /**
     * 提现账户详情
     * @param array $where
     * @return array
     */
    public function getInfo($where)
    {
        $field = 'account_id,site_id,related_id,account_type,bank_name,realname,account_no, transfer_payment_code';
        return $this->model->where($where)->field($field)->findOrEmpty()->toArray();
    }

    /**
     * 获取首条信息
     * @param array $where
     * @param string $order_field
     * @param string $order
     * @return array
     */
    public function getFirstInfo(array $where, $order_field = 'create_time', string $order = 'desc'){

        $field = 'account_id,site_id,related_id,account_type,bank_name,realname,account_no';
        return $this->model->where($where)->order($order_field, $order)->field($field)->findOrEmpty()->toArray();
    }

    /**
     * 添加提现账号
     * @param array $data
     * @return  int
     */
    public function addAccount(array $data)
    {
        $res = $this->model->create($data);
        return $res->account_id;
    }

    /**
     * 修改提现账户
     * @param array $data
     * @return true
     */
    public function editAccount($data)
    {
        $data['update_time'] = time();
        $this->model->update($data, [ [ 'site_id', '=', $data['site_id'] ], [ 'related_id', '=', $data['related_id']], ['account_id', '=', $data['account_id']] ]);
        return true;
    }

    /**
     * 删除
     * @param array $where
     * @return true
     */
    public function delAccount($where)
    {
        $this->model->where($where)->delete();
        return true;
    }

}
