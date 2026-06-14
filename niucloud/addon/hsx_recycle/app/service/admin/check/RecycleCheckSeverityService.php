<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\check;

use addon\hsx_recycle\app\model\check\RecycleCheckOptionSeverity;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 全局“选项→级别”字典管理：normal正常/general一般/abnormal异常。
 * 用户改过的标 is_user_modified=1，重导播种时不再覆盖。
 */
class RecycleCheckSeverityService extends BaseAdminService
{
    public const SEVERITIES = ['normal', 'general', 'abnormal'];

    public function getPage(array $where = []): array
    {
        $query = RecycleCheckOptionSeverity::where('site_id', $this->site_id)->order('id asc');
        if (!empty($where['severity']) && in_array($where['severity'], self::SEVERITIES, true)) {
            $query->where('severity', '=', (string)$where['severity']);
        }
        if (!empty($where['keyword'])) {
            $query->whereLike('option_label', '%' . trim((string)$where['keyword']) . '%');
        }
        return $this->pageQuery($query);
    }

    /** 统计各级别数量 */
    public function summary(): array
    {
        $rows = RecycleCheckOptionSeverity::where('site_id', $this->site_id)
            ->field('severity, count(*) as cnt')->group('severity')->select()->toArray();
        $map = ['normal' => 0, 'general' => 0, 'abnormal' => 0];
        foreach ($rows as $r) {
            $map[$r['severity']] = (int)$r['cnt'];
        }
        return $map;
    }

    /** 设置单个选项级别(标记用户已改) */
    public function setSeverity(int $id, string $severity): bool
    {
        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new CommonException('级别不正确');
        }
        $row = RecycleCheckOptionSeverity::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) {
            throw new CommonException('选项不存在');
        }
        $row->save(['severity' => $severity, 'is_user_modified' => 1, 'update_at' => time()]);
        return true;
    }

    /** 批量设置级别(标记用户已改) */
    public function batchSetSeverity(array $ids, string $severity): int
    {
        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new CommonException('级别不正确');
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }
        return RecycleCheckOptionSeverity::where('site_id', $this->site_id)
            ->whereIn('id', $ids)
            ->update(['severity' => $severity, 'is_user_modified' => 1, 'update_at' => time()]);
    }

    /** 按关键字批量打标(如把含“碎”“裂”的选项设为异常) */
    public function setByKeyword(string $keyword, string $severity): int
    {
        if ($keyword === '' || !in_array($severity, self::SEVERITIES, true)) {
            throw new CommonException('参数不正确');
        }
        return RecycleCheckOptionSeverity::where('site_id', $this->site_id)
            ->whereLike('option_label', '%' . $keyword . '%')
            ->update(['severity' => $severity, 'is_user_modified' => 1, 'update_at' => time()]);
    }
}
