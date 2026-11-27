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

namespace addon\phone_shop\app\adminapi\controller\order;

use addon\phone_shop\app\service\admin\order\ConfigService;
use core\base\BaseAdminController;

/**
 * 订单交易设置
 * Class Config
 * @package addon\phone_shop\app\adminapi\controller\config
 */
class Config extends BaseAdminController
{
    /**
     * 交易设置配置
     * @description 设置交易设置配置
     * @return \think\Response
     */
    public function setConfig()
    {
        $data = $this->request->params([
            [ "is_close", 1 ],
            [ "close_length", "" ],
            [ "is_finish", "" ],
            [ "finish_length", 1 ],
            [ "no_allow_refund", "" ],
            [ "refund_length", "" ],
            [ "is_invoice", "" ],
            [ "invoice_type", "" ],
            [ "invoice_content", "" ],
            [ "is_evaluate", 1 ],
            [ "evaluate_is_to_examine", 1 ],
            [ "evaluate_is_show", 1 ],
            [ 'form_id', '' ],
            [ 'online_order_enabled', 1 ],
            [ 'peer_online_enabled', 1 ],
            [ 'peer_fee_rate', '0.006000' ],
            [ 'peer_fee_bearer', 'merchant' ],
            [ 'offline_order_enabled', 1 ],
            [ 'offline_order_default', 1 ],
            [ 'offline_peer_enabled', 1 ],
            [ 'offline_timeout_minutes', 20 ],
            [ 'offline_contact_tip', '提交后将锁定设备，业务员会尽快联系您确认收款与交付方式。' ],
            [ 'offline_payment_qrcode', '' ],
            [ 'offline_payment_tip', '请在锁单有效期内完成转账并上传付款凭证；如已与门店人员确认，可由工作人员直接处理。' ],
            [ 'offline_voucher_enabled', 1 ],
            [ 'offline_hold_on_progress', 1 ],
            [ 'offline_handlers', [] ],
            [ 'offline_default_handler_uid', 0 ],
            [ 'offline_contact_name', '' ],
            [ 'offline_contact_mobile', '' ]
        ]);

        ( new ConfigService() )->setConfig($data);
        return success('SUCCESS');
    }

    /**
     * 获取交易配置
     * @description 获取交易配置
     * @return \think\Response
     */
    public function getConfig()
    {
        return success(( new ConfigService() )->getConfig());
    }

}
