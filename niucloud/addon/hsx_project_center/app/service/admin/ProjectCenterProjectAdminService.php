<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\admin;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\service\core\ProjectCenterAreaEligibilityService;
use addon\hsx_project_center\app\service\core\ProjectCenterContactGuideService;
use addon\hsx_project_center\app\service\core\ProjectCenterDistributionRuleService;
use app\model\diy\Diy;
use app\model\diy_form\DiyForm;
use app\model\member\MemberLevel;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class ProjectCenterProjectAdminService extends BaseAdminService
{
    private const DEFAULT_REVIEW_REASONS = [
        [
            'id' => 'default_image_unclear',
            'title' => '图片不清晰或不完整',
            'message' => '上传的图片不清晰或内容不完整，暂时无法核验。',
            'example' => '请使用原相机重新拍摄，保证画面清晰、完整，不要裁切或遮挡关键信息。',
            'sort' => 100,
        ],
        [
            'id' => 'default_missing_content',
            'title' => '资料缺失',
            'message' => '该项资料缺失或未按要求填写完整。',
            'example' => '请参考页面中的正确示例，补充完整内容后重新提交。',
            'sort' => 90,
        ],
        [
            'id' => 'default_content_mismatch',
            'title' => '信息不一致',
            'message' => '填写内容与上传的证明材料不一致，请重新核对。',
            'example' => '请以真实有效的证明材料为准，修改填写内容或重新上传对应材料。',
            'sort' => 80,
        ],
    ];

    public function page(array $where): array
    {
        $query = ProjectCenterProject::where('site_id', '=', $this->site_id);
        if (($where['status'] ?? '') !== '') $query->where('status', '=', (int)$where['status']);
        if (trim((string)($where['keyword'] ?? '')) !== '') $query->whereLike('title|project_no', '%' . trim((string)$where['keyword']) . '%');
        $page = $query->order('sort desc,id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))), 'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        $rows = (array)($page[$key] ?? []);
        foreach ($rows as &$row) {
            $row['status_name'] = ProjectCenterDict::projectStatuses()[(int)$row['status']] ?? '';
            $row['application_count'] = ProjectCenterApplication::where([['site_id', '=', $this->site_id], ['project_id', '=', (int)$row['id']]])->count();
            $row['distribution_summary'] = (new ProjectCenterDistributionRuleService())->publicSummary($row);
            $row['area_eligibility_summary'] = (new ProjectCenterAreaEligibilityService())->publicSummary($row);
        }
        unset($row);
        $page[$key] = $rows;
        return $page;
    }

    public function info(int $id): array
    {
        $row = $this->find($id)->toArray();
        $row['distribution_summary'] = (new ProjectCenterDistributionRuleService())->publicSummary($row);
        $row['area_eligibility_summary'] = (new ProjectCenterAreaEligibilityService())->publicSummary($row);
        return $row;
    }

    public function save(array $data, int $id = 0): int
    {
        $payload = $this->validateData($data);
        $now = time();
        if ($id > 0) {
            $row = $this->find($id);
            // 项目编辑器只负责基础介绍、步骤和问答，不能覆盖审核原因等扩展配置。
            $oldContact = (new ProjectCenterContactGuideService())->normalize(
                (array)(((array)$row->config_json)['contact_guide'] ?? [])
            );
            $newContact = (array)($payload['config_json']['contact_guide'] ?? []);
            if ($oldContact['mode'] === ($newContact['mode'] ?? '')
                && $oldContact['staff_uids'] === ($newContact['staff_uids'] ?? [])) {
                foreach (['generated_hash', 'generated_config_id', 'generated_qrcode'] as $field) {
                    $newContact[$field] = $oldContact[$field];
                }
                $payload['config_json']['contact_guide'] = $newContact;
            }
            $payload['config_json'] = array_merge((array)$row->config_json, (array)$payload['config_json']);
            $oldRule = (new ProjectCenterDistributionRuleService())->projectRule($row);
            $payload['config_json']['distribution']['enabled_at'] = !empty($payload['distribution_enabled'])
                ? (!empty($row->distribution_enabled) && (int)$oldRule['enabled_at'] > 0 ? (int)$oldRule['enabled_at'] : $now)
                : 0;
            $areaService = new ProjectCenterAreaEligibilityService();
            $oldAreaRule = $areaService->projectConfig($row);
            $payload['config_json']['area_eligibility']['enabled_at'] = !empty($payload['config_json']['area_eligibility']['enabled'])
                ? (!empty($oldAreaRule['enabled']) && (int)$oldAreaRule['enabled_at'] > 0 ? (int)$oldAreaRule['enabled_at'] : $now)
                : 0;
            $row->save(array_merge($payload, ['update_at' => $now]));
            return $id;
        }
        $payload['config_json']['distribution']['enabled_at'] = !empty($payload['distribution_enabled']) ? $now : 0;
        $payload['config_json']['area_eligibility']['enabled_at'] = !empty($payload['config_json']['area_eligibility']['enabled']) ? $now : 0;
        $row = ProjectCenterProject::create(array_merge($payload, [
            'site_id' => $this->site_id, 'project_no' => create_no('PJ'), 'create_at' => $now, 'update_at' => $now,
        ]));
        return (int)$row->id;
    }

    public function delete(int $id): bool
    {
        $row = $this->find($id);
        if (ProjectCenterApplication::where([['site_id', '=', $this->site_id], ['project_id', '=', $id]])->count() > 0) {
            throw new CommonException('项目已有客户资料，不能删除，可停用项目');
        }
        return (bool)$row->delete();
    }

    public function metadata(): array
    {
        $roles = SysUserRole::where([['site_id', '=', $this->site_id], ['status', '=', 1]])->field('uid')->select()->toArray();
        $uids = array_values(array_unique(array_filter(array_map('intval', array_column($roles, 'uid')))));
        $users = $uids === [] ? [] : SysUser::whereIn('uid', $uids)->where('status', '=', 1)
            ->field('uid,username,real_name,mobile')->select()->toArray();
        foreach ($users as &$user) $user['name'] = (string)($user['real_name'] ?: $user['username'] ?: ('员工' . $user['uid']));
        unset($user);
        $forms = DiyForm::where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->field('form_id,title')->order('form_id desc')->select()->toArray();
        foreach ($forms as &$form) {
            // 对外统一为 form_name，避免管理端依赖框架万能表单的内部字段名。
            $form['form_name'] = (string)($form['title'] ?? '');
        }
        unset($form);
        $diyPages = Diy::where([
            ['site_id', '=', $this->site_id], ['type', '=', 'DIY_PAGE'], ['mode', '=', 'diy'],
        ])->field('id,page_title,title,type,mode,update_time')->order('update_time desc,id desc')->select()->toArray();
        foreach ($diyPages as &$page) {
            $page['page_name'] = (string)($page['page_title'] ?: $page['title'] ?: ('微页面 #' . $page['id']));
        }
        unset($page);
        $levels = MemberLevel::where('site_id', '=', $this->site_id)
            ->field('level_id,level_name,level_benefits')->order('growth asc,level_id asc')->select()->toArray();
        foreach ($levels as &$level) {
            $benefits = is_array($level['level_benefits'] ?? null) ? $level['level_benefits'] : [];
            $benefit = is_array($benefits[ProjectCenterDistributionRuleService::BENEFIT_KEY] ?? null)
                ? $benefits[ProjectCenterDistributionRuleService::BENEFIT_KEY] : [];
            $level['distribution_enabled'] = (int)!empty($benefit['is_use']);
            $level['first_coefficient'] = (float)($benefit['first_coefficient'] ?? 100);
            $level['second_enabled'] = (int)!empty($benefit['second_enabled']);
            $level['second_coefficient'] = (float)($benefit['second_coefficient'] ?? 100);
            unset($level['level_benefits']);
        }
        unset($level);
        return [
            'project_statuses' => ProjectCenterDict::projectStatuses(),
            'reviewers' => $users,
            'forms' => $forms,
            'diy_pages' => $diyPages,
            'member_levels' => $levels,
        ];
    }

    public function reviewReasons(int $projectId): array
    {
        $project = $this->find($projectId);
        $config = (array)$project->config_json;
        $reasons = isset($config['review_reasons']) && is_array($config['review_reasons'])
            ? $this->normalizeReviewReasons($config['review_reasons'])
            : self::DEFAULT_REVIEW_REASONS;
        usort($reasons, static fn(array $a, array $b) => ((int)$b['sort'] <=> (int)$a['sort']));
        return array_values($reasons);
    }

    public function saveReviewReasons(int $projectId, array $reasons): array
    {
        $project = $this->find($projectId);
        $normalized = $this->normalizeReviewReasons($reasons);
        if (count($normalized) > 50) throw new CommonException('常用问题最多配置 50 条');
        $config = (array)$project->config_json;
        $config['review_reasons'] = $normalized;
        $project->save(['config_json' => $config, 'update_at' => time()]);
        return $this->reviewReasons($projectId);
    }

    private function find(int $id): ProjectCenterProject
    {
        $row = ProjectCenterProject::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('项目不存在');
        return $row;
    }

    private function validateData(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') throw new CommonException('请输入项目名称');
        $status = (int)($data['status'] ?? 0);
        if (!array_key_exists($status, ProjectCenterDict::projectStatuses())) throw new CommonException('项目状态不正确');
        $formId = max(0, (int)($data['form_id'] ?? 0));
        if ($status === ProjectCenterDict::PROJECT_ENABLED && $formId <= 0) throw new CommonException('启用项目前请配置资料表单');
        $paymentQrcode = trim((string)($data['payment_qrcode'] ?? ''));
        if ($status === ProjectCenterDict::PROJECT_ENABLED && $paymentQrcode === '') {
            throw new CommonException('启用项目前请配置客户付款码');
        }
        $reviewers = array_values(array_unique(array_filter(array_map('intval', (array)($data['reviewer_uids'] ?? [])))));
        if ($status === ProjectCenterDict::PROJECT_ENABLED && $reviewers === []) throw new CommonException('启用项目前请配置至少一名资料审核员');
        if ($reviewers !== []) {
            $roleUids = array_values(array_unique(array_map('intval', SysUserRole::where([
                ['site_id', '=', $this->site_id], ['status', '=', 1], ['uid', 'in', $reviewers],
            ])->column('uid'))));
            $activeUids = $roleUids === [] ? [] : array_values(array_unique(array_map('intval', SysUser::where([
                ['status', '=', 1], ['uid', 'in', $roleUids],
            ])->column('uid'))));
            $invalidUids = array_values(array_diff($reviewers, $activeUids));
            if ($invalidUids !== []) throw new CommonException('资料审核员不属于当前站点或已停用，请重新选择');
        }
        $introPageId = max(0, (int)($data['intro_page_id'] ?? 0));
        if ($introPageId > 0) {
            $exists = Diy::where([
                ['site_id', '=', $this->site_id], ['id', '=', $introPageId],
                ['type', '=', 'DIY_PAGE'], ['mode', '=', 'diy'],
            ])->count();
            if ($exists <= 0) throw new CommonException('项目微页面不存在或不属于当前站点');
        }
        $config = $this->normalizeProjectConfig((array)($data['config_json'] ?? []));
        if (!empty($config['contact_guide']['enabled']) && trim((string)$config['contact_guide']['fallback_qrcode']) === '') {
            throw new CommonException('开启陌生客户联系流程后，请上传备用企微二维码');
        }
        $contactUids = (array)($config['contact_guide']['staff_uids'] ?? []);
        if (!empty($config['contact_guide']['enabled']) && $config['contact_guide']['mode'] === 'wecom_first' && $contactUids === []) {
            throw new CommonException('企微优先模式请至少选择一名项目顾问');
        }
        if ($contactUids !== []) {
            $contactSiteUids = array_values(array_unique(array_map('intval', SysUserRole::where([
                ['site_id', '=', $this->site_id], ['status', '=', 1], ['uid', 'in', $contactUids],
            ])->column('uid'))));
            if (array_values(array_diff($contactUids, $contactSiteUids)) !== []) {
                throw new CommonException('项目顾问不属于当前站点或已停用，请重新选择');
            }
        }
        $config['area_eligibility'] = (new ProjectCenterAreaEligibilityService())->validateProjectConfig(
            (array)($config['area_eligibility'] ?? [])
        );
        $distributionEnabled = (int)!empty($data['distribution_enabled']);
        $paymentAmount = max(0, round((float)($data['payment_amount'] ?? 0), 2));
        if ($distributionEnabled) {
            // 发佣是资金动作，服务端固定要求人工核对群内真实流水，不能被绕过前端直接关闭。
            $config['distribution']['approval_requires_payment_check'] = 1;
            (new ProjectCenterDistributionRuleService())->validateEnabledRule((array)$config['distribution']);
            if (($config['distribution']['commission_type'] ?? '') === 'ratio' && $paymentAmount <= 0) {
                throw new CommonException('按付款比例分佣时，项目付款金额必须大于 0');
            }
        }
        return [
            'title' => mb_substr($title, 0, 120), 'subtitle' => mb_substr(trim((string)($data['subtitle'] ?? '')), 0, 255),
            'cover' => trim((string)($data['cover'] ?? '')), 'status' => $status, 'sort' => (int)($data['sort'] ?? 0),
            'intro_page_id' => $introPageId, 'form_id' => $formId,
            'payment_qrcode' => $paymentQrcode,
            'payment_amount' => $paymentAmount,
            'payment_tips' => mb_substr(trim((string)($data['payment_tips'] ?? '')), 0, 1000),
            'reviewer_uids' => $reviewers, 'reviewer_role_ids' => [],
            'ai_enabled' => (int)!empty($data['ai_enabled']), 'ai_scene' => mb_substr(trim((string)($data['ai_scene'] ?? '')), 0, 80),
            'distribution_enabled' => $distributionEnabled,
            'config_json' => $config,
        ];
    }

    private function normalizeProjectConfig(array $config): array
    {
        $steps = array_slice(array_values(array_filter(array_map(
            static fn($item): string => mb_substr(trim((string)$item), 0, 500),
            (array)($config['steps'] ?? [])
        ))), 0, 30);
        $faqs = [];
        foreach (array_slice((array)($config['faqs'] ?? []), 0, 50) as $faq) {
            if (!is_array($faq)) continue;
            $question = mb_substr(trim((string)($faq['question'] ?? '')), 0, 200);
            $answer = mb_substr(trim((string)($faq['answer'] ?? '')), 0, 2000);
            if ($question !== '' && $answer !== '') $faqs[] = ['question' => $question, 'answer' => $answer];
        }
        $suggestions = array_slice(array_values(array_unique(array_filter(array_map(
            static fn($item): string => mb_substr(trim((string)$item), 0, 60),
            (array)($config['ai_suggestions'] ?? [])
        )))), 0, 8);
        return [
            'intro' => mb_substr(trim((string)($config['intro'] ?? '')), 0, 10000),
            'steps' => $steps,
            'faqs' => $faqs,
            'ai_title' => mb_substr(trim((string)($config['ai_title'] ?? '')), 0, 30),
            'ai_welcome' => mb_substr(trim((string)($config['ai_welcome'] ?? '')), 0, 300),
            'ai_suggestions' => $suggestions,
            'ai_knowledge' => mb_substr(trim((string)($config['ai_knowledge'] ?? '')), 0, 30000),
            'ai_voice_enabled' => (int)($config['ai_voice_enabled'] ?? 1) === 1 ? 1 : 0,
            'ai_auto_read' => !empty($config['ai_auto_read']) ? 1 : 0,
            'distribution' => (new ProjectCenterDistributionRuleService())->normalizeProjectRule(
                (array)($config['distribution'] ?? [])
            ),
            'area_eligibility' => (new ProjectCenterAreaEligibilityService())->normalizeProjectConfig(
                (array)($config['area_eligibility'] ?? [])
            ),
            'contact_guide' => (new ProjectCenterContactGuideService())->normalize(
                (array)($config['contact_guide'] ?? [])
            ),
        ];
    }

    private function normalizeReviewReasons(array $reasons): array
    {
        $result = [];
        $messages = [];
        foreach ($reasons as $index => $reason) {
            if (!is_array($reason)) continue;
            $message = trim((string)($reason['message'] ?? ''));
            if ($message === '') continue;
            $message = mb_substr($message, 0, 500);
            if (isset($messages[$message])) continue;
            $messages[$message] = true;
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string)($reason['id'] ?? '')));
            if ($id === '') $id = 'reason_' . substr(sha1($message . '|' . microtime(true) . '|' . $index), 0, 16);
            $title = trim((string)($reason['title'] ?? ''));
            if ($title === '') $title = mb_substr($message, 0, 20);
            $result[] = [
                'id' => mb_substr($id, 0, 50),
                'title' => mb_substr($title, 0, 50),
                'message' => $message,
                'example' => mb_substr(trim((string)($reason['example'] ?? '')), 0, 500),
                'sort' => (int)($reason['sort'] ?? (100 - $index)),
            ];
        }
        return array_values($result);
    }
}
