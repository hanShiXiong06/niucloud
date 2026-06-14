<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\check;

use addon\hsx_recycle\app\model\check\RecycleCheckCatalog;
use addon\hsx_recycle\app\model\check\RecycleCheckImportBatch;
use addon\hsx_recycle\app\model\check\RecycleCheckOptionSeverity;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 质检检测目录服务：流式导入(去重/用户改过跳过) + 级别字典播种 + 目录查询。
 *
 * 大文件(50万行)走 CSV 流式(fgetcsv)，内存恒定。
 * CSV 列顺序(无表头或首行表头自动跳过)：
 *   型号, 产品ID, 检测项, 分类, 默认选项, 全部选项(用 | 分隔)
 * 与拍机堂导出表一致：型号/产品ID/检测项/分类/默认选项/全部选项。
 */
class RecycleCheckCatalogService extends BaseAdminService
{
    private const SEP = "\x01"; // 复合键分隔符
    private const CHUNK = 1000;

    /**
     * 从 CSV 流式导入。
     * @param string $absPath 服务器上的绝对路径
     * @param string $source 目录来源标识
     * @param string $fileName 展示用文件名
     * @return array 批次统计
     */
    public function importCsv(string $absPath, string $source = 'paijitang', string $fileName = ''): array
    {
        if (!is_file($absPath)) {
            throw new CommonException('导入文件不存在');
        }
        $fh = fopen($absPath, 'r');
        if (!$fh) {
            throw new CommonException('无法读取导入文件');
        }

        $now = time();
        $batch = RecycleCheckImportBatch::create([
            'site_id' => $this->site_id,
            'source' => $source,
            'file_name' => $fileName,
            'status' => 'processing',
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'create_at' => $now,
            'update_at' => $now,
        ]);

        $stat = ['total_rows' => 0, 'inserted' => 0, 'updated' => 0, 'skipped_same' => 0, 'skipped_user' => 0, 'seeded_options' => 0];
        $chunk = [];
        $optionLabels = []; // 去重收集，用于播种级别字典

        try {
            $first = true;
            while (($row = fgetcsv($fh)) !== false) {
                // 跳过表头(首行含“型号”或“检测项”字样)
                if ($first) {
                    $first = false;
                    $joined = implode('', array_map('strval', $row));
                    if (mb_strpos($joined, '型号') !== false || mb_strpos($joined, '检测项') !== false) {
                        continue;
                    }
                }
                $parsed = $this->parseRow($row);
                if ($parsed === null) {
                    continue;
                }
                $stat['total_rows']++;
                $chunk[] = $parsed;
                foreach ($parsed['options'] as $opt) {
                    $optionLabels[$opt] = true;
                }
                if (count($chunk) >= self::CHUNK) {
                    $this->flushChunk($source, $chunk, $now, $stat);
                    $chunk = [];
                }
                if (count($optionLabels) >= 5000) {
                    $stat['seeded_options'] += $this->seedSeverity(array_keys($optionLabels), $now);
                    $optionLabels = [];
                }
            }
            if ($chunk) {
                $this->flushChunk($source, $chunk, $now, $stat);
            }
            if ($optionLabels) {
                $stat['seeded_options'] += $this->seedSeverity(array_keys($optionLabels), $now);
            }
            $batch->save(array_merge($stat, ['status' => 'completed', 'update_at' => time()]));
        } catch (\Throwable $e) {
            $batch->save(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 900), 'update_at' => time()]);
            fclose($fh);
            throw $e;
        }
        fclose($fh);
        return array_merge(['batch_id' => (int)$batch->id], $stat);
    }

    /**
     * 解析一行 CSV → 标准结构；非法行返回 null。
     */
    private function parseRow(array $row): ?array
    {
        $row = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row);
        $modelKey = (string)($row[0] ?? '');
        $fieldName = (string)($row[2] ?? '');
        if ($modelKey === '' || $fieldName === '') {
            return null;
        }
        $optionsRaw = (string)($row[5] ?? '');
        $options = array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/', $optionsRaw))));
        $default = (string)($row[4] ?? '');
        $group = (string)($row[3] ?? '');
        // 内容哈希：分类|检测项|默认|全部选项
        $hash = md5($group . '|' . $fieldName . '|' . $default . '|' . implode('|', $options));
        return [
            'model_key' => $modelKey,
            'product_id' => (int)($row[1] ?? 0),
            'group_name' => $group,
            'field_name' => $fieldName,
            'default_option' => $default,
            'options' => $options,
            'import_hash' => $hash,
        ];
    }

    /**
     * 处理一批行：按 (model_key,field_name) 与已有记录比对，决定 新增/更新/跳过。
     */
    private function flushChunk(string $source, array $chunk, int $now, array &$stat): void
    {
        // 去重：同一批内同 key 取最后一条
        $byKey = [];
        foreach ($chunk as $r) {
            $byKey[$r['model_key'] . self::SEP . $r['field_name']] = $r;
        }
        $modelKeys = array_values(array_unique(array_map(fn($r) => $r['model_key'], $chunk)));

        // 拉取这些型号已有目录行
        $existRows = Db::name('recycle_check_catalog')
            ->where('site_id', $this->site_id)
            ->where('source', $source)
            ->whereIn('model_key', $modelKeys)
            ->field('id,model_key,field_name,import_hash,is_user_modified')
            ->select()->toArray();
        $existMap = [];
        foreach ($existRows as $er) {
            $existMap[$er['model_key'] . self::SEP . $er['field_name']] = $er;
        }

        $inserts = [];
        Db::startTrans();
        try {
            foreach ($byKey as $key => $r) {
                $data = [
                    'group_name' => $r['group_name'],
                    'field_name' => $r['field_name'],
                    'default_option' => $r['default_option'],
                    'options_json' => json_encode($r['options'], JSON_UNESCAPED_UNICODE),
                    'import_hash' => $r['import_hash'],
                    'update_at' => $now,
                ];
                if (!isset($existMap[$key])) {
                    $inserts[] = array_merge($data, [
                        'site_id' => $this->site_id,
                        'source' => $source,
                        'model_key' => $r['model_key'],
                        'product_id' => $r['product_id'],
                        'is_user_modified' => 0,
                        'create_at' => $now,
                    ]);
                    $stat['inserted']++;
                    continue;
                }
                $ex = $existMap[$key];
                if ((int)$ex['is_user_modified'] === 1) {
                    $stat['skipped_user']++;   // 用户改过，永久跳过
                    continue;
                }
                if ((string)$ex['import_hash'] === $r['import_hash']) {
                    $stat['skipped_same']++;   // 内容未变
                    continue;
                }
                Db::name('recycle_check_catalog')->where('id', (int)$ex['id'])->update(array_merge($data, [
                    'product_id' => $r['product_id'],
                ]));
                $stat['updated']++;
            }
            if ($inserts) {
                // 分小段插入，避免单条 SQL 过长
                foreach (array_chunk($inserts, 500) as $seg) {
                    Db::name('recycle_check_catalog')->insertAll($seg);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 播种级别字典：把新出现的选项文本插入(默认 normal)，已存在的不动(保住用户改的级别)。
     * @return int 新增条数
     */
    private function seedSeverity(array $labels, int $now): int
    {
        $labels = array_values(array_unique(array_filter(array_map('strval', $labels), fn($v) => $v !== '')));
        if (!$labels) {
            return 0;
        }
        $seeded = 0;
        foreach (array_chunk($labels, 800) as $seg) {
            $exist = Db::name('recycle_check_option_severity')
                ->where('site_id', $this->site_id)
                ->whereIn('option_label', $seg)
                ->column('option_label');
            $existSet = array_flip($exist);
            $rows = [];
            foreach ($seg as $label) {
                if (isset($existSet[$label])) {
                    continue;
                }
                $rows[] = [
                    'site_id' => $this->site_id,
                    'option_label' => $label,
                    'severity' => 'normal',
                    'is_user_modified' => 0,
                    'create_at' => $now,
                    'update_at' => $now,
                ];
            }
            if ($rows) {
                Db::name('recycle_check_option_severity')->insertAll($rows);
                $seeded += count($rows);
            }
        }
        return $seeded;
    }

    /** 目录分页(按型号/分类/检测项筛选) */
    public function catalogPage(array $where = []): array
    {
        $query = RecycleCheckCatalog::where('site_id', $this->site_id)->order('model_key asc,group_sort asc,field_sort asc,id asc');
        if (!empty($where['model_key'])) {
            $query->whereLike('model_key', '%' . trim((string)$where['model_key']) . '%');
        }
        if (!empty($where['group_name'])) {
            $query->where('group_name', '=', (string)$where['group_name']);
        }
        if (!empty($where['keyword'])) {
            $query->whereLike('field_name|model_key', '%' . trim((string)$where['keyword']) . '%');
        }
        return $this->pageQuery($query);
    }

    /** 最近导入批次 */
    public function batchList(int $limit = 20): array
    {
        return RecycleCheckImportBatch::where('site_id', $this->site_id)
            ->order('id desc')->limit($limit)->select()->toArray();
    }
}
