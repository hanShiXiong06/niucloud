<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\check;

use addon\hsx_recycle\app\model\check\RecycleCheckData;
use addon\hsx_recycle\app\model\check\RecycleCheckDict;
use addon\hsx_recycle\app\model\check\RecycleCheckImportBatch;
use core\base\BaseAdminService;

/**
 * 质检检测数据：参考表(recycle_check_dict) + 数据表(recycle_check_data，全ID映射)。
 * 大批量初始数据走 LOAD DATA 灌库(本地去重)；本服务负责展示与按型号取数。
 */
class RecycleCheckCatalogService extends BaseAdminService
{
    /** 数据表分页(按型号/检测项筛选)，并把 ID 还原成中文展示 */
    public function dataPage(array $where = []): array
    {
        $query = RecycleCheckData::where('site_id', $this->site_id)
            ->order('model_key asc,group_id asc,sort asc,id asc');
        if (!empty($where['model_key'])) {
            $query->whereLike('model_key', '%' . trim((string)$where['model_key']) . '%');
        }
        if (!empty($where['product_id'])) {
            $query->where('product_id', '=', (int)$where['product_id']);
        }
        $result = $this->pageQuery($query);
        $this->fillDictText($result['data']);
        return $result;
    }

    /** 按型号取整套检测项(含选项+级别)，供质检/预览用 */
    public function getByModel(string $modelKey): array
    {
        if ($modelKey === '') {
            return [];
        }
        $rows = RecycleCheckData::where([['site_id', '=', $this->site_id], ['model_key', '=', $modelKey]])
            ->order('group_id asc,sort asc,id asc')->select()->toArray();
        $this->fillDictText($rows);
        return $rows;
    }

    /** 把数据行里的各种 dict id 还原成中文(批量取字典，避免 N+1) */
    private function fillDictText(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }
        $ids = [];
        foreach ($rows as $r) {
            foreach (['group_id', 'field_id', 'default_option_id'] as $k) {
                if (!empty($r[$k])) {
                    $ids[(int)$r[$k]] = true;
                }
            }
            foreach (explode(',', (string)($r['option_ids'] ?? '')) as $oid) {
                if ($oid !== '') {
                    $ids[(int)$oid] = true;
                }
            }
        }
        $dict = [];
        if (!empty($ids)) {
            foreach (RecycleCheckDict::where('site_id', $this->site_id)->whereIn('id', array_keys($ids))
                         ->field('id,text,severity')->select()->toArray() as $d) {
                $dict[(int)$d['id']] = $d;
            }
        }
        foreach ($rows as &$r) {
            $r['group_name'] = $dict[(int)$r['group_id']]['text'] ?? '';
            $r['field_name'] = $dict[(int)$r['field_id']]['text'] ?? '';
            $r['default_option'] = $dict[(int)$r['default_option_id']]['text'] ?? '';
            $opts = [];
            foreach (explode(',', (string)($r['option_ids'] ?? '')) as $oid) {
                if ($oid === '') {
                    continue;
                }
                $oid = (int)$oid;
                $opts[] = [
                    'id' => $oid,
                    'label' => $dict[$oid]['text'] ?? '',
                    'severity' => $dict[$oid]['severity'] ?? 'normal',
                ];
            }
            $r['options'] = $opts;
        }
        unset($r);
    }

    /** 概览统计 */
    public function summary(): array
    {
        return [
            'models' => (int)RecycleCheckData::where('site_id', $this->site_id)->group('model_key')->count(),
            'data_rows' => (int)RecycleCheckData::where('site_id', $this->site_id)->count(),
            'fields' => (int)RecycleCheckDict::where([['site_id', '=', $this->site_id], ['dict_type', '=', 'field']])->count(),
            'options' => (int)RecycleCheckDict::where([['site_id', '=', $this->site_id], ['dict_type', '=', 'option']])->count(),
        ];
    }

    /** 导入批次记录 */
    public function batchList(int $limit = 30): array
    {
        return RecycleCheckImportBatch::where('site_id', $this->site_id)
            ->order('id desc')->limit($limit)->select()->toArray();
    }
}
