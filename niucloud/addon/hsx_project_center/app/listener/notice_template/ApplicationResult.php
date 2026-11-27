<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\listener\notice_template;

use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterRefund;
use addon\hsx_project_center\app\model\ProjectCenterDistributionDetail;
use app\listener\notice_template\BaseNoticeTemplate;

final class ApplicationResult extends BaseNoticeTemplate
{
    public function handle(array $params)
    {
        $key = (string)($params['key'] ?? '');
        if (in_array($key, ['project_center_distribution_pending', 'project_center_distribution_settled', 'project_center_distribution_reversed'], true)) {
            return $this->distributionChanged($params, $key);
        }
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

    private function distributionChanged(array $params, string $key)
    {
        $id = (int)($params['data']['detail_id'] ?? 0);
        $detail = ProjectCenterDistributionDetail::where('id', '=', $id)->findOrEmpty()->toArray();
        if ($detail === []) return;
        $siteId = (int)$detail['site_id'];
        $project = ProjectCenterProject::where([['site_id', '=', $siteId], ['id', '=', (int)$detail['project_id']]])
            ->field('title')->findOrEmpty()->toArray();
        $page = 'addon/hsx_project_center/pages/distribution/index?project_id=' . (int)$detail['project_id'];
        $url = get_wap_domain($siteId) . '/' . $page;
        $pending = $key === 'project_center_distribution_pending';
        $settled = $key === 'project_center_distribution_settled';
        $changeAmount = max(0, (float)($params['data']['change_amount'] ?? 0));
        $amount = $pending ? (float)$detail['commission_amount'] : ($settled
            ? (float)$detail['settled_amount']
            : ($changeAmount > 0 ? $changeAmount : (float)$detail['reversed_amount']));
        $statusText = $pending ? '已生成，保护期后自动结算' : ($settled
            ? '已计入可用佣金账户'
            : ((float)$detail['debt_amount'] > 0
                ? '已冲红，余额不足部分待抵扣'
                : '客户退款，原佣金已冲红'));
        return $this->toReturn([
            '__wechat_page' => $url, '__weapp_page' => $page,
            // 小程序订阅消息 thing 字段有长度上限，在数据源统一收口，
            // 避免真实项目名过长时整条通知发送失败。
            'project_name' => mb_substr((string)($project['title'] ?? '合作项目'), 0, 15),
            'relation_level' => (int)$detail['relation_level'] === 1 ? '一级佣金' : '二级佣金',
            'commission_amount' => number_format($amount, 2, '.', ''),
            'status_text' => mb_substr($statusText, 0, 15),
            'change_time' => date('Y-m-d H:i:s'), 'url' => $url,
        ], ['member_id' => (int)$detail['beneficiary_member_id']]);
    }
}
