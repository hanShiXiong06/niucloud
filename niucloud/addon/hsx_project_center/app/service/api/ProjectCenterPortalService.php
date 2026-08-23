<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\api;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterIncomeBoard;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterReviewLog;
use addon\hsx_project_center\app\service\core\ProjectCenterApplicationService;
use addon\hsx_project_center\app\service\core\ProjectCenterGroupService;
use app\model\diy\Diy;
use app\model\diy_form\DiyFormRecords;
use app\service\core\diy_form\CoreDiyFormRecordsService;
use core\base\BaseApiService;
use core\exception\ApiException;
use think\facade\Db;

final class ProjectCenterPortalService extends BaseApiService
{
    /**
     * 项目介绍页仅嵌入无交易、无表单提交、无固定底栏的展示组件。
     * 新组件必须经过项目场景验证后再显式加入，避免其他插件升级后自动扩大权限面。
     */
    private const INTRO_DIY_COMPONENT_ALLOWLIST = [
        'ProjectCenterCollapse', 'ProjectCenterReferenceGallery', 'ProjectCenterIncomeBoard',
        'Text', 'ImageAds', 'GraphicNav', 'RubikCube', 'HotArea', 'Notice', 'RichText',
        'ActiveCube', 'HorzBlank', 'HorzLine', 'PictureShow', 'AiAssistantEntry',
    ];

    private const INTRO_DIY_BLOCKED_POSITIONS = [
        'fixed', 'top_fixed', 'right_fixed', 'bottom_fixed', 'left_fixed',
    ];

    public function project(int $id): array
    {
        $row = ProjectCenterProject::where([
            ['site_id', '=', $this->site_id], ['id', '=', $id], ['status', '=', ProjectCenterDict::PROJECT_ENABLED],
        ])->findOrEmpty()->toArray();
        if ($row === []) throw new ApiException('项目不存在或暂未开放');
        $aiEnabled = !empty($row['ai_enabled']);
        // 客户进入项目页即可查看付款码；审核人和 AI 场景等管理配置仍不得对外下发。
        unset($row['reviewer_uids'], $row['reviewer_role_ids'], $row['ai_scene']);
        $config = is_array($row['config_json'] ?? null) ? $row['config_json'] : [];
        // 对外只下发页面展示内容；AI 私有知识、审核原因等配置始终留在服务端。
        $row['config_json'] = array_intersect_key($config, array_flip([
            'intro', 'steps', 'faqs', 'ai_title', 'ai_welcome', 'ai_suggestions', 'ai_voice_enabled', 'ai_auto_read',
        ]));
        $row['intro_diy'] = $this->loadIntroDiy((int)($row['intro_page_id'] ?? 0), $aiEnabled, $id);
        $latestDate = (int)ProjectCenterIncomeBoard::where([
            ['site_id', '=', $this->site_id], ['project_id', '=', $id], ['status', '=', 1],
        ])->max('stat_date');
        $row['income_date'] = $latestDate;
        $row['income_board'] = $latestDate > 0 ? ProjectCenterIncomeBoard::where([
            ['site_id', '=', $this->site_id], ['project_id', '=', $id], ['stat_date', '=', $latestDate], ['status', '=', 1],
        ])->field('rank_no,store_name,income_amount')->order('rank_no asc')->select()->toArray() : [];
        return $row;
    }

    /**
     * 进入资料填写前核验客户群编号与当前会员归属。
     * 正式提交资料时仍会再次校验，避免前端状态被绕过。
     */
    public function checkGroup(int $projectId, string $groupNo): array
    {
        $project = ProjectCenterProject::where([
            ['site_id', '=', $this->site_id], ['id', '=', $projectId],
            ['status', '=', ProjectCenterDict::PROJECT_ENABLED],
        ])->field('id')->findOrEmpty();
        if ($project->isEmpty()) throw new ApiException('项目不存在或暂未开放');

        $group = (new ProjectCenterGroupService())->requireAvailableByNo(
            (int)$this->site_id,
            $projectId,
            $groupNo,
            (int)$this->member_id
        );
        return [
            'group_no' => (string)$group->group_no,
            'group_id' => (int)$group->id,
            'verified' => 1,
        ];
    }

    /** 客户付款后确认群编号：已有台账则绑定本人，未登记则自动创建普通微信群台账。 */
    public function resolveGroup(int $projectId, string $groupNo): array
    {
        $group = (new ProjectCenterGroupService())->resolveForMember(
            (int)$this->site_id,
            $projectId,
            $groupNo,
            (int)$this->member_id
        );
        return [
            'group_no' => (string)$group->group_no,
            'group_no_full' => (string)$group->group_no_full,
            'group_id' => (int)$group->id,
            'verified' => 1,
            'binding_state' => 'bound',
            'next_action' => 'fill_form',
        ];
    }

