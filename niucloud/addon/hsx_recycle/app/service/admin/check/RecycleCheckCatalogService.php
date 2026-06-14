<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\check;

use addon\hsx_recycle\app\model\check\RecycleCheckImportBatch;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 拍机堂检测表导入：网页上传一份 CSV → 后端分批(按型号)慢慢跑，
 * 直接生成「质检模板」(recycle_check_template/group/field/option) 并按型号绑定(recycle_template_binding)，
 * 验机组件、模板编辑器都不动。参考表(recycle_check_dict)只用于选项去重+级别。
 *
 * CSV 列：型号, 产品ID, 检测项, 分类, 默认选项, 全部选项(用 | 分隔)。文件按型号聚集(同型号相邻)。
 */
class RecycleCheckCatalogService extends BaseAdminService
{
    private const IMPORT_DIR = 'upload/check_import/';
    private const TPL_PREFIX = 'pjt_';                 // 拍机堂来源模板的 template_key 前缀
    private const BIND_SCENE = 'manual_device_label';  // 验机侧解析绑定用的场景

    /** 上传初始化：估算型号数(用行数粗估)，建批次，返回 token */
    public function importInit(string $absPath, string $token, string $fileName): array
    {
        if (!is_file($absPath)) {
            throw new CommonException('上传文件不存在');
        }
        $rows = 0;
        $fh = fopen($absPath, 'r');
        while (fgets($fh) !== false) { $rows++; }
        fclose($fh);
        $now = time();
        $batch = RecycleCheckImportBatch::create([
            'site_id' => $this->site_id, 'source' => 'paijitang',
            'file_name' => $fileName . '|' . $token, 'total_rows' => max($rows - 1, 0),
            'status' => 'processing', 'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username, 'create_at' => $now, 'update_at' => $now,
        ]);
        return ['batch_id' => (int)$batch->id, 'token' => $token, 'total_rows' => max($rows - 1, 0)];
    }

