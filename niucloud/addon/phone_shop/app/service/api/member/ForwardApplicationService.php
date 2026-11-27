<?php

namespace addon\phone_shop\app\service\api\member;

use addon\phone_shop\app\model\member\ForwardApplication;
use addon\phone_shop\app\service\core\member\ForwardBenefitService;
use addon\phone_shop\app\service\core\member\ForwardApplicationSchemaService;
use app\model\diy_form\DiyFormRecords;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\ApiException;
use think\facade\Log;

/** 用户侧同行商品转发权限。 */
class ForwardApplicationService extends BaseApiService
{
    public function access(): array
    {
        ForwardApplicationSchemaService::ensure();
        $member = Member::where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
        ])->field('member_id,member_level,nickname,username,mobile')->findOrEmpty()->toArray();
        if (!$member) throw new ApiException('请先登录');

        $benefit = new ForwardBenefitService();
        $current = $benefit->getLevelConfig($this->site_id, (int)$member['member_level']);
        if ((int)($current['config']['is_use'] ?? 0) === 1) {
            return [
                'allowed' => 1,
                'status' => 'approved',
                'level_name' => (string)($current['level_name'] ?? ''),
                'message' => '您已拥有同行商品转发权益',
            ];
        }

        $target = $benefit->getApplicationTarget($this->site_id);
        $latest = ForwardApplication::where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
        ])->order('application_id desc')->findOrEmpty()->append(['status_name'])->toArray();

        return [
            'allowed' => 0,
            'status' => (string)($latest['status'] ?? 'not_applied'),
            'status_name' => (string)($latest['status_name'] ?? '未申请'),
            'application_id' => (int)($latest['application_id'] ?? 0),
            'review_reason' => (string)($latest['review_reason'] ?? ''),
            'can_apply' => $target && (int)($target['config']['form_id'] ?? 0) > 0 && (int)($target['config']['reviewer_uid'] ?? 0) > 0 ? 1 : 0,
            'form_id' => (int)($target['config']['form_id'] ?? 0),
            'target_level_name' => (string)($target['level_name'] ?? ''),
            'reviewer_name' => (string)($target['config']['reviewer_name'] ?? ''),
            'message' => ($latest['status'] ?? '') === 'pending'
                ? '您的同行身份正在审核，审核通过后即可使用'
                : '该功能仅对拥有同行转发权益的会员开放',
        ];
    }

    public function apply(int $formRecordId, string $message = ''): int
    {
        $access = $this->access();
        if ((int)$access['allowed'] === 1) throw new ApiException('您已拥有同行商品转发权益');
        if (($access['status'] ?? '') === 'pending') return (int)$access['application_id'];

        $target = (new ForwardBenefitService())->getApplicationTarget($this->site_id);
        if (!$target || (int)($target['config']['allow_apply'] ?? 0) !== 1) throw new ApiException('商家暂未开放同行身份申请');
        $formId = (int)($target['config']['form_id'] ?? 0);
        $reviewerUid = (int)($target['config']['reviewer_uid'] ?? 0);
        if ($formId <= 0 || $reviewerUid <= 0) throw new ApiException('同行身份申请尚未配置完整，请联系商家');

        $record = DiyFormRecords::where([
            ['record_id', '=', $formRecordId],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['form_id', '=', $formId],
        ])->field('record_id')->findOrEmpty();
        if ($record->isEmpty()) throw new ApiException('申请资料不存在，请重新填写');

        $now = time();
        $application = ForwardApplication::create([
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'target_level_id' => (int)$target['level_id'],
            'target_level_name' => (string)$target['level_name'],
            'form_id' => $formId,
            'form_record_id' => $formRecordId,
            'reviewer_uid' => $reviewerUid,
            'reviewer_name' => (string)($target['config']['reviewer_name'] ?? ''),
            'status' => 'pending',
            'apply_message' => mb_substr(trim($message), 0, 500),
            'create_time' => $now,
            'update_time' => $now,
        ]);
        $id = (int)$application->application_id;
        $this->publishTask($id, $target, $reviewerUid);
        return $id;
    }

    private function publishTask(int $applicationId, array $target, int $reviewerUid): void
    {
        try {
            $member = Member::where([
                ['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id],
            ])->field('nickname,username,mobile')->findOrEmpty()->toArray();
            $name = (string)($member['nickname'] ?: $member['username'] ?: $member['mobile'] ?: ('会员#' . $this->member_id));
            $params = ['application_id' => $applicationId, 'status' => 'pending'];
            $query = '?' . http_build_query($params);
            event('HsxBusinessTaskAssigned', [
                'event_id' => 'phone-shop-forward-' . $this->site_id . '-' . $applicationId,
                'event_name' => 'task.assigned.v1',
                'site_id' => $this->site_id,
                'source_plugin' => 'phone_shop',
                'source_type' => 'forward_application',
                'source_id' => $applicationId,
                'stage_key' => 'forward_application_review',
                'assignee_uid' => $reviewerUid,
                'assignee_name' => (string)($target['config']['reviewer_name'] ?? ''),
                'assigner_uid' => 0,
                'assigner_name' => $name,
                'title' => $name . '申请“' . (string)$target['level_name'] . '”同行转发权益',
                'pending_count' => (int)ForwardApplication::where([
                    ['site_id', '=', $this->site_id], ['reviewer_uid', '=', $reviewerUid], ['status', '=', 'pending'],
                ])->count(),
                'target' => [
                    'plugin' => 'phone_shop',
                    'route_key' => 'phone_shop.forward.application',
                    'params' => $params,
                    'web_path' => 'site/phone_shop/forward/application' . $query,
                ],
                'target_path' => 'site/phone_shop/forward/application' . $query,
                'occurred_at' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[phone_shop] 同行转发申请企微通知失败', ['application_id' => $applicationId, 'message' => $e->getMessage()]);
        }
    }
}
