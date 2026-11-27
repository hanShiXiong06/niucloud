<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

use addon\hsx_project_center\app\model\ProjectCenterProject;
use app\model\member\Member;
use app\model\member\MemberLevel;
use core\exception\CommonException;

/** 项目基础佣金与推广准入规则的唯一解释器。 */
final class ProjectCenterDistributionRuleService
{
    public const BENEFIT_KEY = 'hsx_project_distribution';
    public const ELIGIBILITY_MEMBER_LEVEL = 'member_level';
    public const ELIGIBILITY_ALL_MEMBER = 'all_member';

    public function normalizeProjectRule(array $rule): array
    {
        $type = (string)($rule['commission_type'] ?? 'fixed');
        if (!in_array($type, ['fixed', 'ratio'], true)) $type = 'fixed';
        $eligibilityMode = (string)($rule['eligibility_mode'] ?? self::ELIGIBILITY_MEMBER_LEVEL);
        if (!in_array($eligibilityMode, [self::ELIGIBILITY_MEMBER_LEVEL, self::ELIGIBILITY_ALL_MEMBER], true)) {
            $eligibilityMode = self::ELIGIBILITY_MEMBER_LEVEL;
        }
        $max = $type === 'ratio' ? 100 : 999999.99;
        return [
            'eligibility_mode' => $eligibilityMode,
            'commission_type' => $type,
            'first_value' => min($max, max(0, round((float)($rule['first_value'] ?? 0), 2))),
            'second_value' => min($max, max(0, round((float)($rule['second_value'] ?? 0), 2))),
            'settle_days' => min(365, max(0, (int)($rule['settle_days'] ?? 7))),
            'approval_requires_payment_check' => !array_key_exists('approval_requires_payment_check', $rule)
                || !empty($rule['approval_requires_payment_check']) ? 1 : 0,
            // 由项目服务端写入，用于阻止启用分销前的历史工单被补偿任务追溯发佣。
            'enabled_at' => max(0, (int)($rule['enabled_at'] ?? 0)),
        ];
    }

    public function projectRule(ProjectCenterProject|array $project): array
    {
        $data = $project instanceof ProjectCenterProject ? $project->toArray() : $project;
        $config = $data['config_json'] ?? [];
        if (is_string($config) && trim($config) !== '') {
            $decoded = json_decode($config, true);
            $config = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($config)) $config = [];
        return $this->normalizeProjectRule((array)($config['distribution'] ?? []));
    }

    public function validateEnabledRule(array $rule): void
    {
        $rule = $this->normalizeProjectRule($rule);
        if ($rule['first_value'] <= 0 && $rule['second_value'] <= 0) {
            throw new CommonException('开启邀请分佣后，一级和二级基础佣金不能同时为 0');
        }
    }

    /**
     * 项目可选择“全部正常会员”或“按会员等级权益”两种准入方式。
     * 不传项目时继续按会员等级判断，兼容已有调用和历史业务。
     */
    public function memberCapability(
        int $siteId,
        int $memberId,
        int $relationLevel = 1,
        ProjectCenterProject|array|null $project = null
    ): array
    {
        $eligibilityMode = self::ELIGIBILITY_MEMBER_LEVEL;
        if ($project !== null) {
            $projectData = $project instanceof ProjectCenterProject ? $project->toArray() : $project;
            $eligibilityMode = (string)$this->projectRule($projectData)['eligibility_mode'];
            if (empty($projectData['distribution_enabled'])) {
                return $this->ineligible('该项目暂未开启邀请分佣', $memberId, [], [], $eligibilityMode);
            }
        }
        $member = Member::where([
            ['site_id', '=', $siteId], ['member_id', '=', $memberId], ['status', '=', 1],
        ])->field('member_id,pid,member_level,nickname,username,mobile,commission')->findOrEmpty()->toArray();
        if ($member === []) return $this->ineligible('会员不存在或已停用', $memberId, [], [], $eligibilityMode);
        if ($eligibilityMode === self::ELIGIBILITY_ALL_MEMBER) {
            $benefit = [
                'is_use' => 1, 'second_enabled' => 1,
                'first_coefficient' => 100, 'second_coefficient' => 100,
                'eligibility_mode' => self::ELIGIBILITY_ALL_MEMBER,
            ];
            return [
                'eligible' => 1, 'reason' => '', 'member_id' => $memberId,
                'member' => $member, 'level_id' => 0, 'level_name' => '全部会员',
                'benefit' => $benefit, 'coefficient' => 100,
                'eligibility_mode' => self::ELIGIBILITY_ALL_MEMBER,
                'eligibility_mode_name' => '所有正常会员均可推广',
            ];
        }

        $levelId = (int)($member['member_level'] ?? 0);
        if ($levelId <= 0) return $this->ineligible('当前会员还没有可参与项目推广的会员等级', $memberId, $member);
        $level = MemberLevel::where([
            ['site_id', '=', $siteId], ['level_id', '=', $levelId],
        ])->field('level_id,level_name,status,level_benefits')->findOrEmpty()->toArray();
        if ($level === []) return $this->ineligible('会员等级不存在或已删除', $memberId, $member);
        if ((int)($level['status'] ?? 0) !== 1) {
            return $this->ineligible('“' . (string)$level['level_name'] . '”等级已停用，不能参与项目推广', $memberId, $member, $level);
        }

        $benefits = is_array($level['level_benefits'] ?? null) ? $level['level_benefits'] : [];
        $benefit = is_array($benefits[self::BENEFIT_KEY] ?? null) ? $benefits[self::BENEFIT_KEY] : [];
        if (empty($benefit['is_use'])) {
            return $this->ineligible('“' . (string)$level['level_name'] . '”等级未开启项目推广分佣权益', $memberId, $member, $level);
        }
        if ($relationLevel === 2 && empty($benefit['second_enabled'])) {
            return $this->ineligible('“' . (string)$level['level_name'] . '”等级未开启二级分佣', $memberId, $member, $level);
        }
        $coefficient = $relationLevel === 2
            ? (float)($benefit['second_coefficient'] ?? 100)
            : (float)($benefit['first_coefficient'] ?? 100);
        $coefficient = min(500, max(0, round($coefficient, 2)));
        if ($coefficient <= 0) {
            return $this->ineligible('当前会员等级的' . ($relationLevel === 2 ? '二级' : '一级') . '佣金系数为 0%', $memberId, $member, $level);
        }
        return [
            'eligible' => 1, 'reason' => '', 'member_id' => $memberId,
            'member' => $member, 'level_id' => (int)$level['level_id'],
            'level_name' => (string)$level['level_name'], 'benefit' => $benefit,
            'coefficient' => $coefficient,
            'eligibility_mode' => self::ELIGIBILITY_MEMBER_LEVEL,
            'eligibility_mode_name' => '按会员等级权益推广',
        ];
    }

