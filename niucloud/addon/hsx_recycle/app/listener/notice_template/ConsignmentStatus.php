<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\notice_template;

use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 代卖进度通知
 */
class ConsignmentStatus extends BaseNoticeTemplate
{
    private string $key = 'recycle_consignment_status';

    public function handle(array $params)
    {
        if ($this->key !== ($params['key'] ?? '')) {
            return null;
        }

        $data = $params['data'] ?? [];
        $siteId = (int)($data['site_id'] ?? request()->siteid() ?? 0);
        $wapDomain = $siteId > 0 ? get_wap_domain($siteId) : '';
        $consignmentId = (int)($data['consignment_id'] ?? 0);
        $page = 'addon/hsx_recycle/pages/consignment/detail?id=' . $consignmentId;

        return $this->toReturn(
            [
                '__wechat_page' => $wapDomain ? ($wapDomain . '/' . $page) : '',
                '__weapp_page' => $page,
                'order_no' => $data['order_no'] ?? '',
                'source_order_no' => $data['source_order_no'] ?? '',
                'device_name' => $data['device_name'] ?? '',
                'device_imei' => $data['device_imei'] ?? '',
                'status' => $data['status'] ?? '',
                'time' => $data['time'] ?? date('Y-m-d H:i:s'),
                'remark' => $data['remark'] ?? '',
                'url' => '/mplink/a5f',
            ],
            [
                'member_id' => (int)($data['member_id'] ?? 0),
            ]
        );
    }
}