    /**
     * 返回可嵌入项目页的微页面数据。
     * 仅允许本站 DIY_PAGE，并关闭嵌套后会重复出现的导航、弹窗和版权容器。
     */
    private function loadIntroDiy(int $pageId, bool $aiEnabled = false, int $projectId = 0): array
    {
        if ($pageId <= 0) return [];
        $page = Diy::where([
            ['site_id', '=', $this->site_id], ['id', '=', $pageId],
            ['type', '=', 'DIY_PAGE'], ['mode', '=', 'diy'],
        ])->field('id,page_title,title,type,mode,value')->findOrEmpty()->toArray();
        if ($page === []) return [];

        $source = $page['value'] ?? [];
        if (is_string($source)) {
            $source = json_decode($source, true);
        }
        if (!is_array($source)) return [];

        $global = is_array($source['global'] ?? null) ? $source['global'] : [];
        $global['topStatusBar'] = array_merge((array)($global['topStatusBar'] ?? []), ['isShow' => false]);
        $global['bottomTabBar'] = array_merge((array)($global['bottomTabBar'] ?? []), ['isShow' => false]);
        $global['copyright'] = array_merge((array)($global['copyright'] ?? []), ['isShow' => false]);
        $global['popWindow'] = array_merge((array)($global['popWindow'] ?? []), ['show' => false]);

        $components = [];
        foreach (array_slice((array)($source['value'] ?? []), 0, 120) as $component) {
            if (!is_array($component)) continue;
            $name = trim((string)($component['componentName'] ?? ''));
            if ($name === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,79}$/', $name)) continue;
            if (!in_array($name, self::INTRO_DIY_COMPONENT_ALLOWLIST, true)) continue;
            if ($name === 'AiAssistantEntry' && !$aiEnabled) continue;
            $position = strtolower(trim((string)($component['position'] ?? '')));
            if (in_array($position, self::INTRO_DIY_BLOCKED_POSITIONS, true)) continue;
            $component['componentName'] = $name;
            if ($name === 'AiAssistantEntry') {
                $component['assistantType'] = 'project_center';
                $component['assistantMode'] = 'popup';
                $component['projectId'] = $projectId;
                if (trim((string)($component['title'] ?? '')) === '' || (string)$component['title'] === 'AI 选机助手') {
                    $component['title'] = trim((string)($source['global']['title'] ?? '')) ?: 'AI 项目顾问';
                }
                if (trim((string)($component['subtitle'] ?? '')) === '' || str_contains((string)$component['subtitle'], '在售商品')) {
                    $component['subtitle'] = '项目规则、资料、审核与退款问题，随时问我';
                }
                $component['buttonText'] = trim((string)($component['buttonText'] ?? '')) ?: '立即咨询';
            }
            $components[] = $component;
        }
        if ($components === []) return [];

        return [
            'id' => (int)$page['id'],
            'title' => (string)($page['page_title'] ?: $page['title']),
            'global' => $global,
            'value' => $components,
        ];
    }

    public function submit(int $projectId, string $groupNo, int $formRecordId, bool $paymentDeclared): int
    {
        return (new ProjectCenterApplicationService())->submit(
            (int)$this->site_id, (int)$this->member_id, $projectId, $groupNo, $formRecordId, $paymentDeclared
        );
    }

    /**
     * 驳回修订专用入口。
     *
     * 只允许客户修改自己当前被驳回的原记录，不新建表单记录，
     * 因此不受“每人可填写次数”限制，也不会产生多张互相覆盖的工单。
     */
    public function revise(int $projectId, string $groupNo, array $value, bool $paymentDeclared): int
    {
        $group = (new ProjectCenterGroupService())->requireAvailableByNo(
            (int)$this->site_id,
            $projectId,
            $groupNo,
            (int)$this->member_id
        );
        $groupNo = (string)$group->group_no;
        $application = $this->ownApplication($projectId, $groupNo);
        if ($application === []) throw new ApiException('待修改的资料工单不存在');
        if ((string)$application['status'] !== ProjectCenterDict::APPLICATION_REJECTED) {
            throw new ApiException('只有已驳回的资料才能修改后重新提交');
        }
        if (!$paymentDeclared) throw new ApiException('请先确认已按项目说明完成付款，再修改资料');

        $record = DiyFormRecords::where([
            ['site_id', '=', $this->site_id],
            ['record_id', '=', (int)$application['form_record_id']],
            ['form_id', '=', (int)$application['form_id']],
            ['member_id', '=', $this->member_id],
        ])->findOrEmpty();
        if ($record->isEmpty()) throw new ApiException('原资料记录不存在，请联系工作人员');

        $applicationService = new ProjectCenterApplicationService();
        $applicationService->validateFormValue((int)$this->site_id, (int)$application['form_id'], $value);

        (new CoreDiyFormRecordsService())->edit([
            'record_id' => (int)$application['form_record_id'],
            'form_id' => (int)$application['form_id'],
            'site_id' => (int)$this->site_id,
            'member_id' => (int)$this->member_id,
            'value' => $value,
        ]);

        return $applicationService->submit(
            (int)$this->site_id,
            (int)$this->member_id,
            $projectId,
            $groupNo,
            (int)$application['form_record_id'],
            $paymentDeclared
        );
    }