    /** 处理一片(按型号，最多 limit 个型号)。前端循环调用直到 done。offset=0 时先清旧拍机堂模板 */
    public function importChunk(int $batchId, string $token, int $offset, int $limit = 80): array
    {
        $path = public_path() . self::IMPORT_DIR . basename($token);
        if (!is_file($path)) {
            throw new CommonException('导入文件已失效，请重新上传');
        }
        $batch = RecycleCheckImportBatch::where([['site_id', '=', $this->site_id], ['id', '=', $batchId]])->findOrEmpty();
        if ($batch->isEmpty()) {
            throw new CommonException('导入批次不存在');
        }
        if ($offset === 0) {
            $this->clearImported();
        }
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new CommonException('无法读取导入文件');
        }
        if ($offset > 0) {
            fseek($fh, $offset);
        }
        $now = time();
        $optCache = []; $nodeCache = [];
        $newTpl = (int)$batch->inserted;   // 复用字段：新建模板数
        $bound = (int)$batch->updated;     // 复用字段：绑定型号数
        $rowsDone = (int)$batch->skipped_same; // 复用字段：已处理行数(进度)
        $buffer = []; $curModel = null; $modelsThis = 0; $done = false;
        $nextOffset = $offset;
        while (true) {
            $posBefore = ftell($fh);
            $row = fgetcsv($fh);
            if ($row === false) {
                if ($buffer) { $rowsDone += $this->processModel($buffer, $now, $optCache, $nodeCache, $newTpl, $bound); }
                $done = true; $nextOffset = ftell($fh); break;
            }
            if ($offset === 0 && $posBefore === 0) {
                $j = implode('', array_map('strval', $row));
                if (mb_strpos($j, '型号') !== false || mb_strpos($j, '检测项') !== false) { continue; }
            }
            $model = trim((string)($row[0] ?? ''));
            if ($model === '') { continue; }
            if ($curModel !== null && $model !== $curModel) {
                $rowsDone += $this->processModel($buffer, $now, $optCache, $nodeCache, $newTpl, $bound);
                $buffer = []; $modelsThis++;
                if ($modelsThis >= $limit) { $nextOffset = $posBefore; break; }
            }
            $curModel = $model; $buffer[] = $row;
        }
        fclose($fh);
        $save = ['inserted' => $newTpl, 'updated' => $bound, 'skipped_same' => $rowsDone, 'update_at' => time()];
        if ($done) { $save['status'] = 'completed'; @unlink($path); }
        $batch->save($save);
        return ['batch_id' => $batchId, 'next_offset' => $nextOffset, 'done' => $done,
                'templates' => $newTpl, 'bindings' => $bound, 'rows_done' => $rowsDone];
    }

    /** 处理一个型号的所有行：去重成模板(共享)→ 建/复用模板 → 绑定型号节点。返回处理行数 */
    private function processModel(array $rows, int $now, array &$optCache, array &$nodeCache, int &$newTpl, int &$bound): int
    {
        if (empty($rows)) { return 0; }
        $model = trim((string)($rows[0][0] ?? '')); $pid = (int)($rows[0][1] ?? 0);
        $items = [];
        foreach ($rows as $r) {
            $field = trim((string)($r[2] ?? ''));
            if ($field === '') { continue; }
            $group = trim((string)($r[3] ?? '')); $dft = trim((string)($r[4] ?? ''));
            $opts = array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/', (string)($r[5] ?? '')))));
            if ($dft !== '' && !in_array($dft, $opts, true)) { array_unshift($opts, $dft); }
            $items[] = ['group' => $group, 'field' => $field, 'default' => $dft, 'opts' => $opts];
        }
        $count = count($rows);
        if (empty($items)) { return $count; }

        $sig = md5(json_encode(array_map(
            fn($it) => $it['group'] . '#' . $it['field'] . '#' . $it['default'] . '#' . implode('|', $it['opts']),
            $items
        ), JSON_UNESCAPED_UNICODE));
        $templateKey = self::TPL_PREFIX . $sig;
        $tplId = (int)Db::name('recycle_check_template')
            ->where('site_id', $this->site_id)->where('template_key', $templateKey)->value('id');
        if ($tplId <= 0) {
            $tplId = (int)Db::name('recycle_check_template')->insertGetId([
                'site_id' => $this->site_id, 'template_key' => $templateKey,
                'template_name' => '拍机堂·' . mb_substr($model, 0, 80), 'scene' => 'phone',
                'is_default' => 0, 'status' => 1, 'sort' => 0, 'version' => 1,
                'create_at' => $now, 'update_at' => $now,
            ]);
            $newTpl++;
            $groupId = []; $gi = 0;
            foreach ($items as $it) {
                $g = $it['group'] !== '' ? $it['group'] : '检测项';
                if (!isset($groupId[$g])) {
                    $gi++;
                    $groupId[$g] = (int)Db::name('recycle_check_group')->insertGetId([
                        'site_id' => $this->site_id, 'template_id' => $tplId, 'group_key' => 'g' . $gi,
                        'group_name' => $g, 'description' => '', 'sort' => $gi, 'status' => 1,
                        'create_at' => $now, 'update_at' => $now,
                    ]);
                }
            }
            $fi = 0;
            foreach ($items as $it) {
                $fi++;
                $g = $it['group'] !== '' ? $it['group'] : '检测项';
                $fid = (int)Db::name('recycle_check_field')->insertGetId([
                    'site_id' => $this->site_id, 'template_id' => $tplId, 'group_id' => $groupId[$g],
                    'field_key' => 'f' . $fi, 'field_name' => $it['field'], 'component' => 'radio',
                    'selection_mode' => 'single', 'is_required' => 0, 'is_show' => 1,
                    'seller_visible' => 1, 'buyer_visible' => 0, 'result_visible' => 1,
                    'sort' => $fi, 'create_at' => $now, 'update_at' => $now,
                ]);
                $oi = 0;
                foreach ($it['opts'] as $o) {
                    $oi++;
                    Db::name('recycle_check_option')->insert([
                        'site_id' => $this->site_id, 'field_id' => $fid, 'option_label' => $o,
                        'option_value' => (string)$oi, 'is_default' => ($o === $it['default'] ? 1 : 0),
                        'is_show' => 1, 'severity' => $this->resolveOptionSeverity($optCache, $o, $now),
                        'sort' => $oi, 'create_at' => $now, 'update_at' => $now,
                    ]);
                }
            }
        }

        $nodeId = $this->resolveModelNode($nodeCache, $pid, $model);
        if ($nodeId > 0) {
            $existBind = (int)Db::name('recycle_template_binding')
                ->where('site_id', $this->site_id)->where('target_type', 'model_dict')
                ->where('target_id', $nodeId)->where('scene_key', self::BIND_SCENE)->value('id');
            if ($existBind > 0) {
                Db::name('recycle_template_binding')->where('id', $existBind)
                    ->update(['check_template_id' => $tplId, 'status' => 1, 'update_at' => $now]);
            } else {
                Db::name('recycle_template_binding')->insert([
                    'site_id' => $this->site_id, 'target_type' => 'model_dict', 'target_id' => $nodeId,
                    'scene_key' => self::BIND_SCENE, 'check_template_id' => $tplId, 'print_template_id' => 0,
                    'inherit_enabled' => 1, 'status' => 1, 'sort' => 0, 'remark' => '拍机堂导入',
                    'create_at' => $now, 'update_at' => $now,
                ]);
            }
            $bound++;
        }
        return $count;
    }

    /** 选项级别：dict 里有就用其级别，没有就建(默认normal)。保住用户改过的级别 */
    private function resolveOptionSeverity(array &$cache, string $label, int $now): string
    {
        $label = trim($label);
        if ($label === '') { return 'normal'; }
        $key = mb_strtolower($label);
        if (isset($cache[$key])) { return $cache[$key]; }
        $row = Db::name('recycle_check_dict')
            ->where('site_id', $this->site_id)->where('dict_type', 'option')->where('text', $label)
            ->field('id,severity')->find();
        if (!$row) {
            Db::name('recycle_check_dict')->insert([
                'site_id' => $this->site_id, 'dict_type' => 'option', 'text' => $label,
                'severity' => 'normal', 'is_user_modified' => 0, 'sort' => 0,
                'create_at' => $now, 'update_at' => $now,
            ]);
            $cache[$key] = 'normal';
            return 'normal';
        }
        $cache[$key] = (string)$row['severity'];
        return $cache[$key];
    }

    /** 拍机堂产品ID → 型号字典节点ID(product_source_id 匹配)。缓存 */
    private function resolveModelNode(array &$cache, int $pid, string $model): int
    {
        if ($pid <= 0) { return 0; }
        if (isset($cache[$pid])) { return $cache[$pid]; }
        $id = (int)Db::name('recycle_device_model_dict')
            ->where('site_id', $this->site_id)->where('product_source_id', (string)$pid)
            ->order('id asc')->value('id');
        $cache[$pid] = $id;
        return $id;
    }

    /** 清掉旧的拍机堂来源模板(template_key 前缀 pjt_)及其分组/字段/选项/绑定。自定义模板不碰 */
    private function clearImported(): void
    {
        $tplIds = Db::name('recycle_check_template')
            ->where('site_id', $this->site_id)->whereLike('template_key', self::TPL_PREFIX . '%')
            ->column('id');
        if (empty($tplIds)) { return; }
        $fieldIds = Db::name('recycle_check_field')->where('site_id', $this->site_id)->whereIn('template_id', $tplIds)->column('id');
        if (!empty($fieldIds)) {
            Db::name('recycle_check_option')->where('site_id', $this->site_id)->whereIn('field_id', $fieldIds)->delete();
        }
        Db::name('recycle_check_field')->where('site_id', $this->site_id)->whereIn('template_id', $tplIds)->delete();
        Db::name('recycle_check_group')->where('site_id', $this->site_id)->whereIn('template_id', $tplIds)->delete();
        Db::name('recycle_template_binding')->where('site_id', $this->site_id)->whereIn('check_template_id', $tplIds)->delete();
        Db::name('recycle_check_template')->where('site_id', $this->site_id)->whereIn('id', $tplIds)->delete();
    }

    /** 概览：拍机堂模板数 / 绑定型号数 / 选项字典数 */
    public function summary(): array
    {
        return [
            'templates' => (int)Db::name('recycle_check_template')->where('site_id', $this->site_id)->whereLike('template_key', self::TPL_PREFIX . '%')->count(),
            'bindings' => (int)Db::name('recycle_template_binding')->where('site_id', $this->site_id)->where('scene_key', self::BIND_SCENE)->where('check_template_id', '>', 0)->count(),
            'options' => (int)Db::name('recycle_check_dict')->where([['site_id', '=', $this->site_id], ['dict_type', '=', 'option']])->count(),
        ];
    }

    /** 导入批次记录 */
    public function batchList(int $limit = 30): array
    {
        return RecycleCheckImportBatch::where('site_id', $this->site_id)->order('id desc')->limit($limit)->select()->toArray();
    }
}
