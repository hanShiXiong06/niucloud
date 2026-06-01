<?php

namespace addon\hsx_phone_query\app\listener\notice;

use addon\hsx_phone_query\app\model\HsxPhoneQueryOrder;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 查询失败通知模板数据
 */
class QueryFail extends BaseNoticeTemplate
{
    private string $key = 'hsx_phone_query_fail';

    public function handle(array $params)
    {
        if (($params['key'] ?? '') != $this->key) {
            return;
        }

        $order = (new HsxPhoneQueryOrder())->where('order_id', (int)$params['data']['order_id'])->findOrEmpty()->toArray();
        if (empty($order)) {
            return;
        }

        $wapDomain = get_wap_domain($order['site_id']);
        $page = 'addon/hsx_phone_query/pages/history';

        return $this->toReturn([
            '__wechat_page' => $wapDomain . '/' . $page,
            '__weapp_page' => $page,
            'order_no' => $order['order_no'],
            'result_text' => $this->getResultText($order),
            'service_name' => $order['service_name'],
            'query_count' => $order['query_count'],
            'fail_reason' => str_sub($params['data']['fail_reason'] ?? '查询失败'),
            'refund_text' => $this->getRefundText($order),
            'finish_time' => $this->getFinishTime($order),
            'url' => $wapDomain . '/' . $page,
        ], [
            'member_id' => $order['member_id'],
        ]);
    }

    private function getFinishTime(array $order): string
    {
        $finishTime = (int)($order['finish_time'] ?? 0);
        if ($finishTime <= 0) {
            $finishTime = time();
        }

        return date('Y-m-d H:i:s', $finishTime);
    }

    private function getResultText(array $order): string
    {
        return (int)($order['refund_status'] ?? 0) === 1 ? '查询失败，已退款' : '查询失败';
    }

    private function getRefundText(array $order): string
    {
        if ((int)($order['refund_status'] ?? 0) === 1) {
            return ($order['pay_type'] ?? '') === 'point' ? '积分已退还' : '已原路退款';
        }

        return ($order['pay_type'] ?? '') === 'point' ? '积分退还处理中' : '退款处理中';
    }
}
