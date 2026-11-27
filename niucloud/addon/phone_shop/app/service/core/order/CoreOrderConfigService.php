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

namespace addon\phone_shop\app\service\core\order;

use app\model\diy_form\DiyForm;
use app\service\core\sys\CoreConfigService;
use core\base\BaseCoreService;


/**
 * 订单设置服务层
 * Class CoreOrderConfigService
 * @package addon\phone_shop\app\service\core\order
 */
class CoreOrderConfigService extends BaseCoreService
{
    //系统配置文件
    public $core_config_service;

    public function __construct()
    {
        parent::__construct();
        $this->core_config_service = new CoreConfigService();
    }

    /**
     * 设置交易配置
     * @param array $params
     * @return array
     */
    public function setConfig($params)
    {
        $site_id = $params[ 'site_id' ];

        $value[ 'order_close' ] = [
            'is_close' => $params[ 'is_close' ],
            'close_length' => $params[ 'close_length' ]
        ];
        $value[ 'order_finish' ] = [
            'is_finish' => $params[ 'is_finish' ],
            'finish_length' => $params[ 'finish_length' ]
        ];
        $value[ 'order_refund' ] = [
            'no_allow_refund' => $params[ 'no_allow_refund' ],
            'refund_length' => $params[ 'refund_length' ]
        ];
        $value[ 'evaluate' ] = [
            'is_evaluate' => $params[ 'is_evaluate' ],
            'evaluate_is_to_examine' => $params[ 'evaluate_is_to_examine' ],
            'evaluate_is_show' => $params[ 'evaluate_is_show' ]
        ];
        $value[ 'form_id' ] = $params[ 'form_id' ];
        $value[ 'online_trade' ] = $this->normalizeOnlineTradeConfig($params);

        $this->core_config_service->setConfig($site_id, 'SHOP_ORDER_CONFIG', $value);

        $invoice = 'SHOP_INVOICE';
        $invoiceInfo = [
            'is_invoice' => $params[ 'is_invoice' ],
            'invoice_type' => $params[ 'invoice_type' ],
            'invoice_content' => $params[ 'invoice_content' ]
        ];
        $this->core_config_service->setConfig($site_id, $invoice, $invoiceInfo);

        $evaluate = 'SHOP_GOODS_EVALUATE';
        $evaluateInfo = [
            'is_evaluate' => $params[ 'is_evaluate' ],
            'evaluate_is_to_examine' => $params[ 'evaluate_is_to_examine' ],
            'evaluate_is_show' => $params[ 'evaluate_is_show' ]
        ];
        $this->core_config_service->setConfig($site_id, $evaluate, $evaluateInfo);

        return true;
    }

    /**
     * 小程序线上成交配置。
     *
     * 费率、承担方和金额会在创建订单时形成快照，后续修改不会改变历史订单金额；
     * 成交方式开关仍用于判断待支付订单能否继续发起线上支付。
     */
    public function getOnlineTradeConfig(int $site_id): array
    {
        $data = ( new CoreConfigService() )->getConfigValue($site_id, 'SHOP_ORDER_CONFIG');
        return $this->normalizeOnlineTradeConfig((array)($data['online_trade'] ?? []));
    }

