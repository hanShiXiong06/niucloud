<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\check;

use addon\hsx_recycle\app\model\check\RecycleCheckData;
use addon\hsx_recycle\app\model\check\RecycleCheckDict;
use addon\hsx_recycle\app\model\check\RecycleCheckImportBatch;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 质检检测数据：参考表(recycle_check_dict) + 数据表(recycle_check_data，全ID映射)。
 * 网页上传原始拍机堂 CSV(型号,产品ID,检测项,分类,默认选项,全部选项)，后端分批慢慢跑：
 *   去重交给数据库(firstOrCreate，按其排序规则去重，绝不撞唯一键)。
 */
class RecycleCheckCatalogService extends BaseAdminService
{
    private const IMPORT_DIR = 'upload/check_import/';
    private int $newDictCount = 0;

    /** 上传初始化：存批次，估算行数，返回 token 供分批读取 */
    public function importInit(string $absPath, string $token, string $fileName): array
    {
        if (!is_file($absPath)) {
            throw new CommonException('上传文件不存在');
        }
        $total = 0;
        $fh = fopen($absPath, 'r');
        while (fgets($fh) !== false) {
            $total++;
        }
        fclose($fh);
        $now = time();
        $batch = RecycleCheckImportBatch::create([
            'site_id' => $this->site_id, 'source' => 'paijitang',
            'file_name' => $fileName . '|' . $token, 'total_rows' => max($total - 1, 0),
            'status' => 'processing', 'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username, 'create_at' => $now, 'update_at' => $now,
        ]);
        return ['batch_id' => (int)$batch->id, 'token' => $token, 'total_rows' => max($total - 1, 0)];
    }

    /** 处理一片(从字节 offset 起最多 limit 行)，返回进度；前端循环调用直到 done */
    public function importChunk(int $batchId, string $token, int $offset, int $limit = 2000): array
    {
        $path = public_path() . self::IMPORT_DIR . basename($token);
        if (!is_file($path)) {
            throw new CommonException('导入文件已失效，请重新上传');
        }
        $batch = RecycleCheckImportBatch::where([['site_id', '=', $this->site_id], ['id', '=', $batchId]])->findOrEmpty();
        if ($batch->isEmpty()) {
            throw new CommonException('导入批次不存在');
        }
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new CommonException('无法读取导入文件');
        }
        if ($offset > 0) {
            fseek($fh, $offset);
        }
        $cache = [];
        $this->newDictCount = 0;
        $now = time();
        $ins = (int)$batch->inserted; $upd = (int)$batch->updated;
        $same = (int)$batch->skipped_same; $su = (int)$batch->skipped_user; $nd = (int)$batch->new_dict;
        $processed = 0; $done = false;
        while ($processed < $limit) {
            $row = fgetcsv($fh);
            if ($row === false) { $done = true; break; }
            $processed++;
            if ($offset === 0 && $processed === 1) {
                $j = implode('', array_map('strval', $row));
                if (mb_strpos($j, '型号') !== false || mb_strpos($j, '检测项') !== false) {
                    continue; // 跳过表头
                }
            }
            $model = trim((string)($row[0] ?? '')); $field = trim((string)($row[2] ?? ''));
            if ($model === '' || $field === '') { continue; }
            $pid = (int)($row[1] ?? 0); $group = trim((string)($row[3] ?? '')); $dft = trim((string)($row[4] ?? ''));
            $opts = array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/', (string)($row[5] ?? '')))));
            if ($dft !== '' && !in_array($dft, $opts, true)) { array_unshift($opts, $dft); }
            $gid = $group !== '' ? $this->resolveDict($cache, 'group', $group, $now) : 0;
            $fid = $this->resolveDict($cache, 'field', $field, $now);
            $optIds = [];
            foreach ($opts as $o) { $optIds[] = $this->resolveDict($cache, 'option', $o, $now); }
            $defid = $dft !== '' ? $this->resolveDict($cache, 'option', $dft, $now) : 0;
            $hash = md5($group . '|' . $field . '|' . $dft . '|' . implode('|', $opts));
            $exist = Db::name('recycle_check_data')
                ->where('site_id', $this->site_id)->where('model_key', $model)->where('field_id', $fid)
                ->field('id,import_hash,is_user_modified')->find();
            $data = ['group_id' => $gid, 'default_option_id' => $defid, 'option_ids' => implode(',', $optIds),
                     'import_hash' => $hash, 'product_id' => $pid, 'update_at' => $now];
            if (!$exist) {
                Db::name('recycle_check_data')->insert(array_merge($data, [
                    'site_id' => $this->site_id, 'model_key' => $model, 'field_id' => $fid,
                    'is_user_modified' => 0, 'sort' => 0, 'create_at' => $now,
                ]));
                $ins++;
            } elseif ((int)$exist['is_user_modified'] === 1) {
                $su++;
            } elseif ((string)$exist['import_hash'] === $hash) {
                $same++;
            } else {
                Db::name('recycle_check_data')->where('id', (int)$exist['id'])->update($data);
                $upd++;
            }
        }
        $nextOffset = ftell($fh);
        fclose($fh);
        $nd += $this->newDictCount;
        $save = ['inserted' => $ins, 'updated' => $upd, 'skipped_same' => $same, 'skipped_user' => $su,
                 'new_dict' => $nd, 'update_at' => time()];
        if ($done) {
            $save['status'] = 'completed';
            @unlink($path);
        }
        $batch->save($save);
        return array_merge(['batch_id' => $batchId, 'next_offset' => $nextOffset, 'done' => $done], $save);
    }

    /** firstOrCreate 字典项(DB 去重，跟排序规则一致，绝不撞唯一键)，内存缓存 */
    private function resolveDict(array &$cache, string $type, string $text, int $now): int
    {
        $text = trim($text);
        if ($text === '') { return 0; }
        $key = $type . '|' . mb_strtolower($text);
        if (isset($cache[$key])) { return $cache[$key]; }
        $id = (int)Db::name('recycle_check_dict')
            ->where('site_id', $this->site_id)->where('dict_type', $type)->where('text', $text)
            ->value('id');
        if ($id <= 0) {
            $id = (int)Db::name('recycle_check_dict')->insertGetId([
                'site_id' => $this->site_id, 'dict_type' => $type, 'text' => $text,
                'severity' => 'normal', 'is_user_modified' => 0, 'sort' => 0,
                'create_at' => $now, 'update_at' => $now,
            ]);
            $this->newDictCount++;
        }
        $cache[$key] = $id;
        return $id;
    }

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
