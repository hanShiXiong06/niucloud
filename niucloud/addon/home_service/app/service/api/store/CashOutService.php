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

namespace addon\home_service\app\service\api\store;

use addon\home_service\app\dict\cash_out\CashOutDict;
use addon\home_service\app\service\core\cash_out\CoreCashOutService;
use app\service\core\member\CoreMemberCashOutService;
use app\service\core\member\CoreMemberConfigService;
use core\base\BaseApiService;

/**
 * 提现服务层
 */
class CashOutService extends BaseApiService
{
    use StoreTrait;
    public function __construct()
    {
        parent::__construct();
        $this->checkStore();
    }

    /**
     * 会员提现列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $where['source'] = CashOutDict::STORE;
        $where['related_id'] = $this->store_id;
        $where['site_id'] = $this->site_id;
        return (new CoreCashOutService())->getCashOutPage($where);
    }

    /**
     * 提现详情
     * @param int $cash_id
     * @return array
     */
    public function getInfo(int $cash_id)
    {
        $where['id'] = $cash_id;
        $where['site_id'] = $this->site_id;
        $where['related_id'] = $this->store_id;
        $where['source'] = CashOutDict::STORE;
        return (new CoreCashOutService())->getCashOutInfo($where);
    }


    /**
     * 申请提现
     * @param array $data
     * @return true
     */
    public function apply(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['source'] = CashOutDict::STORE;;
        $data['related_id'] = $this->store_id;
        return (new CoreCashOutService())->apply($data);
    }


    /**
     * 撤销提现申请
     * @param int $cash_id
     * @return true
     */
    public function cancel(int $cash_id){
        (new CoreCashOutService())->cancel($this->site_id, $cash_id);
        return true;
    }

    /**
     * 获取提现配置
     * @return array
     */
    public function getCashOutConfig(){
        return (new CoreMemberConfigService())->getCashOutConfig($this->site_id);
    }

    /**
     * 提现转账(主要用于微信商家转账)
     * @param int $id
     * @param array $data
     * @return true
     */
    public function transfer(int $id, array $data){
        $data['channel'] = $this->channel;
        $result = (new CoreMemberCashOutService())->transfer($this->site_id, $id, $data);
        return $result;
    }
}