    /**
     * 判断一笔已创建订单是否仍允许会员发起线上支付。
     *
     * 订单必须使用创建时保存的 pricing_identity 与 payment_mode 快照判断，
     * 避免会员等级后续变化导致历史订单的价格身份和支付权限错位。
     */
    public function getOrderOnlinePayState(array $order, ?array $config = null): array
    {
        $config = $config ?? $this->getOnlineTradeConfig((int)($order['site_id'] ?? 0));
        $paymentMode = (string)($order['payment_mode'] ?? 'online');
        $pricingIdentity = (string)($order['pricing_identity'] ?? 'retail');

        if ($paymentMode !== '' && $paymentMode !== 'online') {
            return [
                'can_online_pay' => 0,
                'online_pay_disabled_reason' => $paymentMode === 'offline_pending'
                    ? '该订单已选择线下支付，请等待商家联系处理'
                    : '该订单属于线下收款，不支持在线支付',
            ];
        }
        if ((int)($config['online_order_enabled'] ?? 1) !== 1) {
            return [
                'can_online_pay' => 0,
                'online_pay_disabled_reason' => '商家当前未开放线上支付',
            ];
        }
        if ($pricingIdentity === 'peer' && (int)($config['peer_online_enabled'] ?? 1) !== 1) {
            return [
                'can_online_pay' => 0,
                'online_pay_disabled_reason' => '同行价订单当前不支持线上支付，请联系商家',
            ];
        }

        return [
            'can_online_pay' => 1,
            'online_pay_disabled_reason' => '',
        ];
    }

    /**
     * 自动取消订单
     */
    public function orderClose(int $site_id)
    {
        $data = ( new CoreConfigService() )->getConfigValue($site_id, 'SHOP_ORDER_CONFIG');
        $config = (array)($data['order_close'] ?? []);
        $enabled = (int)($config['is_close'] ?? 1) === 1;
        $minutes = (int)($config['close_length'] ?? 120);
        // 部分站点只保存过成交方式，没有 order_close；不能因缺项让创建监听失败。
        return ['is_close' => $enabled ? '1' : '2', 'close_length' => $minutes > 0 ? $minutes : 120];
    }

    /** 在创建订单的同步事务内写入关闭时间，不依赖后置队列成功。 */
    public function pendingPaymentTimeout(int $siteId, string $paymentMode, int $createdAt): int
    {
        if ($paymentMode === 'offline_pending') {
            $minutes = (int)$this->getOnlineTradeConfig($siteId)['offline_timeout_minutes'];
        } elseif (in_array($paymentMode, ['', 'online'], true)) {
            $config = $this->orderClose($siteId);
            if ((int)$config['is_close'] !== 1) return 0;
            $minutes = (int)$config['close_length'];
        } else {
            return 0;
        }
        return $createdAt + $minutes * 60;
    }

    /**
     * 获取交易配置
     * @param int $site_id
     * @return array
     */
    public function getConfig(int $site_id)
    {
        $data = ( new CoreConfigService() )->getConfigValue($site_id, 'SHOP_ORDER_CONFIG');
        if (empty($data)) {
            $data[ 'confirm' ] = [
                'is_finish' => true,
                'finish_length' => 14
            ];
            $data[ 'refund' ] = [
                'no_allow_refund' => 1,
                'refund_length' => 7
            ];
            $data[ 'form_id' ] = '';
            $data[ 'online_trade' ] = $this->normalizeOnlineTradeConfig([]);
        } else {
            $data[ 'confirm' ] = [
                'is_finish' => $data[ 'order_finish' ][ 'is_finish' ],
                'finish_length' => $data[ 'order_finish' ][ 'finish_length' ],
            ];
            $data[ 'refund' ] = [
                'no_allow_refund' => $data[ 'order_refund' ][ 'no_allow_refund' ],
                'refund_length' => $data[ 'order_refund' ][ 'refund_length' ],
            ];
            $data[ 'form_id' ] = $data[ 'form_id' ] ?? '';
            $data[ 'online_trade' ] = $this->normalizeOnlineTradeConfig((array)($data['online_trade'] ?? []));

            if(!empty($data[ 'form_id' ])) {
                $diy_form_model = new DiyForm();
                $diy_form_count = $diy_form_model->where([
                    [ 'site_id', '=', $site_id ],
                    [ 'form_id', '=', $data[ 'form_id' ] ]
                ])->count();
                if ($diy_form_count == 0) {
                    $data[ 'form_id' ] = '';
                }
            }
        }

        $data['close_order_info'] = $this->orderClose($site_id);

        //发票
        $data[ 'invoice' ] = ( new CoreConfigService() )->getConfigValue($site_id, 'SHOP_INVOICE');
        if (empty($data[ 'invoice' ])) {
            $data[ 'invoice' ] = [
                'is_invoice' => '2',
                'invoice_type' => [],
                'invoice_content' => []
            ];
        }
        //评价
        $data[ 'evaluate' ] = ( new CoreConfigService() )->getConfigValue($site_id, 'SHOP_GOODS_EVALUATE');
        if (empty($data[ 'evaluate' ])) {
            $data[ 'evaluate' ] = [
                'is_evaluate' => 1,
                'evaluate_is_to_examine' => 1,
                'evaluate_is_show' => 1
            ];
        }
        return $data;
    }

