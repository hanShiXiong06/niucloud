<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\check;

use addon\hsx_recycle\app\model\check\RecycleCheckDict;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 选项级别：操作参考表(recycle_check_dict) 里 dict_type=option 的行的 severity。
 * normal正常(success绿)/general一般(info)/abnormal异常(danger红)。
 * 用户改过的标 is_user_modified=1，重导播种时不覆盖。
 */
class RecycleCheckSeverityService extends BaseAdminService
{
    public const SEVERITIES = ['normal', 'general', 'abnormal'];

    private function baseQuery()
    {
        return RecycleCheckDict::where([['site_id', '=', $this->site_id], ['dict_type', '=', 'option']]);
    }

    public function getPage(array $where = []): array
    {
        $query = $this->baseQuery()->order('id asc');
        if (!empty($where['severity']) && in_array($where['severity'], self::SEVERITIES, true)) {
            $query->where('severity', '=', (string)$where['severity']);
        }
        if (!empty($where['keyword'])) {
            $query->whereLike('text', '%' . trim((string)$where['keyword']) . '%');
        }
        return $this->pageQuery($query);
    }

    public function summary(): array
    {
        $rows = $this->baseQuery()->field('severity, count(*) as cnt')->group('severity')->select()->toArray();
        $map = ['normal' => 0, 'general' => 0, 'abnormal' => 0];
        foreach ($rows as $r) {
            $map[$r['severity']] = (int)$r['cnt'];
        }
        return $map;
    }

    public function setSeverity(int $id, string $severity): bool
    {
        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new CommonException('级别不正确');
        }
        $row = RecycleCheckDict::where([['site_id', '=', $this->site_id], ['id', '=', $id], ['dict_type', '=', 'option']])->findOrEmpty();
        if ($row->isEmpty()) {
            throw new CommonException('选项不存在');
        }
        $row->save(['severity' => $severity, 'is_user_modified' => 1, 'update_at' => time()]);
        return true;
    }

    public function batchSetSeverity(array $ids, string $severity): int
    {
        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new CommonException('级别不正确');
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }
        return $this->baseQuery()->whereIn('id', $ids)
            ->update(['severity' => $severity, 'is_user_modified' => 1, 'update_at' => time()]);
    }

    /** 按关键字批量打标(如含“碎/裂”设为异常) */
    public function setByKeyword(string $keyword, string $severity): int
    {
        if ($keyword === '' || !in_array($severity, self::SEVERITIES, true)) {
            throw new CommonException('参数不正确');
        }
        return $this->baseQuery()->whereLike('text', '%' . $keyword . '%')
            ->update(['severity' => $severity, 'is_user_modified' => 1, 'update_at' => time()]);
    }
}
