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
            'service_name' => $order['service_name'],
            'query_count' => $order['query_count'],
            'fail_reason' => str_sub($params['data']['fail_reason'] ?? '查询失败'),
            'finish_time' => empty($order['finish_time']) ? '' : date('Y-m-d H:i:s', (int)$order['finish_time']),
            'url' => $wapDomain . '/' . $page,
        ], [
            'member_id' => $order['member_id'],
        ]);
    }
}
