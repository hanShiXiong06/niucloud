<?php

namespace addon\phone_shop\app\listener\notice_template;

use addon\phone_shop\app\model\member\ForwardApplication;
use addon\phone_shop\app\service\core\member\ForwardApplicationNoticeService;
use app\listener\notice_template\BaseNoticeTemplate;

/** 同行转发权限申请审核结果通知数据。 */
class ForwardApplicationResult extends BaseNoticeTemplate
{
    public function handle(array $params)
    {
        $key = (string)($params['key'] ?? '');
        if (!in_array($key, [ForwardApplicationNoticeService::APPROVED, ForwardApplicationNoticeService::REJECTED], true)) return;
        $id = (int)($params['data']['application_id'] ?? 0);
        $row = ForwardApplication::where('application_id', '=', $id)->findOrEmpty()->toArray();
        if (!$row) return;
        $approved = $key === ForwardApplicationNoticeService::APPROVED;
        $page = 'addon/phone_shop/pages/goods/category';
        $url = get_wap_domain((int)$row['site_id']) . '/' . $page;
        return $this->toReturn([
            '__wechat_page' => $url,
            '__weapp_page' => $page,
            'level_name' => (string)($row['target_level_name'] ?? '同行会员'),
            'review_result' => $approved ? '审核通过，同行商品转发功能已开通' : '审核未通过',
            'review_reason' => (string)($row['review_reason'] ?: '无'),
            'review_time' => date('Y-m-d H:i:s', (int)($row['reviewed_at'] ?? 0) ?: time()),
            'url' => $url,
        ], ['member_id' => (int)$row['member_id']]);
    }
}