    private function normalizeOnlineTradeConfig(array $params): array
    {
        $rate = (float)($params['peer_fee_rate'] ?? 0.006);
        // 防止误把“6”当成600%；管理端统一使用小数，0.006代表千分之六。
        $rate = max(0, min($rate, 0.2));
        $bearer = (string)($params['peer_fee_bearer'] ?? 'merchant');
        if (!in_array($bearer, ['merchant', 'customer'], true)) $bearer = 'merchant';
        $offlineEnabled = (int)($params['offline_order_enabled'] ?? 1) === 1 ? 1 : 0;
        $onlineEnabled = (int)($params['online_order_enabled'] ?? 1) === 1 ? 1 : 0;
        // 至少保留一种可提交方式，避免配置误操作导致商城完全无法成交。
        if ($offlineEnabled !== 1 && $onlineEnabled !== 1) $offlineEnabled = 1;
        $offlineDefault = $offlineEnabled === 1
            && (int)($params['offline_order_default'] ?? 1) === 1 ? 1 : 0;
        $offlineTimeout = max(15, min((int)($params['offline_timeout_minutes'] ?? 20), 10080));
        $offlineContactTip = trim((string)($params['offline_contact_tip']
            ?? '提交后将锁定设备，业务员会尽快联系您确认收款与交付方式。'));
        if ($offlineContactTip === '') {
            $offlineContactTip = '提交后将锁定设备，业务员会尽快联系您确认收款与交付方式。';
        }
        $offlinePaymentTip = trim((string)($params['offline_payment_tip']
            ?? '请在锁单有效期内完成转账并上传付款凭证；如已与门店人员确认，可由工作人员直接处理。'));
        if ($offlinePaymentTip === '') {
            $offlinePaymentTip = '请在锁单有效期内完成转账并上传付款凭证；如已与门店人员确认，可由工作人员直接处理。';
        }
        $handlers = [];
        foreach ((array)($params['offline_handlers'] ?? []) as $handler) {
            if (!is_array($handler)) continue;
            $uid = (int)($handler['uid'] ?? 0);
            if ($uid <= 0 || isset($handlers[$uid])) continue;
            $handlers[$uid] = [
                'uid' => $uid,
                'name' => mb_substr(trim((string)($handler['name'] ?? '')), 0, 100),
                'mobile' => mb_substr(trim((string)($handler['mobile'] ?? '')), 0, 30),
            ];
        }
        $handlers = array_values($handlers);
        $handlerUids = array_column($handlers, 'uid');
        $defaultHandlerUid = (int)($params['offline_default_handler_uid'] ?? 0);
        if ($defaultHandlerUid <= 0 || !in_array($defaultHandlerUid, $handlerUids, true)) {
            $defaultHandlerUid = (int)($handlers[0]['uid'] ?? 0);
        }
        $defaultHandler = [];
        foreach ($handlers as $handler) {
            if ((int)$handler['uid'] === $defaultHandlerUid) {
                $defaultHandler = $handler;
                break;
            }
        }
        $contactName = trim((string)($params['offline_contact_name'] ?? ($defaultHandler['name'] ?? '')));
        $contactMobile = trim((string)($params['offline_contact_mobile'] ?? ($defaultHandler['mobile'] ?? '')));

        return [
            'online_order_enabled' => $onlineEnabled,
            'peer_online_enabled' => (int)($params['peer_online_enabled'] ?? 1) === 1 ? 1 : 0,
            'peer_fee_rate' => number_format($rate, 6, '.', ''),
            'peer_fee_bearer' => $bearer,
            'offline_order_enabled' => $offlineEnabled,
            'offline_order_default' => $offlineDefault,
            'offline_peer_enabled' => (int)($params['offline_peer_enabled'] ?? 1) === 1 ? 1 : 0,
            'offline_timeout_minutes' => $offlineTimeout,
            'offline_contact_tip' => mb_substr($offlineContactTip, 0, 120),
            'offline_payment_qrcode' => mb_substr(trim((string)($params['offline_payment_qrcode'] ?? '')), 0, 500),
            'offline_payment_tip' => mb_substr($offlinePaymentTip, 0, 200),
            'offline_voucher_enabled' => (int)($params['offline_voucher_enabled'] ?? 1) === 1 ? 1 : 0,
            'offline_hold_on_progress' => (int)($params['offline_hold_on_progress'] ?? 1) === 1 ? 1 : 0,
            'offline_handlers' => $handlers,
            'offline_default_handler_uid' => $defaultHandlerUid,
            'offline_contact_name' => mb_substr($contactName, 0, 100),
            'offline_contact_mobile' => mb_substr($contactMobile, 0, 30),
        ];
    }

