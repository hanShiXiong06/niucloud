<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\listener\poster;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\service\core\ProjectCenterDistributionRuleService;
use addon\hsx_project_center\app\service\core\ProjectCenterInviteService;
use app\model\member\Member;
use app\service\core\sys\CoreSysConfigService;
use core\exception\CommonException;

/** 为框架统一海报服务提供项目、邀请人和二维码数据。 */
final class ProjectCenterDistributionPoster
{
    public function handle($data = []): array
    {
        $data = is_array($data) ? $data : [];
        if (($data['type'] ?? '') !== 'hsx_project_center_distribution') return [];

        $siteId = (int)($data['site_id'] ?? 0);
        $param = is_array($data['param'] ?? null) ? $data['param'] : [];
        $projectId = (int)($param['project_id'] ?? $param['id'] ?? 0);
        $credential = trim((string)($param['invite'] ?? $param['invite_token'] ?? $param['share_code'] ?? ''));
        $domain = (string)((new CoreSysConfigService())->getSceneDomain($siteId)['wap_url'] ?? '');

        if (($param['mode'] ?? '') === 'preview') {
            return $this->payload(
                $domain,
                1,
                'c_1_1_0123456789',
                '项目合作邀请',
                '了解项目规则、参与条件与资料办理流程',
                'addon/hsx_project_center/cover.png',
                '项目推广人：会员昵称',
                '合作伙伴 · 一级佣金系数 100%'
            );
        }

        if ($siteId <= 0 || $projectId <= 0 || $credential === '') {
            throw new CommonException('项目推广海报参数不完整，请重新打开分享入口');
        }

        $project = ProjectCenterProject::where([
            ['site_id', '=', $siteId], ['id', '=', $projectId],
            ['status', '=', ProjectCenterDict::PROJECT_ENABLED], ['distribution_enabled', '=', 1],
        ])->findOrEmpty();
        if ($project->isEmpty()) throw new CommonException('该项目当前未开放邀请推广');

        $invite = (new ProjectCenterInviteService())->findActive($siteId, $projectId, $credential);
        $inviterId = (int)$invite->inviter_member_id;
        $capability = (new ProjectCenterDistributionRuleService())->memberCapability($siteId, $inviterId, 1, $project);
        if (empty($capability['eligible'])) {
            throw new CommonException('当前会员推广资格不可用：' . (string)($capability['reason'] ?? '请联系管理员'));
        }

        $member = Member::where([
            ['site_id', '=', $siteId], ['member_id', '=', $inviterId], ['status', '=', 1],
        ])->field('nickname')->findOrEmpty();
        if ($member->isEmpty()) throw new CommonException('分享会员不存在或已停用');

        $nickname = $this->limit((string)$member->nickname, 12);
        $cover = trim((string)$project->cover) ?: 'addon/hsx_project_center/cover.png';
        return $this->payload(
            $domain,
            $projectId,
            $credential,
            $this->limit((string)$project->title, 22),
            $this->limit((string)$project->subtitle, 36) ?: '查看项目详情、参与条件与资料办理流程',
            $cover,
            '项目推广人：' . ($nickname ?: '合作伙伴'),
            (string)($capability['level_name'] ?? '合作伙伴')
                . ' · 一级佣金系数 ' . $this->number((float)($capability['coefficient'] ?? 100)) . '%'
        );
    }

    private function payload(
        string $domain,
        int $projectId,
        string $credential,
        string $title,
        string $subtitle,
        string $cover,
        string $inviterText,
        string $levelText
    ): array {
        return [
            'project_title' => $title,
            'project_subtitle' => $subtitle,
            'project_cover' => $cover,
            'inviter_text' => $inviterText,
            'level_text' => $levelText,
            'scan_text' => '长按识别二维码，查看项目详情',
            'footer_text' => '项目说明、参与条件及实际办理结果以项目页面为准',
            'url' => [
                'url' => $domain,
                'page' => 'addon/hsx_project_center/pages/project/detail',
                // 微信小程序码 scene 最长 32 字符，只传短签名凭证；页面可从凭证解析项目 ID。
                'data' => [['key' => 'i', 'value' => $credential]],
            ],
        ];
    }

    private function limit(string $value, int $length): string
    {
        $value = trim($value);
        return mb_strlen($value, 'UTF-8') > $length
            ? mb_substr($value, 0, $length - 1, 'UTF-8') . '…'
            : $value;
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
