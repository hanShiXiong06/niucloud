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
use addon\home_service\app\model\cash_out\CashOut;
use addon\home_service\app\model\cash_out\CashOutAccount;
use app\dict\pay\TransferDict;
use app\dict\sys\ConfigKeyDict;
use app\service\core\sys\CoreConfigService;
use core\base\BaseCoreService;
use core\exception\CommonException;

/**
 * 提现
 * Class CoreCashOutConfigService
 */
class  CoreCashOutConfigService extends BaseCoreService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new CashOut();
    }

    /**
     * 获取提现设置
     * @param int $site_id
     * @return array
     */
    public function getCashOutConfig(int $site_id)
    {
        $config = ( new CoreConfigService() )->getConfig($site_id, ConfigKeyDict::MEMBER_CASH_OUT)[ 'value' ] ?? [];
        return [
            'is_open' => $config[ 'is_open' ] ?? '0',//是否启用提现
            'transfer_type' => $config[ 'transfer_type' ] ?? [],//提现方式
            'min' => $config[ 'min' ] ?? '0',//最低提现金额
//            'max'            => '0',//最高提现金额
            'rate' => $config[ 'rate' ] ?? '0',//手续费比率
            'is_auto_verify' => $config[ 'is_auto_verify' ] ?? '0',  //是否自动审核
            'is_auto_transfer' => $config[ 'is_auto_transfer' ] ?? '0',  //是否自动转账
        ];
    }

    /**
     * 提现配置
     * @param int $site_id
     * @param array $data
     * @return true
     */
    public function setCashOutConfig(int $site_id, array $data)
    {
        //校验转账方式是否合法
        $transfer_type_list = array_keys(TransferDict::getTransferType());
        if (array_diff(array_diff($data[ 'transfer_type' ], $transfer_type_list), $transfer_type_list)) throw new CommonException('TRANSFER_TYPE_NOT_EXIST');
        foreach ($transfer_type_list as $key => $item) {
            if (!in_array($item, $data[ 'transfer_type' ])) {
                unset($transfer_type_list[ $key ]);
            }
        }
        $transfer_type_list = array_values($transfer_type_list);
        $config = [
            'is_open' => $data[ 'is_open' ],//是否启用提现
            'transfer_type' => $transfer_type_list ?? [],//提现方式
            'min' => $data[ 'min' ] ?? '',//最低提现金额
            'is_auto_verify' => $data[ 'is_auto_verify' ] ?? 0,  //是否自动审核
            'is_auto_transfer' => $data[ 'is_auto_transfer' ] ?? 0,  //是否自动转账
//            'max'            => $data['max'] ?? '',//最高提现金额
            'rate' => $data[ 'rate' ] ?? '',//手续费比率
        ];
        ( new CoreConfigService() )->setConfig($site_id, ConfigKeyDict::MEMBER_CASH_OUT, $config);
        return true;
    }
}
