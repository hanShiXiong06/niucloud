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

namespace addon\home_service\app\service\api\technician;

use addon\home_service\app\dict\cash_out\CashOutDict;
use addon\home_service\app\model\cash_out\CashOutAccount;
use addon\home_service\app\service\core\cash_out\CoreCashOutAccountService;
use core\base\BaseApiService;

/**
 * 提现账户服务层
 */
class CashOutAccountService extends BaseApiService
{
    use TechnicianTrait;
    public function __construct()
    {
        parent::__construct();
        $this->checkTechnician();
    }

    /**
     * 师傅提现账户列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $where['site_id'] = $this->site_id;
        $where['source'] = CashOutDict::TECHNICIAN;
        $where['related_id'] = $this->technician_id;

        return (new CoreCashOutAccountService())->getAccountPage($where);
    }

    /**
     * 提现账户详情
     * @param int $account_id
     * @return array
     */
    public function getInfo(int $account_id)
    {
        $where['account_id'] = $account_id;
        $where['source'] = CashOutDict::TECHNICIAN;
        $where['site_id'] = $this->site_id;
        $where['related_id'] = $this->technician_id;
        return (new CoreCashOutAccountService())->getInfo($where);
    }

    /**
     * 获取首条信息
     * @param array $where
     * @param string $order_field
     * @param string $order
     * @return array
     */
    public function getFirstInfo(array $where, $order_field = 'create_time', string $order = 'desc'){
        $where['site_id'] = $this->site_id;
        $where['source'] = CashOutDict::TECHNICIAN;
        $where['related_id'] = $this->technician_id;
        return (new CoreCashOutAccountService())->getFirstInfo($where);
    }

    /**
     * 添加提现账号
     * @param array $data
     * @return  int
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['source'] = CashOutDict::TECHNICIAN;
        $data['related_id'] = $this->technician_id;
        $data['create_time'] = time();

        return (new CoreCashOutAccountService())->addAccount($data);
    }

    /**
     * 修改提现账户
     * @param int $account_id
     * @param array $data
     * @return true
     */
    public function edit(int $account_id, array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['account_id'] = $account_id;
        $data['source'] = CashOutDict::TECHNICIAN;
        $data['related_id'] = $this->technician_id;
        $data['update_time'] = time();
        (new CoreCashOutAccountService())->editAccount($data);
        return true;
    }

    /**
     * 删除
     * @param int $account_id
     * @return true
     */
    public function del(int $account_id)
    {
        $where = [
            ['related_id', '=', $this->technician_id],
            ['site_id', '=', $this->site_id],
            ['account_id', '=', $account_id]
        ];
        (new CoreCashOutAccountService())->delAccount($where);
        return true;
    }
}
