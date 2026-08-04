<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\check;

use addon\hsx_recycle\app\model\check\RecycleCheckDict;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;
use think\facade\Log;

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
        $confirmStatus = (string)($where['confirm_status'] ?? '');
        if ($confirmStatus === 'pending') {
            $query->where('is_user_modified', '=', 0);
        } elseif ($confirmStatus === 'confirmed') {
            $query->where('is_user_modified', '=', 1);
        }
        $page = $this->pageQuery($query);
        $rows = (array)($page['data'] ?? []);
        if ($rows) {
            $contextMap = $this->optionContextMap(array_column($rows, 'text'));
            foreach ($rows as &$row) {
                $row['context'] = $contextMap[mb_strtolower(trim((string)$row['text']))] ?? '';
                $row['confirm_status'] = (int)($row['is_user_modified'] ?? 0) === 1 ? 'confirmed' : 'pending';
            }
            unset($row);
            $page['data'] = $rows;
        }
        return $page;
    }

    public function summary(): array
    {
        $rows = $this->baseQuery()->field('severity, count(*) as cnt')->group('severity')->select()->toArray();
        $map = ['normal' => 0, 'general' => 0, 'abnormal' => 0, 'pending' => 0, 'confirmed' => 0];
        foreach ($rows as $r) {
            $map[$r['severity']] = (int)$r['cnt'];
        }
        $map['pending'] = (int)$this->baseQuery()->where('is_user_modified', '=', 0)->count();
        $map['confirmed'] = (int)$this->baseQuery()->where('is_user_modified', '=', 1)->count();
        return $map;
    }

    /**
     * 导出当前站点全部质检选项，交给人工或外部工具离线标注。
     * 系统选项 ID + 选项文本共同校验，避免错行覆盖。
     */
    public function exportFile(): string
    {
        $rows = $this->baseQuery()->field('id,text,severity,is_user_modified,update_at')->order('id asc')->select()->toArray();
        if (!$rows) {
            throw new CommonException('暂无可导出的质检选项，请先导入质检模板');
        }
        $contextMap = $this->optionContextMap(array_column($rows, 'text'), true);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('级别标注');
        $headers = ['系统选项ID', '质检项上下文', '选项文本', '当前级别', '建议级别', '确认状态', '标注备注'];
        $sheet->fromArray($headers, null, 'A1');
        $line = 2;
        foreach ($rows as $row) {
            $confirmed = (int)($row['is_user_modified'] ?? 0) === 1;
            $sheet->fromArray([
                (int)$row['id'],
                $contextMap[mb_strtolower(trim((string)$row['text']))] ?? '',
                (string)$row['text'],
                $this->severityLabel((string)$row['severity']),
                $confirmed ? $this->severityLabel((string)$row['severity']) : '',
                $confirmed ? '已确认' : '待确认',
                '',
            ], null, 'A' . $line);
            $line++;
        }

        $lastRow = max(2, $line - 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G' . $lastRow);
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF315BE8');
        $sheet->getStyle('A1:G' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B2:C' . $lastRow)->getAlignment()->setWrapText(true);
        foreach (['A' => 15, 'B' => 52, 'C' => 32, 'D' => 14, 'E' => 16, 'F' => 16, 'G' => 38] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        // 一列共用一个校验规则，避免几千行分别创建对象导致 PHP 内存暴涨。
        $severityValidation = (new DataValidation())
            ->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)->setShowErrorMessage(true)->setShowDropDown(true)
            ->setErrorTitle('级别不正确')->setError('请选择：正常、一般、异常')
            ->setFormula1('"正常,一般,异常"')->setSqref('E2:E' . $lastRow);
        $sheet->setDataValidation('E2', $severityValidation);
        $confirmValidation = (new DataValidation())
            ->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(false)->setShowErrorMessage(true)->setShowDropDown(true)
            ->setErrorTitle('确认状态不正确')->setError('请选择：待确认、已确认')
            ->setFormula1('"待确认,已确认"')->setSqref('F2:F' . $lastRow);
        $sheet->setDataValidation('F2', $confirmValidation);

        $help = $spreadsheet->createSheet();
        $help->setTitle('填写说明');
        $help->fromArray([
            ['质检选项级别协作说明'],
            ['1. “系统选项ID、质检项上下文、选项文本、当前级别”用于校验，请勿修改。'],
            ['2. 由人工或 Codex 在“建议级别”填写：正常、一般、异常。'],
            ['3. 人工复核无误后将“确认状态”改为“已确认”；待确认行不会导入。'],
            ['4. 导入前系统会展示新增修改、无变化、待确认和错误数量，确认后才更新。'],
            ['5. 当前级别字典为全站实时数据源，确认更新后订单、ERP 和商城质检展示会使用新级别。'],
        ], null, 'A1');
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $help->getColumnDimension('A')->setWidth(110);
        $help->getStyle('A1:A6')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $directory = $this->tempDirectory();
        $path = $directory . DIRECTORY_SEPARATOR . '质检选项级别_' . $this->site_id . '_' . date('Ymd_His') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return $path;
    }

    /** 上传 Excel 并生成一次性预检令牌，不直接修改业务数据。 */
    public function previewImport($file): array
    {
        if (!$file || !$file->isValid()) {
            throw new CommonException('Excel 文件上传失败');
        }
        $ext = strtolower((string)$file->getOriginalExtension());
        if (!in_array($ext, ['xls', 'xlsx'], true)) {
            throw new CommonException('仅支持 xls 或 xlsx 文件');
        }
        if ((int)$file->getSize() > 10 * 1024 * 1024) {
            throw new CommonException('Excel 文件不能超过 10MB');
        }
        try {
            $reader = IOFactory::createReaderForFile($file->getPathname());
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly('级别标注');
            }
            $spreadsheet = $reader->load($file->getPathname());
            $sheet = $spreadsheet->getSheetByName('级别标注') ?: $spreadsheet->getActiveSheet();
            $matrix = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable $e) {
            throw new CommonException('Excel 解析失败：' . $e->getMessage());
        }
        if (count($matrix) < 2) {
            throw new CommonException('Excel 中没有可导入的质检选项');
        }
        if (count($matrix) > 20001) {
            throw new CommonException('单次最多处理 20000 个质检选项');
        }
        $header = array_map(static fn($value) => trim((string)$value), (array)$matrix[0]);
        $required = ['系统选项ID', '选项文本', '建议级别', '确认状态'];
        $indexes = [];
        foreach ($required as $name) {
            $index = array_search($name, $header, true);
            if ($index === false) {
                throw new CommonException('Excel 缺少必要列：' . $name);
            }
            $indexes[$name] = (int)$index;
        }

        $ids = [];
        foreach (array_slice($matrix, 1) as $row) {
            $id = (int)($row[$indexes['系统选项ID']] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $dbRows = $ids ? $this->baseQuery()->whereIn('id', array_values(array_unique($ids)))
            ->field('id,text,severity,is_user_modified')->select()->toArray() : [];
        $dbMap = [];
        foreach ($dbRows as $dbRow) {
            $dbMap[(int)$dbRow['id']] = $dbRow;
        }

        $seen = [];
        $changes = [];
        $previewRows = [];
        $errors = [];
        $summary = ['total' => 0, 'will_update' => 0, 'unchanged' => 0, 'pending' => 0, 'errors' => 0];
        foreach (array_slice($matrix, 1) as $offset => $row) {
            $excelLine = $offset + 2;
            $id = (int)($row[$indexes['系统选项ID']] ?? 0);
            $text = trim((string)($row[$indexes['选项文本']] ?? ''));
            $suggested = trim((string)($row[$indexes['建议级别']] ?? ''));
            $confirm = trim((string)($row[$indexes['确认状态']] ?? ''));
            if ($id <= 0 && $text === '' && $suggested === '' && $confirm === '') {
                continue;
            }
            $summary['total']++;
            $error = '';
            if ($id <= 0) {
                $error = '系统选项ID无效';
            } elseif (isset($seen[$id])) {
                $error = '系统选项ID重复';
            } elseif (!isset($dbMap[$id])) {
                $error = '本站不存在该质检选项';
            } elseif (trim((string)$dbMap[$id]['text']) !== $text) {
                $error = '选项文本与系统记录不一致，请重新导出';
            }
            $seen[$id] = true;
            $severity = $this->normalizeSeverity($suggested);
            if ($error === '' && !in_array($confirm, ['待确认', '已确认'], true)) {
                $error = '确认状态必须是待确认或已确认';
            }
            if ($error === '' && $confirm !== '已确认') {
                $summary['pending']++;
                if (count($previewRows) < 100) {
                    $previewRows[] = ['line' => $excelLine, 'id' => $id, 'text' => $text, 'old' => $dbMap[$id]['severity'] ?? '', 'new' => '', 'status' => 'pending', 'message' => '尚未人工确认'];
                }
                continue;
            }
            if ($error === '' && $severity === '') {
                $error = '建议级别必须是正常、一般或异常';
            }
            if ($error !== '') {
                $summary['errors']++;
                if (count($errors) < 200) {
                    $errors[] = ['line' => $excelLine, 'id' => $id, 'text' => $text, 'message' => $error];
                }
                continue;
            }
            $old = (string)$dbMap[$id]['severity'];
            $changed = $old !== $severity || (int)($dbMap[$id]['is_user_modified'] ?? 0) !== 1;
            if ($changed) {
                $summary['will_update']++;
                $changes[] = ['id' => $id, 'text' => $text, 'old' => $old, 'new' => $severity, 'line' => $excelLine];
            } else {
                $summary['unchanged']++;
            }
            if (count($previewRows) < 100) {
                $previewRows[] = ['line' => $excelLine, 'id' => $id, 'text' => $text, 'old' => $old, 'new' => $severity, 'status' => $changed ? 'update' : 'unchanged', 'message' => $changed ? '等待确认更新' : '级别未变化'];
            }
        }

        $token = bin2hex(random_bytes(20));
        $payload = [
            'site_id' => (int)$this->site_id,
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'expires_at' => time() + 1800,
            'summary' => $summary,
            'changes' => $changes,
            'errors' => $errors,
        ];
        $written = file_put_contents($this->previewPath($token), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        if ($written === false) {
            throw new CommonException('预检结果保存失败，请稍后重试');
        }
        return ['token' => $token, 'summary' => $summary, 'rows' => $previewRows, 'errors' => $errors, 'expires_in' => 1800];
    }

    /** 用户确认预检结果后原子更新字典；失败不会产生部分更新。 */
    public function confirmImport(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            throw new CommonException('预检令牌无效，请重新上传');
        }
        $path = $this->previewPath($token);
        if (!is_file($path)) {
            throw new CommonException('预检结果已失效，请重新上传');
        }
        $payload = json_decode((string)file_get_contents($path), true);
        if (!is_array($payload) || (int)($payload['site_id'] ?? 0) !== (int)$this->site_id || (int)($payload['operator_uid'] ?? 0) !== (int)$this->uid) {
            throw new CommonException('预检结果与当前操作人不匹配');
        }
        if ((int)($payload['expires_at'] ?? 0) < time()) {
            @unlink($path);
            throw new CommonException('预检结果已过期，请重新上传');
        }
        if (!empty($payload['errors'])) {
            throw new CommonException('Excel 仍有错误，请修正后重新上传');
        }
        $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
        $updated = 0;
        Db::startTrans();
        try {
            foreach ($changes as $change) {
                $id = (int)($change['id'] ?? 0);
                $text = trim((string)($change['text'] ?? ''));
                $severity = (string)($change['new'] ?? '');
                if ($id <= 0 || $text === '' || !in_array($severity, self::SEVERITIES, true)) {
                    throw new CommonException('预检数据异常，请重新上传');
                }
                $affected = Db::name('recycle_check_dict')
                    ->where([['site_id', '=', $this->site_id], ['id', '=', $id], ['dict_type', '=', 'option'], ['text', '=', $text]])
                    ->update(['severity' => $severity, 'is_user_modified' => 1, 'update_at' => time()]);
                if ($affected < 1) {
                    throw new CommonException('质检选项已发生变化，请重新导出后处理：' . $text);
                }
                // 保持规范化模板选项快照一致；紧凑模板和所有下游仍以字典为唯一事实源。
                Db::name('recycle_check_option')->where([['site_id', '=', $this->site_id], ['option_label', '=', $text]])
                    ->update(['severity' => $severity, 'update_at' => time()]);
                $updated++;
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e instanceof CommonException ? $e : new CommonException($e->getMessage());
        }
        @unlink($path);
        Log::record('【质检选项级别导入】' . json_encode([
            'site_id' => $this->site_id,
            'operator_uid' => $this->uid,
            'operator_name' => $this->username,
            'updated' => $updated,
            'changes' => array_slice($changes, 0, 100),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'info');
        return ['updated' => $updated, 'unchanged' => (int)($payload['summary']['unchanged'] ?? 0), 'pending' => (int)($payload['summary']['pending'] ?? 0)];
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

    private function severityLabel(string $severity): string
    {
        return $severity === 'abnormal' ? '异常' : ($severity === 'general' ? '一般' : '正常');
    }

    private function normalizeSeverity(string $severity): string
    {
        $value = mb_strtolower(trim($severity));
        return match ($value) {
            '正常', 'normal' => 'normal',
            '一般', 'general' => 'general',
            '异常', 'abnormal' => 'abnormal',
            default => '',
        };
    }

    /** 按选项文本汇总模板/分组/质检项上下文，供人工判断，不参与更新匹配。 */
    private function optionContextMap(array $texts = [], bool $includeCompact = false): array
    {
        $filter = [];
        foreach ($texts as $text) {
            $key = mb_strtolower(trim((string)$text));
            if ($key !== '') {
                $filter[$key] = true;
            }
        }
        $contexts = [];
        $append = static function (array &$target, string $label, string $context) use ($filter): void {
            $key = mb_strtolower(trim($label));
            if ($key === '' || ($filter && !isset($filter[$key])) || $context === '') {
                return;
            }
            $target[$key] ??= [];
            if (!in_array($context, $target[$key], true) && count($target[$key]) < 5) {
                $target[$key][] = $context;
            }
        };

        $normalized = Db::name('recycle_check_option')->alias('o')
            ->leftJoin('recycle_check_field f', 'f.id=o.field_id')
            ->leftJoin('recycle_check_group g', 'g.id=f.group_id')
            ->leftJoin('recycle_check_template t', 't.id=f.template_id')
            ->where('o.site_id', '=', $this->site_id)
            ->when($filter, static function ($query) use ($texts) {
                $query->whereIn('o.option_label', array_values(array_unique(array_filter(array_map('strval', $texts)))));
            })
            ->field('o.option_label,f.field_name,g.group_name,t.template_name')->select()->toArray();
        foreach ($normalized as $row) {
            $parts = array_values(array_filter([(string)($row['template_name'] ?? ''), (string)($row['group_name'] ?? ''), (string)($row['field_name'] ?? '')]));
            $append($contexts, (string)($row['option_label'] ?? ''), implode(' / ', $parts));
        }

        // 大批量导出时补读紧凑模板；列表分页不扫描全部 JSON，避免为了展示上下文拖慢页面。
        if ($includeCompact) {
            // PDO 的 cursor 在部分环境仍会缓冲完整结果；按主键小批读取，防止大量 schema_json 撑满内存。
            $lastTemplateId = 0;
            do {
                $templates = Db::name('recycle_check_template')->where('site_id', '=', $this->site_id)
                    ->where('id', '>', $lastTemplateId)->where('schema_json', '<>', '')
                    ->field('id,template_name,schema_json')->order('id asc')->limit(20)->select()->toArray();
                foreach ($templates as $template) {
                    $lastTemplateId = max($lastTemplateId, (int)($template['id'] ?? 0));
                    $schema = json_decode((string)($template['schema_json'] ?? ''), true);
                    foreach ((array)($schema['groups'] ?? []) as $group) {
                        foreach ((array)($group['fields'] ?? []) as $field) {
                            $parts = array_values(array_filter([(string)($template['template_name'] ?? ''), (string)($group['group_name'] ?? ''), (string)($field['field_name'] ?? '')]));
                            $context = implode(' / ', $parts);
                            foreach ((array)($field['options'] ?? []) as $option) {
                                $append($contexts, (string)($option['label'] ?? ($option['name'] ?? '')), $context);
                            }
                        }
                    }
                    unset($schema);
                }
                $batchCount = count($templates);
                unset($templates);
            } while ($batchCount === 20);
        }
        $result = [];
        foreach ($contexts as $key => $items) {
            $result[$key] = implode('；', $items);
        }
        return $result;
    }

    private function tempDirectory(): string
    {
        $directory = runtime_path() . 'temp' . DIRECTORY_SEPARATOR . 'hsx_recycle' . DIRECTORY_SEPARATOR . 'check_severity';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new CommonException('临时目录创建失败');
        }
        foreach ((array)glob($directory . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_file($file) && (int)filemtime($file) < time() - 7200) {
                @unlink($file);
            }
        }
        return $directory;
    }

    private function previewPath(string $token): string
    {
        return $this->tempDirectory() . DIRECTORY_SEPARATOR . 'preview_' . $this->site_id . '_' . $token . '.json';
    }
}
