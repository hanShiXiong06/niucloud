<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\listener\notice_template;

use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterRefund;
use app\listener\notice_template\BaseNoticeTemplate;

final class ApplicationResult extends BaseNoticeTemplate
{
    public function handle(array $params)
    {
        $key = (string)($params['key'] ?? '');
        if ($key === 'project_center_refund_completed') return $this->refundCompleted($params);
        if (!in_array($key, ['project_center_application_approved', 'project_center_application_rejected'], true)) return;
        $id = (int)($params['data']['application_id'] ?? 0);
        $row = ProjectCenterApplication::where('id', '=', $id)->findOrEmpty()->toArray();
        if ($row === []) return;
        $siteId = (int)$row['site_id'];
        $project = ProjectCenterProject::where([['site_id', '=', $siteId], ['id', '=', (int)$row['project_id']]])
            ->field('title')->findOrEmpty()->toArray();
        $group = ProjectCenterGroup::where([['site_id', '=', $siteId], ['id', '=', (int)$row['group_id']]])
            ->field('group_no')->findOrEmpty()->toArray();
        $approved = $key === 'project_center_application_approved';
        $page = 'addon/hsx_project_center/pages/project/detail?id=' . (int)$row['project_id'] . '&group_no=' . urlencode((string)($group['group_no'] ?? ''));
        $url = get_wap_domain($siteId) . '/' . $page;
        return $this->toReturn([
            '__wechat_page' => $url, '__weapp_page' => $page,
            'project_name' => (string)($project['title'] ?? '合作项目'),
            'group_no' => (string)($group['group_no'] ?? '-'),
            'review_result' => $approved ? '资料审核通过' : '资料需修改',
            'review_reason' => $approved ? '资料已通过，请关注群内后续安排' : ((string)$row['last_reject_summary'] ?: '请重新扫码查看并修改资料'),
            'review_time' => date('Y-m-d H:i:s', (int)$row['reviewed_at'] ?: time()),
            'url' => $url,
        ], ['member_id' => (int)$row['member_id']]);
    }

    private function refundCompleted(array $params)
    {
        $id = (int)($params['data']['refund_id'] ?? 0);
        $refund = ProjectCenterRefund::where('id', '=', $id)->findOrEmpty()->toArray();
        if ($refund === [] || (string)$refund['status'] !== 'refunded') return;
        $siteId = (int)$refund['site_id'];
        $project = ProjectCenterProject::where([['site_id', '=', $siteId], ['id', '=', (int)$refund['project_id']]])
            ->field('title')->findOrEmpty()->toArray();
        $group = ProjectCenterGroup::where([['site_id', '=', $siteId], ['id', '=', (int)$refund['group_id']]])
            ->field('group_no')->findOrEmpty()->toArray();
        $page = 'addon/hsx_project_center/pages/project/detail?id=' . (int)$refund['project_id'] . '&group_no=' . urlencode((string)($group['group_no'] ?? ''));
        $url = get_wap_domain($siteId) . '/' . $page;
        return $this->toReturn([
            '__wechat_page' => $url, '__weapp_page' => $page,
            'project_name' => (string)($project['title'] ?? '合作项目'),
            'group_no' => (string)($group['group_no'] ?? '-'),
            'refund_amount' => number_format((float)$refund['amount'], 2, '.', ''),
            'refund_result' => '退款已完成',
            'refund_time' => date('Y-m-d H:i:s', (int)$refund['refunded_at'] ?: time()),
            'refund_reason' => (string)($refund['remark'] ?: $refund['reason'] ?: '款项已按约定退回'),
            'url' => $url,
        ], ['member_id' => (int)$refund['member_id']]);
    }
}
