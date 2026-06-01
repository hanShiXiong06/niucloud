<?php

namespace addon\hsx_phone_query\app\listener\notice;

use addon\hsx_phone_query\app\model\HsxPhoneQueryOrder;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 查询成功通知模板数据
 */
class QuerySuccess extends BaseNoticeTemplate
{
    private string $key = 'hsx_phone_query_success';

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
            'success_count' => (int)($order['success_count'] ?? 0),
            'fail_count' => (int)($order['fail_count'] ?? 0),
            'pay_text' => $this->getPayText($order),
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
        $successCount = (int)($order['success_count'] ?? 0);
        $failCount = (int)($order['fail_count'] ?? 0);

        return $failCount > 0 ? "成功{$successCount}条，失败{$failCount}条" : "成功{$successCount}条";
    }

    private function getPayText(array $order): string
    {
        if (($order['pay_type'] ?? '') === 'point') {
            return (int)($order['pay_point'] ?? 0) . '积分';
        }

        return '￥' . number_format((float)($order['pay_money'] ?? 0), 2);
    }

    private function getRefundText(array $order): string
    {
        if ((int)($order['fail_count'] ?? 0) <= 0) {
            return '查询报告已生成，请及时查看';
        }

        if ((int)($order['refund_status'] ?? 0) === 1) {
            return ($order['pay_type'] ?? '') === 'point' ? '失败部分积分已退还' : '失败部分已原路退款';
        }

        return ($order['pay_type'] ?? '') === 'point' ? '失败部分积分退还处理中' : '失败部分退款处理中';
    }
}