    public function status(int $projectId, string $groupNo = ''): array
    {
        $row = $this->ownApplication($projectId, $groupNo);
        if ($row === []) {
            $group = (new ProjectCenterGroupService())->ownedGroupForStatus(
                (int)$this->site_id,
                $projectId,
                $groupNo,
                (int)$this->member_id
            );
            if (!$group->isEmpty() && in_array((string)$group->status, ['refund_pending', 'refunded', 'abandoned', 'dissolved', 'completed'], true)) {
                return [
                    'status' => (string)$group->status,
                    'status_name' => ProjectCenterDict::groupStatuses()[(string)$group->status] ?? (string)$group->status,
                    'group_no' => (string)$group->group_no,
                    'store_name' => (string)$group->store_name,
                    'legacy_direct' => 0,
                ];
            }
            return ['status' => 'not_submitted', 'status_name' => '未提交'];
        }
        $result = [
            'id' => (int)$row['id'],
            'application_no' => (string)$row['application_no'],
            'status' => (string)$row['status'],
            'status_name' => ProjectCenterDict::applicationStatuses()[(string)$row['status']] ?? (string)$row['status'],
            'submit_version' => (int)$row['submit_version'],
            'payment_declared_at' => (int)$row['payment_declared_at'],
            'last_reject_summary' => (string)$row['last_reject_summary'],
            'submitted_at' => (int)$row['submitted_at'],
            'reviewed_at' => (int)$row['reviewed_at'],
            'approved_at' => (int)$row['approved_at'],
            // 历史“二维码直达”工单只允许查看，新的资料提交必须绑定客户群。
            'legacy_direct' => (int)((int)$row['group_id'] === 0),
        ];
        $group = ProjectCenterGroup::where([['site_id', '=', $this->site_id], ['id', '=', (int)$row['group_id']]])->field('group_no,store_name')->findOrEmpty()->toArray();
        $result['group_no'] = (string)($group['group_no'] ?? '');
        $result['store_name'] = (string)($group['store_name'] ?? '');
        $latestReview = ProjectCenterReviewLog::where([['site_id', '=', $this->site_id], ['application_id', '=', (int)$row['id']]])->order('id desc')->findOrEmpty()->toArray();
        $result['field_issues'] = (array)($latestReview['field_issues_json'] ?? []);
        $result['review_remark'] = (string)($latestReview['remark'] ?? '');
        if ((string)$row['status'] === ProjectCenterDict::APPLICATION_REJECTED) {
            $record = DiyFormRecords::where([
                ['site_id', '=', $this->site_id],
                ['record_id', '=', (int)$row['form_record_id']],
                ['member_id', '=', $this->member_id],
                ['form_id', '=', (int)$row['form_id']],
            ])->field('record_id,value')->findOrEmpty()->toArray();
            $result['revision_record_id'] = (int)($record['record_id'] ?? 0);
            $result['revision_value'] = is_array($record['value'] ?? null) ? $record['value'] : [];
        }
        return $result;
    }

    private function ownApplication(int $projectId, string $groupNo): array
    {
        $query = ProjectCenterApplication::where([
            ['site_id', '=', $this->site_id], ['project_id', '=', $projectId], ['member_id', '=', $this->member_id],
        ]);
        $groupNo = trim($groupNo);
        if ($groupNo !== '') {
            $groupNo = (new ProjectCenterGroupService())->normalizeGroupNo($groupNo);
            $groupIds = ProjectCenterGroup::where([['site_id', '=', $this->site_id], ['project_id', '=', $projectId]])
                ->where(function ($q) use ($groupNo) {
                    $q->where('group_no', '=', $groupNo)->whereOr('group_no_full', '=', $groupNo);
                })->column('id');
            if ($groupIds === []) {
                // 管理员更正群编号后兼容客户旧二维码/旧缓存，定位到同一个不可变 group_id。
                $groupIds = array_values(array_unique(array_map('intval', Db::name('project_center_group_no_alias')->where([
                    ['site_id', '=', $this->site_id], ['project_id', '=', $projectId],
                ])->where(function ($q) use ($groupNo) {
                    $q->where('old_group_no', '=', $groupNo)->whereOr('old_group_no_full', '=', $groupNo);
                })->column('group_id'))));
            }
            if ($groupIds === []) return [];
            $query->whereIn('group_id', $groupIds);
        } else {
            $query->where('group_id', '=', 0);
        }
        return $query->order('id desc')->findOrEmpty()->toArray();
    }
}