    public function baseCommission(float $businessAmount, array $rule, int $relationLevel): float
    {
        $rule = $this->normalizeProjectRule($rule);
        $value = $relationLevel === 2 ? (float)$rule['second_value'] : (float)$rule['first_value'];
        return round($rule['commission_type'] === 'ratio' ? $businessAmount * $value / 100 : $value, 2);
    }

    public function commission(float $businessAmount, array $rule, int $relationLevel, float $coefficient): array
    {
        $base = $this->baseCommission($businessAmount, $rule, $relationLevel);
        return [
            'base_commission' => $base,
            'coefficient' => round($coefficient, 2),
            'commission_amount' => round($base * $coefficient / 100, 2),
        ];
    }

    public function publicSummary(array $project): array
    {
        if (empty($project['distribution_enabled'])) return ['enabled' => 0];
        $rule = $this->projectRule($project);
        $suffix = $rule['commission_type'] === 'ratio' ? '%' : '元';
        $eligibilityText = $rule['eligibility_mode'] === self::ELIGIBILITY_ALL_MEMBER
            ? '所有正常会员均可推广，佣金系数固定为 100%'
            : '推广资格与佣金系数由会员等级权益决定';
        return [
            'enabled' => 1,
            'eligibility_mode' => $rule['eligibility_mode'],
            'eligibility_mode_name' => $rule['eligibility_mode'] === self::ELIGIBILITY_ALL_MEMBER ? '所有会员' : '按会员等级',
            'commission_type' => $rule['commission_type'],
            'first_value' => $rule['first_value'], 'second_value' => $rule['second_value'],
            'settle_days' => $rule['settle_days'],
            'rule_text' => '一级基础佣金 ' . $rule['first_value'] . $suffix
                . '，二级基础佣金 ' . $rule['second_value'] . $suffix
                . '；' . $eligibilityText . '。只对开启分销后审核通过的新工单生效，历史工单不追溯',
        ];
    }

    private function ineligible(
        string $reason,
        int $memberId,
        array $member = [],
        array $level = [],
        string $eligibilityMode = self::ELIGIBILITY_MEMBER_LEVEL
    ): array
    {
        $allMember = $eligibilityMode === self::ELIGIBILITY_ALL_MEMBER;
        return [
            'eligible' => 0, 'reason' => $reason, 'member_id' => $memberId,
            'member' => $member, 'level_id' => (int)($level['level_id'] ?? 0),
            'level_name' => (string)($level['level_name'] ?? ''), 'benefit' => [], 'coefficient' => 0,
            'eligibility_mode' => $allMember ? self::ELIGIBILITY_ALL_MEMBER : self::ELIGIBILITY_MEMBER_LEVEL,
            'eligibility_mode_name' => $allMember ? '所有正常会员均可推广' : '按会员等级权益推广',
        ];
    }
}
