<?php

namespace addon\phone_shop\app\service\core\member;

use app\model\member\MemberLevel;

/**
 * 同行转发会员权益解析器。
 *
 * 权限归属于会员等级，绝不依赖等级名称中是否包含“同行”。
 */
class ForwardBenefitService
{
    public const KEY = 'shop_goods_forward';

    public function getLevelConfig(int $siteId, int $levelId): array
    {
        if ($levelId <= 0) return [];
        $level = MemberLevel::where([
            ['site_id', '=', $siteId],
            ['level_id', '=', $levelId],
        ])->field('level_id,level_name,level_benefits')->findOrEmpty()->toArray();
        return $this->normalizeLevel($level);
    }

    /** 找到允许用户申请的目标等级。通常一个站点只配置一个。 */
    public function getApplicationTarget(int $siteId): array
    {
        $levels = MemberLevel::where([['site_id', '=', $siteId]])
            ->field('level_id,level_name,level_benefits,growth')->order('growth asc,level_id asc')->select()->toArray();
        foreach ($levels as $level) {
            $normalized = $this->normalizeLevel($level);
            if (($normalized['config']['is_use'] ?? 0) === 1 && ($normalized['config']['allow_apply'] ?? 0) === 1) {
                return $normalized;
            }
        }
        return [];
    }

    public function canUse(int $siteId, int $levelId): bool
    {
        $level = $this->getLevelConfig($siteId, $levelId);
        return (int)($level['config']['is_use'] ?? 0) === 1;
    }

    private function normalizeLevel(array $level): array
    {
        if (!$level) return [];
        $benefits = $level['level_benefits'] ?? [];
        if (is_string($benefits)) $benefits = json_decode($benefits, true) ?: [];
        $config = is_array($benefits[self::KEY] ?? null) ? $benefits[self::KEY] : [];
        $config = array_merge([
            'is_use' => 0,
            'allow_apply' => 0,
            'form_id' => 0,
            'reviewer_uid' => 0,
            'reviewer_name' => '',
        ], $config);
        $config['is_use'] = (int)$config['is_use'];
        $config['allow_apply'] = (int)$config['allow_apply'];
        $config['form_id'] = (int)$config['form_id'];
        $config['reviewer_uid'] = (int)$config['reviewer_uid'];
        return [
            'level_id' => (int)$level['level_id'],
            'level_name' => (string)$level['level_name'],
            'config' => $config,
        ];
    }
}
