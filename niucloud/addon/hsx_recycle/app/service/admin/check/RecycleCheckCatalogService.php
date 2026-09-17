<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\check;

use addon\hsx_recycle\app\model\check\RecycleCheckImportBatch;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use think\facade\Db;
use XMLReader;

/**
 * 拍机堂检测表导入：网页上传 Excel/CSV → 后端分批(按型号)慢慢跑，
 * 直接生成「质检模板」并按型号绑定(recycle_template_binding)。
 * 拍机堂导入模板使用 schema_json 紧凑存储，验机时按需展开，避免把大量一次性字段/选项写入明细表。
 * 手工模板仍使用 recycle_check_group/field/option，模板编辑器不受影响。
 *
 * 表格列：型号, 产品ID, 检测项, 分类, 默认选项, 全部选项(用 | 分隔)。文件按型号聚集(同型号相邻)。
 */
class RecycleCheckCatalogService extends BaseAdminService
{
    private const IMPORT_DIR = 'upload/check_import/';
    private const TPL_PREFIX = 'pjt_';                 // 拍机堂来源模板的 template_key 前缀
    private const BIND_SCENE = 'manual_device_label';  // 验机侧解析绑定用的场景
    private const EXCEL_CHUNK_ROWS = 20000;

    /** 上传初始化：估算型号数(用行数粗估)，建批次，返回 token */
    public function importInit(string $absPath, string $token, string $fileName, string $mode = 'append'): array
    {
        $this->ensureCompactColumns();
        $mode = $this->normalizeImportMode($mode);
        if (!is_file($absPath)) {
            throw new CommonException('上传文件不存在');
        }
        $this->validateImportFile($absPath);
        $rows = $this->countDataRows($absPath);
        $now = time();
        $batch = RecycleCheckImportBatch::create([
            'site_id' => $this->site_id, 'source' => 'paijitang',
            'file_name' => $fileName . '|' . $token . '|' . $mode, 'total_rows' => $rows,
            'status' => 'processing', 'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username, 'create_at' => $now, 'update_at' => $now,
        ]);
        return ['batch_id' => (int)$batch->id, 'token' => $token, 'total_rows' => $rows, 'mode' => $mode];
    }