    /**
     * 订单确认收货
     */
    public function orderConfirm(int $site_id)
    {
        $data = ( new CoreConfigService() )->getConfigValue($site_id, 'SHOP_ORDER_CONFIG');
        if (empty($data)) {
            $confirmOrderInfo = [
                'is_finish' => true,
                'finish_length' => 14
            ];
        } else {
            $confirmOrderInfo = [
                'is_finish' => $data[ 'order_finish' ][ 'is_finish' ],
                'finish_length' => $data[ 'order_finish' ][ 'finish_length' ]
            ];
        }
        return $confirmOrderInfo;
    }

    /**
     * 售后
     */
    public function orderRefund(int $site_id)
    {
        $data = ( new CoreConfigService() )->getConfigValue($site_id, 'SHOP_ORDER_CONFIG');
        if (empty($data)) {
            $refundOrderInfo = [
                'no_allow_refund' => 1,
                'refund_length' => 7
            ];
        } else {
            $refundOrderInfo = [
                'no_allow_refund' => $data[ 'order_refund' ][ 'no_allow_refund' ],
                'refund_length' => $data[ 'order_refund' ][ 'refund_length' ]
            ];
        }
        return $refundOrderInfo;
    }

    /**
     * 发票信息
     */
    public function invoice(int $site_id)
    {
        $data = ( new CoreConfigService() )->getConfigValue($site_id, 'SHOP_INVOICE');
        if (empty($data)) {
            $invoiceInfo = [
                'is_invoice' => '',
                'invoice_type' => [],
                'invoice_content' => []
            ];
        } else {
            $invoiceInfo = [
                'is_invoice' => $data[ 'is_invoice' ],
                'invoice_type' => $data[ 'invoice_type' ],
                'invoice_content' => $data[ 'invoice_content' ]
            ];
        }
        return $invoiceInfo;
    }

    /**
     * 获取评价设置
     * @return array|int[]|mixed
     */
    public function getEvaluateConfig(int $site_id)
    {
        $config = ( new CoreConfigService() )->getConfigValue($site_id, 'SHOP_GOODS_EVALUATE');
        if (empty($config)) {
            $config = [
                'is_evaluate' => 1,
                'evaluate_is_to_examine' => 1,
                'evaluate_is_show' => 1
            ];
        }
        return $config;
    }
}