    /** 处理一片(按型号，最多 limit 个型号)。前端循环调用直到 done。覆盖模式 offset=0 时先清旧拍机堂模板 */
    public function importChunk(int $batchId, string $token, int $offset, int $limit = 80, string $mode = ''): array
    {
        $this->ensureCompactColumns();
        $path = public_path() . self::IMPORT_DIR . basename($token);
        if (!is_file($path)) {
            throw new CommonException('导入文件已失效，请重新上传');
        }
        $batch = RecycleCheckImportBatch::where([['site_id', '=', $this->site_id], ['id', '=', $batchId]])->findOrEmpty();
        if ($batch->isEmpty()) {
            throw new CommonException('导入批次不存在');
        }
        $mode = $mode !== '' ? $this->normalizeImportMode($mode) : $this->resolveImportModeFromBatch((string)$batch->file_name);
        $limit = max(1, min($limit, 1000));
        $now = time();
        $optCache = []; $nodeCache = [];
        $newTpl = (int)$batch->inserted;   // 复用字段：新建模板数
        $bound = (int)$batch->updated;     // 复用字段：绑定型号数
        $rowsDone = (int)$batch->skipped_same; // 复用字段：已处理行数(进度)
        $skippedExists = (int)$batch->skipped_user; // 复用字段：追加模式下已有模板的型号数
        $totalRows = (int)$batch->total_rows;
        try {
            // 也校验升级前已经上传的批次；必须在覆盖清理之前，空文件不能清掉现有模板。
            if ($offset === 0) {
                $this->validateImportFile($path);
            }
            Db::transaction(function () use ($path, $offset, $limit, $totalRows, $now, $mode, $batch,
                &$optCache, &$nodeCache, &$newTpl, &$bound, &$rowsDone, &$skippedExists, &$result) {
                if ($offset === 0) {
                    if ($mode === 'overwrite') {
                        $this->clearImported();
                    }
                    $this->seedGrades();
                }
                $result = $this->isSpreadsheetFile($path)
                    ? $this->processSpreadsheetChunk($path, $offset, $limit, $totalRows, $now, $optCache, $nodeCache, $newTpl, $bound, $rowsDone, $skippedExists, $mode)
                    : $this->processCsvChunk($path, $offset, $limit, $now, $optCache, $nodeCache, $newTpl, $bound, $rowsDone, $skippedExists, $mode);
                if ($result['done'] && $rowsDone === 0) {
                    throw new CommonException('未读取到可导入的检测项，本次未完成导入。请检查首个工作表的表头和内容后重新上传。');
                }
                if (!$result['done'] && $result['next_offset'] <= $offset) {
                    throw new CommonException('导入进度未推进，已停止处理。请检查文件是否完整后重新上传。');
                }
                $batch->save(['inserted' => $newTpl, 'updated' => $bound, 'skipped_same' => $rowsDone,
                    'skipped_user' => $skippedExists, 'update_at' => time(), 'error_message' => '',
                    'status' => $result['done'] ? 'completed' : 'processing']);
            });
        } catch (\Throwable $e) {
            $batch->save(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000), 'update_at' => time()]);
            throw $e;
        }
        $done = $result['done'];
        $nextOffset = $result['next_offset'];
        if ($done) {
            @unlink($path);
        }
        return ['batch_id' => $batchId, 'next_offset' => $nextOffset, 'done' => $done,
                'templates' => $newTpl, 'bindings' => $bound, 'rows_done' => $rowsDone,
                'skipped_exists' => $skippedExists, 'mode' => $mode];
    }

    private function processSpreadsheetChunk(
        string $path,
        int $offset,
        int $limit,
        int $totalRows,
        int $now,
        array &$optCache,
        array &$nodeCache,
        int &$newTpl,
        int &$bound,
        int &$rowsDone,
        int &$skippedExists,
        string $mode
    ): array {
        if ($this->isXlsxFile($path)) {
            return $this->processXlsxStreamChunk($path, $offset, $limit, $totalRows, $now, $optCache, $nodeCache, $newTpl, $bound, $rowsDone, $skippedExists, $mode);
        }

        $startRow = $offset > 1 ? $offset : 2;

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class($startRow) implements IReadFilter {
                private int $startRow;

                public function __construct(int $startRow)
                {
                    $this->startRow = $startRow;
                }

                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row >= $this->startRow && in_array($columnAddress, ['A', 'B', 'C', 'D', 'E', 'F'], true);
                }
            });
        }
        Settings::setLibXmlLoaderOptions(LIBXML_COMPACT);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheet(0);
        $sourceHighestRow = max(1, $totalRows + 1);

        $buffer = [];
        $curModel = null;
        $modelsThis = 0;
        $lastReadRow = $startRow - 1;
        $done = false;
        $nextOffset = $startRow;

        // 按型号切片，不能在同一型号中间截断，否则追加模式会跳过剩余检测项。
        for ($rowNo = $startRow; $rowNo <= $sourceHighestRow; $rowNo++) {
            $row = [];
            for ($col = 1; $col <= 6; $col++) {
                $row[] = trim((string)$sheet->getCell([$col, $rowNo])->getFormattedValue());
            }
            $lastReadRow = $rowNo;
            if (implode('', $row) === '') {
                continue;
            }
            if ($this->isHeaderRow($row)) {
                continue;
            }
            $model = trim((string)($row[0] ?? ''));
            if ($model === '') {
                continue;
            }
            if ($curModel !== null && $model !== $curModel) {
                $rowsDone += $this->processModel($buffer, $now, $optCache, $nodeCache, $newTpl, $bound, $skippedExists, $mode);
                $buffer = [];
                $modelsThis++;
                if ($modelsThis >= $limit) {
                    $nextOffset = $rowNo;
                    $spreadsheet->disconnectWorksheets();
                    return ['next_offset' => $nextOffset, 'done' => false];
                }
            }
            $curModel = $model;
            $buffer[] = $row;
        }

        if ($buffer) {
            $rowsDone += $this->processModel($buffer, $now, $optCache, $nodeCache, $newTpl, $bound, $skippedExists, $mode);
        }
        $done = $lastReadRow >= $sourceHighestRow;
        $nextOffset = $done ? $sourceHighestRow + 1 : $lastReadRow + 1;
        $spreadsheet->disconnectWorksheets();
        return ['next_offset' => $nextOffset, 'done' => $done];
    }

    private function processXlsxStreamChunk(
        string $path,
        int $offset,
        int $limit,
        int $totalRows,
        int $now,
        array &$optCache,
        array &$nodeCache,
        int &$newTpl,
        int &$bound,
        int &$rowsDone,
        int &$skippedExists,
        string $mode
    ): array {
        $startRow = $offset > 1 ? $offset : 2;
        $endRow = $startRow + self::EXCEL_CHUNK_ROWS - 1;
        $sharedStrings = $this->readXlsxSharedStrings($path);
        $reader = $this->openXlsxXmlReader($path, $this->firstXlsxWorksheetPath($path));

        $buffer = [];
        $curModel = null;
        $modelsThis = 0;
        $lastReadRow = $startRow - 1;

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $rowNo = (int)$reader->getAttribute('r');
            if ($rowNo <= 0) {
                $rowNo = $lastReadRow + 1;
            }
            if ($rowNo < $startRow) {
                continue;
            }
            $row = $this->parseXlsxRow($reader->readOuterXml(), $sharedStrings);
            $lastReadRow = $rowNo;
            if (implode('', $row) === '' || $this->isHeaderRow($row)) {
                continue;
            }
            $model = trim((string)($row[0] ?? ''));
            if ($model === '') {
                continue;
            }
            if ($curModel !== null && $model !== $curModel) {
                $rowsDone += $this->processModel($buffer, $now, $optCache, $nodeCache, $newTpl, $bound, $skippedExists, $mode);
                $buffer = [];
                $modelsThis++;
                if ($modelsThis >= $limit || $rowNo > $endRow) {
                    $reader->close();
                    return ['next_offset' => $rowNo, 'done' => false];
                }
            }
            $curModel = $model;
            $buffer[] = $row;
        }

        if ($buffer) {
            $rowsDone += $this->processModel($buffer, $now, $optCache, $nodeCache, $newTpl, $bound, $skippedExists, $mode);
        }
        $reader->close();
        // 以实际文件结束为准；稀疏行号不能被误当成总行数而提前结束。
        return ['next_offset' => $lastReadRow + 1, 'done' => true];
    }

    private function processCsvChunk(
        string $path,
        int $offset,
        int $limit,
        int $now,
        array &$optCache,
        array &$nodeCache,
        int &$newTpl,
        int &$bound,
        int &$rowsDone,
        int &$skippedExists,
        string $mode
    ): array {
        $startRow = $offset > 1 ? $offset : 1;
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new CommonException('无法读取导入文件');
        }
        $buffer = [];
        $curModel = null;
        $modelsThis = 0;
        $rowNo = 0;
        $nextOffset = $startRow;
        while (($row = fgetcsv($fh)) !== false) {
            $rowNo++;
            if ($rowNo < $startRow) {
                continue;
            }
            if ($this->isHeaderRow($row)) {
                continue;
            }
            $model = trim((string)($row[0] ?? ''));
            if ($model === '') {
                continue;
            }
            if ($curModel !== null && $model !== $curModel) {
                $rowsDone += $this->processModel($buffer, $now, $optCache, $nodeCache, $newTpl, $bound, $skippedExists, $mode);
                $buffer = [];
                $modelsThis++;
                if ($modelsThis >= $limit) {
                    $nextOffset = $rowNo;
                    fclose($fh);
                    return ['next_offset' => $nextOffset, 'done' => false];
                }
            }
            $curModel = $model;
            $buffer[] = $row;
        }
        if ($buffer) {
            $rowsDone += $this->processModel($buffer, $now, $optCache, $nodeCache, $newTpl, $bound, $skippedExists, $mode);
        }
        fclose($fh);
        return ['next_offset' => $rowNo + 1, 'done' => true];
    }

    /** 处理一个型号的所有行：去重成模板(共享)→ 建/复用模板 → 绑定型号节点。返回处理行数 */
    private function processModel(
        array $rows,
        int $now,
        array &$optCache,
        array &$nodeCache,
        int &$newTpl,
        int &$bound,
        int &$skippedExists,
        string $mode
    ): int
    {
        $rows = array_values(array_filter($rows, static fn(array $row) => trim((string)($row[2] ?? '')) !== ''));
        if (empty($rows)) { return 0; }
        $model = trim((string)($rows[0][0] ?? '')); $pid = (int)($rows[0][1] ?? 0);
        $count = count($rows);
        $nodeId = $this->resolveModelNode($nodeCache, $pid, $model);
        if ($mode === 'append' && $nodeId > 0 && $this->modelHasTemplateBinding($nodeId)) {
            $skippedExists++;
            return $count;
        }
        $items = [];
        foreach ($rows as $r) {
            $field = trim((string)($r[2] ?? ''));
            if ($field === '') { continue; }
            $group = trim((string)($r[3] ?? '')); $dft = trim((string)($r[4] ?? ''));
            $opts = array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/', (string)($r[5] ?? '')))));
            if ($dft !== '' && !in_array($dft, $opts, true)) { array_unshift($opts, $dft); }
            $items[] = ['group' => $group, 'field' => $field, 'default' => $dft, 'opts' => $opts];
        }
        if (empty($items)) { return $count; }

        $schema = $this->buildCompactSchema($items, $optCache, $now);
        $schemaJson = $this->encodeJson($schema);
        $sig = md5($schemaJson);
        $templateKey = self::TPL_PREFIX . $sig;
        $tplId = (int)Db::name('recycle_check_template')
            ->where('site_id', $this->site_id)->where('template_key', $templateKey)->value('id');
        if ($tplId <= 0) {
            $tplId = (int)Db::name('recycle_check_template')->insertGetId([
                'site_id' => $this->site_id, 'template_key' => $templateKey,
                'template_name' => '拍机堂·' . mb_substr($model, 0, 80), 'scene' => 'pjt',
                'is_default' => 0, 'status' => 1, 'sort' => 0, 'version' => 1,
                'schema_hash' => $sig, 'schema_json' => $schemaJson,
                'create_at' => $now, 'update_at' => $now,
            ]);
            $newTpl++;
        }

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

    /**
     * 检测项名 → 设备稳定字段 key。匹配上则勾选值会写入设备同名列、被打印变量取用。
     * 仅映射到设备表确有的列(capacity/color/system_version/battery/package_type)，避免保存到不存在的列。
     * 想加新对应：在 $rules 加一行 + 确保设备表有该列(见 install/update SQL)。
     */
    private function mapFieldKey(string $fieldName): string
    {
        // 注：package_type(包装) 改为「设备信息」组里的固定标准字段，不从检测项映射，避免撞 key。
        $rules = [
            'capacity'       => ['内存', '容量', '规格', '存储'],
            'color'          => ['颜色'],
            'system_version' => ['系统'],
            // 仅"电池健康度"占用 battery 数值键;"电池维修/电池情况/电池循环次数"等不再抢键(走 f{N} 普通检测项)
            'battery'        => ['电池健康度', '电池健康'],
        ];
        foreach ($rules as $key => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_strpos($fieldName, $kw) !== false) {
                    return $key;
                }
            }
        }
        return '';
    }

    private function buildCompactSchema(array $items, array &$optCache, int $now): array
    {
        $groups = [];
        $groupIndex = [];
        $fi = 0;
        $usedStable = [];

        foreach ($items as $it) {
            $fi++;
            $g = $it['group'] !== '' ? $it['group'] : '检测项';
            if (!isset($groupIndex[$g])) {
                $nextGroupSort = count($groups) + 1;
                $groupIndex[$g] = count($groups);
                $groups[] = [
                    'id' => 0,
                    'group_key' => 'g' . $nextGroupSort,
                    'group_name' => $g,
                    'description' => '',
                    'sort' => $nextGroupSort,
                    'status' => 1,
                    'fields' => [],
                ];
            }

            $stable = $this->mapFieldKey($it['field']);
            $fieldKey = ($stable !== '' && !isset($usedStable[$stable])) ? $stable : ('f' . $fi);
            if ($stable !== '') {
                $usedStable[$stable] = true;
            }
            $isBatteryHealth = ($fieldKey === 'battery');
            $field = [
                'id' => 0,
                'field_key' => $fieldKey,
                'field_name' => $it['field'],
                'component' => $isBatteryHealth ? 'number' : 'radio',
                'selection_mode' => $isBatteryHealth ? '' : 'single',
                'unit' => $isBatteryHealth ? '%' : '',
                'placeholder' => '',
                'default_value' => '',
                'is_required' => 0,
                'is_show' => 1,
                'seller_visible' => 1,
                'buyer_visible' => 0,
                'result_visible' => 1,
                'result_template' => '',
                'api_fill_enabled' => $isBatteryHealth ? 1 : 0,
                'api_fill_policy' => $isBatteryHealth ? 'overwrite' : 'empty_only',
                'sort' => $fi,
                'extra_config' => [],
                'options' => [],
            ];
            if (!$isBatteryHealth) {
                $oi = 0;
                foreach ($it['opts'] as $o) {
                    $oi++;
                    $field['options'][] = [
                        'id' => 0,
                        'name' => $o,
                        'label' => $o,
                        'value' => (string)$oi,
                        'is_default' => ($o === $it['default'] ? 1 : 0),
                        'severity' => $this->resolveOptionSeverity($optCache, $o, $now),
                        'sort' => $oi,
                        'extra_config' => [],
                    ];
                }
            }
            $groups[$groupIndex[$g]]['fields'][] = $field;
        }

        $infoIndex = 0;
        foreach ($groups as $index => $group) {
            if (mb_strpos((string)$group['group_name'], '基本') !== false) {
                $infoIndex = $index;
                break;
            }
        }
        $sort = count($items);
        $groups[$infoIndex]['fields'][] = $this->compactInputField('warranty_info', '保修', ++$sort, '点「查保修」自动回填', '保修: {value}', 1);
        $groups[$infoIndex]['fields'][] = $this->compactRadioField('package_type', '包装', ++$sort, ['全套', '单机', '带配件']);
        $groups[$infoIndex]['fields'][] = $this->compactRadioField('condition_grade', '成色等级', ++$sort, ['10新', '99新', '98新', '95新', '9新', '85新']);

        foreach ($groups as $gIndex => &$group) {
            $group['id'] = -($gIndex + 1);
            foreach ($group['fields'] as $fIndex => &$field) {
                $field['id'] = -(($gIndex + 1) * 10000 + $fIndex + 1);
                foreach ($field['options'] as $oIndex => &$option) {
                    $option['id'] = -(($gIndex + 1) * 1000000 + ($fIndex + 1) * 1000 + $oIndex + 1);
                }
                unset($option);
            }
            unset($field);
        }
        unset($group);

        return ['version' => 1, 'storage' => 'compact', 'groups' => $groups];
    }

    private function compactInputField(string $key, string $name, int $sort, string $placeholder = '', string $resultTemplate = '', int $apiFill = 0): array
    {
        return [
            'id' => 0, 'field_key' => $key, 'field_name' => $name, 'component' => 'input',
            'selection_mode' => '', 'unit' => '', 'placeholder' => $placeholder, 'default_value' => '',
            'is_required' => 0, 'is_show' => 1, 'seller_visible' => 1, 'buyer_visible' => 0,
            'result_visible' => 1, 'result_template' => $resultTemplate,
            'api_fill_enabled' => $apiFill, 'api_fill_policy' => $apiFill ? 'overwrite' : 'empty_only',
            'sort' => $sort, 'extra_config' => [], 'options' => [],
        ];
    }

    private function compactRadioField(string $key, string $name, int $sort, array $labels): array
    {
        $field = [
            'id' => 0, 'field_key' => $key, 'field_name' => $name, 'component' => 'radio',
            'selection_mode' => 'single', 'unit' => '', 'placeholder' => '', 'default_value' => '',
            'is_required' => 0, 'is_show' => 1, 'seller_visible' => 1, 'buyer_visible' => 0,
            'result_visible' => 1, 'result_template' => '', 'api_fill_enabled' => 0,
            'api_fill_policy' => 'empty_only', 'sort' => $sort, 'extra_config' => [], 'options' => [],
        ];
        foreach ($labels as $index => $label) {
            $field['options'][] = [
                'id' => 0, 'name' => $label, 'label' => $label, 'value' => (string)($index + 1),
                'is_default' => 0, 'severity' => 'normal', 'sort' => $index + 1, 'extra_config' => [],
            ];
        }
        return $field;
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

    private function modelHasTemplateBinding(int $nodeId): bool
    {
        return (int)Db::name('recycle_template_binding')
            ->where('site_id', $this->site_id)
            ->where('target_type', 'model_dict')
            ->where('target_id', $nodeId)
            ->where('scene_key', self::BIND_SCENE)
            ->where('check_template_id', '>', 0)
            ->where('status', 1)
            ->value('id') > 0;
    }

    private function normalizeImportMode(string $mode): string
    {
        return $mode === 'overwrite' ? 'overwrite' : 'append';
    }

    private function resolveImportModeFromBatch(string $fileName): string
    {
        $parts = explode('|', $fileName);
        return $this->normalizeImportMode((string)($parts[2] ?? 'append'));
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

    private function encodeJson(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '{}';
    }

    private function ensureCompactColumns(): void
    {
        $prefix = (string)config('database.connections.mysql.prefix');
        $table = $prefix . 'recycle_check_template';
        if (!$this->hasColumn($table, 'schema_hash')) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `schema_hash` varchar(32) NOT NULL DEFAULT '' COMMENT '紧凑模板结构hash' AFTER `version`");
        }
        if (!$this->hasColumn($table, 'schema_json')) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `schema_json` longtext NULL COMMENT '导入模板紧凑结构JSON' AFTER `schema_hash`");
        }

        $batchTable = $prefix . 'recycle_check_import_batch';
        if (!$this->hasColumn($batchTable, 'skipped_user')) {
            Db::execute("ALTER TABLE `{$batchTable}` ADD COLUMN `skipped_user` int NOT NULL DEFAULT 0 COMMENT '跳过用户已有配置' AFTER `skipped_same`");
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return !empty(Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'"));
    }

    private function countDataRows(string $path): int
    {
        if ($this->isXlsxFile($path)) {
            $reader = $this->openXlsxXmlReader($path, $this->firstXlsxWorksheetPath($path));
            $rows = 0;
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
                    $rows = max($rows + 1, (int)$reader->getAttribute('r'));
                }
            }
            $reader->close();
            return max($rows - 1, 0);
        }

        if ($this->isSpreadsheetFile($path)) {
            $reader = IOFactory::createReaderForFile($path);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($path);
            $rows = max(0, (int)$spreadsheet->getSheet(0)->getHighestDataRow() - 1);
            $spreadsheet->disconnectWorksheets();
            return $rows;
        }

        $rows = 0;
        $fh = fopen($path, 'r');
        if (!$fh) {
            return 0;
        }
        while (fgetcsv($fh) !== false) {
            $rows++;
        }
        fclose($fh);
        return max($rows - 1, 0);
    }

    private function isSpreadsheetFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xls', 'xlsx'], true);
    }

    private function isXlsxFile(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx';
    }

    private function isHeaderRow(array $row): bool
    {
        return $this->normalizeHeader((string)($row[0] ?? '')) === '型号'
            && $this->normalizeHeader((string)($row[2] ?? '')) === '检测项';
    }

    private function normalizeHeader(string $value): string
    {
        return trim(str_replace(["\xEF\xBB\xBF", "\r", "\n", ' '], '', $value));
    }

    /** 只预读到首条有效检测项；不写业务数据，也不把级别标注表当检测模板导入。 */
    private function validateImportFile(string $path): void
    {
        $headerSeen = false;
        $checkRow = function (array $row) use (&$headerSeen): bool {
            if (trim(implode('', array_map('strval', $row))) === '') {
                return false;
            }
            if (!$headerSeen) {
                $header = array_map(fn($value) => $this->normalizeHeader((string)$value), $row);
                if (in_array('系统选项ID', $header, true) || in_array('建议级别', $header, true)) {
                    throw new CommonException('这是“质检选项级别”表，请到“选项级别”页面导入标注结果；检测目录需要“型号、产品ID、检测项、分类、默认选项、全部选项”六列表。');
                }
                if (array_slice($header, 0, 6) !== ['型号', '产品ID', '检测项', '分类', '默认选项', '全部选项']) {
                    throw new CommonException('检测表格式不匹配：请将数据放在首个工作表，前六列表头依次为“型号、产品ID、检测项、分类、默认选项、全部选项”。');
                }
                $headerSeen = true;
                return false;
            }
            return !$this->isHeaderRow($row) && trim((string)($row[0] ?? '')) !== '' && trim((string)($row[2] ?? '')) !== '';
        };
        if ($this->isXlsxFile($path)) {
            $strings = $this->readXlsxSharedStrings($path);
            $reader = $this->openXlsxXmlReader($path, $this->firstXlsxWorksheetPath($path));
            try {
                while ($reader->read()) {
                    if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row'
                        && $checkRow($this->parseXlsxRow($reader->readOuterXml(), $strings))) {
                        return;
                    }
                }
            } finally {
                $reader->close();
            }
        } elseif ($this->isSpreadsheetFile($path)) {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            try {
                $sheet = $spreadsheet->getSheet(0);
                for ($line = 1; $line <= $sheet->getHighestDataRow(); $line++) {
                    $row = [];
                    for ($col = 1; $col <= 6; $col++) {
                        $row[] = (string)$sheet->getCell([$col, $line])->getFormattedValue();
                    }
                    if ($checkRow($row)) {
                        return;
                    }
                }
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } else {
            $fh = fopen($path, 'r');
            if (!$fh) {
                throw new CommonException('无法读取导入文件');
            }
            try {
                while (($row = fgetcsv($fh)) !== false) {
                    if ($checkRow($row)) {
                        return;
                    }
                }
            } finally {
                fclose($fh);
            }
        }
        throw new CommonException('首个工作表中没有可导入的检测项：需要表头和至少一行同时填写“型号”“检测项”的数据；空表、说明页不能导入。');
    }

    /** 按工作簿关系找到首个工作表，不假定它一定叫 sheet1.xml。 */
    private function firstXlsxWorksheetPath(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new CommonException('无法打开 Excel 文件，请确认文件未损坏');
        }
        try {
            $workbook = @simplexml_load_string((string)$zip->getFromName('xl/workbook.xml'), \SimpleXMLElement::class, LIBXML_NONET);
            $relations = @simplexml_load_string((string)$zip->getFromName('xl/_rels/workbook.xml.rels'), \SimpleXMLElement::class, LIBXML_NONET);
            $sheetIds = $workbook !== false ? ($workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"][1]/@*[local-name()="id"]') ?: []) : [];
            foreach ($relations !== false ? ($relations->xpath('/*/*[local-name()="Relationship"]') ?: []) : [] as $relation) {
                if ((string)$relation['Id'] !== (string)($sheetIds[0] ?? '') || (string)$relation['TargetMode'] === 'External') {
                    continue;
                }
                $target = (string)$relation['Target'];
                $innerPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
                if ($zip->locateName($innerPath) !== false) {
                    return $innerPath;
                }
            }
        } finally {
            $zip->close();
        }
        throw new CommonException('无法定位 Excel 的首个工作表，请重新另存为 xlsx 后上传');
    }

    private function openXlsxXmlReader(string $path, string $innerPath): XMLReader
    {
        $reader = new XMLReader();
        $uri = 'zip://' . $path . '#' . $innerPath;
        if (!$reader->open($uri, null, LIBXML_COMPACT | LIBXML_NONET)) {
            throw new CommonException('无法读取 Excel 文件内容：' . $innerPath);
        }
        return $reader;
    }

    private function readXlsxSharedStrings(string $path): array
    {
        $strings = [];
        // inlineStr 文件可以合法地没有共享字符串表，不能把缺失可选文件当解析异常。
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new CommonException('无法打开 Excel 文件，请确认文件未损坏');
        }
        $hasStrings = $zip->locateName('xl/sharedStrings.xml') !== false;
        $zip->close();
        if (!$hasStrings) {
            return $strings;
        }
        $reader = new XMLReader();
        $uri = 'zip://' . $path . '#xl/sharedStrings.xml';
        if (!$reader->open($uri, null, LIBXML_COMPACT | LIBXML_NONET)) {
            return $strings;
        }
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                continue;
            }
            $xml = $reader->readOuterXml();
            $node = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
            if ($node === false) {
                $strings[] = '';
                continue;
            }
            $texts = $node->xpath('.//*[local-name()="t"]') ?: [];
            $value = '';
            foreach ($texts as $text) {
                $value .= (string)$text;
            }
            $strings[] = $value;
        }
        $reader->close();
        return $strings;
    }

    private function parseXlsxRow(string $rowXml, array $sharedStrings): array
    {
        $row = array_fill(0, 6, '');
        $xml = simplexml_load_string($rowXml, \SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) {
            return $row;
        }
        // WPS/第三方导出可能使用 x:row/x:c 命名空间，children() 会读不到任何单元格。
        foreach ($xml->xpath('./*[local-name()="c"]') ?: [] as $cell) {
            $ref = (string)($cell['r'] ?? '');
            $col = $this->xlsxColumnIndex($ref);
            if ($col < 1 || $col > 6) {
                continue;
            }
            $type = (string)($cell['t'] ?? '');
            $values = $cell->xpath('./*[local-name()="v"]') ?: [];
            $value = '';
            if ($type === 's') {
                $idx = (int)($values[0] ?? -1);
                $value = (string)($sharedStrings[$idx] ?? '');
            } elseif ($type === 'inlineStr') {
                $texts = $cell->xpath('.//*[local-name()="t"]') ?: [];
                foreach ($texts as $text) {
                    $value .= (string)$text;
                }
            } else {
                $value = (string)($values[0] ?? '');
            }
            $row[$col - 1] = trim($value);
        }
        return $row;
    }

    private function xlsxColumnIndex(string $cellRef): int
    {
        if (!preg_match('/^([A-Z]+)/i', $cellRef, $match)) {
            return 0;
        }
        $letters = strtoupper($match[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index;
    }

    /**
     * 成色等级字典(扁平),格式"码|名"。导入时确保存在,幂等;用户改过的(is_user_modified=1)不动。
     * 存进 recycle_check_dict(dict_type=grade),供质检/定价给设备标成色等级,可往下游 condition_grade 流。
     */
    public function seedGrades(): int
    {
        $grades = ['10新|全套', '99新|单机', '98新|微瑕', '95新|小花', '9新|磕碰划痕', '85新|硬划磕伤'];
        $now = time();
        $added = 0;
        foreach ($grades as $i => $text) {
            $exists = Db::name('recycle_check_dict')
                ->where('site_id', $this->site_id)->where('dict_type', 'grade')->where('text', $text)->find();
            if ($exists) {
                continue;
            }
            Db::name('recycle_check_dict')->insert([
                'site_id' => $this->site_id, 'dict_type' => 'grade', 'text' => $text,
                'severity' => 'normal', 'is_user_modified' => 0, 'sort' => $i,
                'create_at' => $now, 'update_at' => $now,
            ]);
            $added++;
        }
        return $added;
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
