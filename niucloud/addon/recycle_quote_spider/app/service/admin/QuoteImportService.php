<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\admin;

use addon\recycle_quote_spider\app\model\QuoteImportTask;
use addon\recycle_quote_spider\app\model\QuoteItem;
use addon\recycle_quote_spider\app\model\QuoteRow;
use addon\recycle_quote_spider\app\service\core\QuoteApiCacheService;
use addon\recycle_quote_spider\app\service\core\QuotePriceHistoryService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class QuoteImportService extends BaseAdminService
{
    private const DEFAULT_NOTICE_TEXT = '温馨提示：报价仅供参考，最终价格以质检结果为准';

    public function __construct()
    {
        parent::__construct();
        $this->model = new QuoteImportTask();
    }

    public function upload($file, int $sourceId = 0): array
    {
        if (!$file || !$file->isValid()) {
            throw new CommonException('文件上传失败');
        }
        $extension = strtolower($file->getOriginalExtension());
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            throw new CommonException('只支持上传 xls/xlsx 文件');
        }
        if ($file->getSize() > 20 * 1024 * 1024) {
            throw new CommonException('文件大小不能超过20MB');
        }

        $originalName = $file->getOriginalName();
        $fileName = date('Y/m/d/') . uniqid('quote_', true) . '.' . $extension;
        $saveDir = public_path() . 'upload/recycle_quote_spider/' . date('Y/m/d/');
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }
        $file->move($saveDir, basename($fileName));
        $filePath = 'upload/recycle_quote_spider/' . $fileName;
        $sheets = $this->getSheets($filePath);

        $task = $this->model->create([
            'site_id' => $this->site_id,
            'source_id' => $sourceId,
            'file_name' => $originalName,
            'file_path' => $filePath,
            'status' => 0,
            'sheet_count' => count($sheets),
            'message' => '已上传，待解析确认',
        ]);

        return [
            'task_id' => $task->id,
            'file_name' => $originalName,
            'file_path' => $filePath,
            'sheets' => $sheets,
        ];
    }

    public function preview(array $data): array
    {
        $filePath = (string)($data['file_path'] ?? '');
        if ($filePath === '') {
            $task = $this->model->where('site_id', $this->site_id)->where('id', (int)($data['task_id'] ?? 0))->findOrEmpty()->toArray();
            $filePath = (string)($task['file_path'] ?? '');
        }
        if ($filePath === '' || !is_file(public_path() . $filePath)) {
            throw new CommonException('Excel文件不存在');
        }

        $sheetName = (string)($data['sheet_name'] ?? '');
        $headerRow = max(1, (int)($data['header_row'] ?? 1));
        $previewCount = max(1, min(100, (int)($data['preview_count'] ?? 20)));

        $spreadsheet = IOFactory::load(public_path() . $filePath);
        $sheet = $sheetName !== '' ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();
        if (!$sheet) {
            throw new CommonException('找不到指定工作表');
        }

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        [$headerRow, $noticeText] = $this->resolveHeaderRowAndNotice($sheet, $headerRow, $highestRow);
        $headers = [];
        $headerColumns = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $value = trim((string)$sheet->getCell($col . $headerRow)->getFormattedValue());
            if ($value !== '') {
                $headers[$col] = $value;
                $headerColumns[] = [
                    'col' => $col,
                    'label' => $value,
                ];
            }
        }

        $rows = [];
        for ($row = $headerRow + 1; $row <= min($highestRow, $headerRow + $previewCount); $row++) {
            $rowData = [];
            foreach ($headerColumns as $header) {
                $rowData[$header['label']] = $sheet->getCell($header['col'] . $row)->getFormattedValue();
            }
            if (!empty(array_filter($rowData, fn($value) => $value !== null && $value !== ''))) {
                $rows[] = ['row_number' => $row, 'data' => $rowData];
            }
        }

        $result = [
            'sheet_name' => $sheet->getTitle(),
            'headers' => array_values($headers),
            'rows' => $rows,
            'total_rows' => max(0, $highestRow - $headerRow),
            'suggested_mapping' => $this->suggestMapping(array_values($headers)),
            'notice_text' => $noticeText,
        ];

        if (!empty($data['task_id'])) {
            $this->model->where('site_id', $this->site_id)->where('id', (int)$data['task_id'])->update([
                'row_count' => $result['total_rows'],
                'preview_data' => $result,
                'mapping' => $result['suggested_mapping'],
            ]);
        }
        return $result;
    }

    public function getPage(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $search = $this->model
            ->withSearch(['site_id', 'source_id', 'status'], $where)
            ->order('id desc');
        return $this->pageQuery($search);
    }

    public function confirm(array $data): array
    {
        $taskId = (int)($data['task_id'] ?? 0);
        $task = $this->getTaskOrFail($taskId);

        $sourceId = (int)($data['source_id'] ?? $task['source_id'] ?? 0);
        $categoryId = (int)($data['category_id'] ?? 0);
        if ($sourceId <= 0) {
            throw new CommonException('请选择报价源');
        }
        if ($categoryId <= 0 && empty($data['item_id'])) {
            throw new CommonException('请选择分类');
        }

        $result = $this->importSheetToItem((string)$task['file_path'], [
            'source_id' => $sourceId,
            'category_id' => $categoryId,
            'item_id' => (int)($data['item_id'] ?? 0),
            'item_name' => (string)($data['item_name'] ?? ''),
            'tab' => (string)($data['tab'] ?? ''),
            'brand' => (string)($data['brand'] ?? ''),
            'notice_text' => $data['notice_text'] ?? '',
            'sheet_name' => (string)($data['sheet_name'] ?? ''),
            'header_row' => (int)($data['header_row'] ?? 1),
            'mapping' => $this->normalizeMapping($data['mapping'] ?? ($task['mapping'] ?? [])),
            'mode' => $data['mode'] ?? 'replace',
            'fallback_item_name' => $task['file_name'] ?: '手工报价单',
        ]);

        $this->model->where('site_id', $this->site_id)->where('id', $taskId)->update([
            'source_id' => $sourceId,
            'status' => 1,
            'row_count' => (int)$result['rows'],
            'message' => '已导入并覆盖报价项',
        ]);

        $this->refreshApiCache();
        return $result;
    }

    /**
     * 按品牌批量导入:一张多工作表的 Excel,每个工作表(系列)分流到该分类下的一个报价项。
     * sheets: [{ sheet_name, item_id(0=新建同名报价项), item_name?, tab?, brand?, enabled? }]
     */
    public function batchConfirm(array $data): array
    {
        $taskId = (int)($data['task_id'] ?? 0);
        $task = $this->getTaskOrFail($taskId);

        $sourceId = (int)($data['source_id'] ?? $task['source_id'] ?? 0);
        $categoryId = (int)($data['category_id'] ?? 0);
        if ($sourceId <= 0) {
            throw new CommonException('请选择报价源');
        }
        if ($categoryId <= 0) {
            throw new CommonException('请选择分类');
        }

        $sheets = $data['sheets'] ?? [];
        if (is_string($sheets)) {
            $sheets = json_decode($sheets, true) ?: [];
        }
        if (!is_array($sheets) || empty($sheets)) {
            throw new CommonException('请至少选择一个工作表进行导入');
        }

        $noticeText = $data['notice_text'] ?? '';
        $mode = $data['mode'] ?? 'replace';
        $defaultBrand = (string)($data['brand'] ?? '');
        $headerRow = (int)($data['header_row'] ?? 1);

        $results = [];
        $totalRows = 0;
        $okCount = 0;
        foreach ($sheets as $sheet) {
            $sheetName = trim((string)($sheet['sheet_name'] ?? ''));
            if ($sheetName === '') {
                continue;
            }
            if (array_key_exists('enabled', $sheet) && !$sheet['enabled']) {
                continue;
            }
            try {
                $res = $this->importSheetToItem((string)$task['file_path'], [
                    'source_id' => $sourceId,
                    'category_id' => $categoryId,
                    'item_id' => (int)($sheet['item_id'] ?? 0),
                    'item_name' => (string)($sheet['item_name'] ?? $sheetName),
                    'tab' => (string)($sheet['tab'] ?? ''),
                    'brand' => (string)($sheet['brand'] ?? $defaultBrand),
                    'notice_text' => $noticeText,
                    'sheet_name' => $sheetName,
                    'header_row' => $headerRow,
                    'mapping' => [],
                    'mode' => $mode,
                    'fallback_item_name' => $sheetName,
                ]);
                $totalRows += (int)$res['rows'];
                $okCount++;
                $results[] = array_merge($res, ['sheet_name' => $sheetName, 'success' => true, 'message' => '']);
            } catch (\Throwable $e) {
                $results[] = [
                    'sheet_name' => $sheetName,
                    'item_id' => (int)($sheet['item_id'] ?? 0),
                    'rows' => 0,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $this->model->where('site_id', $this->site_id)->where('id', $taskId)->update([
            'source_id' => $sourceId,
            'status' => 1,
            'row_count' => $totalRows,
            'message' => "批量导入完成:{$okCount} 个工作表 / {$totalRows} 行",
        ]);

        $this->refreshApiCache();
        return [
            'total_rows' => $totalRows,
            'sheet_count' => $okCount,
            'sheets' => $results,
        ];
    }

    /**
     * 把单个工作表导入到指定(或新建)报价项。confirm / batchConfirm 共用。
     * 不在此处刷新缓存 / 更新任务状态,由调用方统一处理。
     */
    private function importSheetToItem(string $filePath, array $opts): array
    {
        $sourceId = (int)$opts['source_id'];
        $categoryId = (int)$opts['category_id'];

        $parsed = $this->parseRows($filePath, (string)($opts['sheet_name'] ?? ''), (int)($opts['header_row'] ?? 1), $opts['mapping'] ?? []);
        if (empty($parsed['rows'])) {
            throw new CommonException('没有可导入的报价行');
        }
        $noticeText = $this->resolveNoticeText($opts['notice_text'] ?? '', $parsed['notice_text'] ?? '');
        $importBrand = $this->normalizeImportFallback((string)($opts['brand'] ?? ''), ['分组', '系列']);
        if ($importBrand === '') {
            $importBrand = $this->firstParsedValue($parsed['rows'], 'brand');
        }

        $tab = (string)($opts['tab'] ?? '');
        $itemId = (int)($opts['item_id'] ?? 0);
        $itemName = trim((string)($opts['item_name'] ?? ''));
        if ($itemId <= 0) {
            if ($itemName === '') {
                $itemName = (string)($opts['fallback_item_name'] ?? '手工报价单');
            }
            $item = (new QuoteItem())->create([
                'site_id' => $this->site_id,
                'source_id' => $sourceId,
                'category_id' => $categoryId,
                'source_item_id' => 'manual_import_' . uniqid('', true),
                'brand' => $importBrand,
                'tab' => $tab,
                'name' => $itemName,
                'quote_type' => 'manual_excel',
                'is_image_quote' => 0,
                'notice_text' => $noticeText,
                'is_show' => 1,
                'is_hot' => 0,
                'follow_source' => 0,
                'columns' => $parsed['price_columns'],
                'raw_data' => ['manual_import' => true],
                'source_hash' => md5($itemName . microtime(true)),
                'last_sync_at' => time(),
            ]);
            $itemId = (int)$item->id;
        } else {
            $itemModel = new QuoteItem();
            $oldItem = $itemModel->where('site_id', $this->site_id)->where('id', $itemId)->findOrEmpty()->toArray();
            if (empty($oldItem)) {
                throw new CommonException('报价项不存在');
            }
            $itemBrand = $importBrand !== ''
                ? $importBrand
                : $this->normalizeImportFallback((string)($oldItem['brand'] ?? ''), ['分组', '系列']);
            // tab 留空时保留报价项原有系列名,避免批量覆盖把已有 tab 清空
            $itemTab = $tab !== '' ? $tab : (string)($oldItem['tab'] ?? '');
            $itemName = $itemName !== '' ? $itemName : (string)($oldItem['name'] ?? '');
            $itemModel->where('site_id', $this->site_id)->where('id', $itemId)->update([
                'source_id' => $sourceId,
                'category_id' => $categoryId > 0 ? $categoryId : (int)$oldItem['category_id'],
                'brand' => $itemBrand,
                'tab' => $itemTab,
                'quote_type' => 'manual_excel',
                'is_image_quote' => 0,
                'notice_text' => $noticeText,
                'follow_source' => 0,
                'columns' => $parsed['price_columns'],
                'last_sync_at' => time(),
            ]);
        }

        // 重复导入/每天覆盖时,按「型号+容量」稳定标识原地更新同一行、保留行 id,
        // 保证价格历史(按 row_id 关联)跨天可对比,不会因删后重建换 id 而丢失昨天数据。
        $rowModel = new QuoteRow();
        $history = new QuotePriceHistoryService();
        $existing = $rowModel->field('id,source_row_id,model_name,raw_data')
            ->where('site_id', $this->site_id)->where('item_id', $itemId)->select()->toArray();
        $byKey = [];       // source_row_id => id
        $byModelCap = [];  // model|capacity => id(旧数据/首次切换兜底匹配)
        foreach ($existing as $ex) {
            if (!empty($ex['source_row_id'])) {
                $byKey[(string)$ex['source_row_id']] = (int)$ex['id'];
            }
            $rawEx = $ex['raw_data'];
            if (is_string($rawEx)) {
                $rawEx = json_decode($rawEx, true) ?: [];
            }
            $capEx = is_array($rawEx) ? (string)($rawEx['capacity'] ?? '') : '';
            $byModelCap[(string)$ex['model_name'] . '|' . $capEx] = (int)$ex['id'];
        }

        $usedKeys = [];
        $keptIds = [];
        foreach ($parsed['rows'] as $index => $row) {
            $prices = $row['prices'];
            $capacity = (string)($row['raw']['capacity'] ?? '');
            $baseKey = $row['model_name'] . '#' . $capacity;
            $key = $baseKey;
            $dup = 1;
            while (isset($usedKeys[$key])) {
                $key = $baseKey . '#' . (++$dup);
            }
            $usedKeys[$key] = true;
            $sourceRowId = 'manual_import_' . md5($key);

            $payload = [
                'brand' => $row['brand'] ?: $importBrand,
                'tab' => $row['tab'] ?: $tab,
                'model_name' => $row['model_name'],
                'columns' => $parsed['price_columns'],
                'source_prices' => $prices,
                'manual_prices' => $prices,
                'final_prices' => $prices,
                'remark' => $row['remark'],
                'sort' => $index,
                'raw_data' => $row['raw'],
                'source_hash' => md5(json_encode($row, JSON_UNESCAPED_UNICODE)),
            ];

            $rid = $byKey[$sourceRowId] ?? ($byModelCap[$row['model_name'] . '|' . $capacity] ?? 0);
            if ($rid > 0 && !in_array($rid, $keptIds, true)) {
                $rowModel->where('site_id', $this->site_id)->where('id', $rid)->update(array_merge($payload, [
                    'source_id' => $sourceId,
                    'source_row_id' => $sourceRowId,
                ]));
            } else {
                $record = $rowModel->create(array_merge($payload, [
                    'site_id' => $this->site_id,
                    'source_id' => $sourceId,
                    'item_id' => $itemId,
                    'source_row_id' => $sourceRowId,
                    'source_is_show' => 1,
                    'is_show' => 1,
                    'follow_source' => 0,
                    'adjust_type' => 0,
                    'adjust_value' => 0,
                    'adjust_ratio' => 1,
                    'round_mode' => 'round',
                ]));
                $rid = (int)$record->id;
            }
            $keptIds[] = $rid;

            $history->record($this->site_id, [
                'id' => $rid,
                'item_id' => $itemId,
                'source_id' => $sourceId,
                'model_name' => $row['model_name'],
                'columns' => $parsed['price_columns'],
                'final_prices' => $prices,
            ]);
        }

        // 覆盖模式:删除本次表里已不存在的旧型号行;仍在的行原地更新保留 id
        if (($opts['mode'] ?? 'replace') === 'replace') {
            $allIds = array_map(fn($e) => (int)$e['id'], $existing);
            $toDelete = array_values(array_diff($allIds, $keptIds));
            if (!empty($toDelete)) {
                $rowModel->where('site_id', $this->site_id)->where('item_id', $itemId)->whereIn('id', $toDelete)->delete();
            }
        }

        return [
            'item_id' => $itemId,
            'item_name' => $itemName,
            'rows' => count($parsed['rows']),
            'price_columns' => $parsed['price_columns'],
        ];
    }

    private function getTaskOrFail(int $taskId): array
    {
        $task = $this->model->where('site_id', $this->site_id)->where('id', $taskId)->findOrEmpty()->toArray();
        if (empty($task)) {
            throw new CommonException('导入任务不存在');
        }
        return $task;
    }

    private function normalizeMapping($mapping): array
    {
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true) ?: [];
        }
        return is_array($mapping) ? $mapping : [];
    }

    private function refreshApiCache(): void
    {
        (new QuoteApiCacheService())->refresh($this->site_id);
    }

    private function getSheets(string $filePath): array
    {
        $spreadsheet = IOFactory::load(public_path() . $filePath);
        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheets[] = [
                'name' => $sheet->getTitle(),
                'row_count' => $sheet->getHighestRow(),
                'column_count' => $sheet->getHighestColumn(),
            ];
        }
        return $sheets;
    }

    private function resolveHeaderRowAndNotice($sheet, int $headerRow, int $highestRow): array
    {
        $noticeLabel = trim((string)$sheet->getCell('A' . $headerRow)->getFormattedValue());
        if ($noticeLabel === '报价提示' && $headerRow < $highestRow) {
            $noticeText = $this->normalizeNoticeText((string)$sheet->getCell('B' . $headerRow)->getFormattedValue());
            return [$headerRow + 1, $noticeText];
        }
        return [$headerRow, ''];
    }

    private function parseRows(string $filePath, string $sheetName, int $headerRow, array $mapping): array
    {
        if ($filePath === '' || !is_file(public_path() . $filePath)) {
            throw new CommonException('Excel文件不存在');
        }
        $spreadsheet = IOFactory::load(public_path() . $filePath);
        $sheet = $sheetName !== '' ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();
        if (!$sheet) {
            throw new CommonException('找不到指定工作表');
        }

        $headerRow = max(1, $headerRow);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        [$headerRow, $firstRowNoticeText] = $this->resolveHeaderRowAndNotice($sheet, $headerRow, $highestRow);
        $headers = [];
        $headerColumns = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $value = trim((string)$sheet->getCell($col . $headerRow)->getFormattedValue());
            if ($value !== '') {
                $headers[$col] = $value;
                $headerColumns[] = [
                    'col' => $col,
                    'label' => $value,
                    'field' => $this->resolveMappingField($mapping, count($headerColumns), $value),
                ];
            }
        }

        // 兜底:未识别且非元信息(型号/容量/备注等)的列,若整列大多为数字则当作价格列,
        // 避免「充新/大花」之外的新等级词漏掉(图片转表格、自定义表头都适用)
        foreach ($headerColumns as &$header) {
            if (($header['field'] ?? '') !== '') {
                continue;
            }
            if ($this->isMetaOnlyHeader($header['label'])) {
                continue;
            }
            if ($this->columnLooksNumeric($sheet, $header['col'], $headerRow, $highestRow)) {
                $header['field'] = 'price:' . $header['label'];
            }
        }
        unset($header);

        $priceColumns = [];
        foreach ($headerColumns as $header) {
            $field = $header['field'];
            if (is_string($field) && str_starts_with($field, 'price:')) {
                $priceColumns[] = substr($field, 6) ?: $header['label'];
            }
        }
        if (empty($priceColumns)) {
            throw new CommonException('没有识别到价格列');
        }

        $rows = [];
        for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
            $raw = [];
            foreach ($headerColumns as $header) {
                $raw[$header['label']] = trim((string)$sheet->getCell($header['col'] . $rowNumber)->getFormattedValue());
            }
            if (empty(array_filter($raw, fn($value) => $value !== ''))) {
                continue;
            }

            $row = ['model_name' => '', 'brand' => '', 'tab' => '', 'remark' => '', 'notice_text' => '', 'prices' => [], 'raw' => $raw];
            foreach ($headerColumns as $header) {
                $value = trim((string)$sheet->getCell($header['col'] . $rowNumber)->getFormattedValue());
                $field = $header['field'] ?? '';
                if ($field === 'model_name') {
                    $row['model_name'] = $value;
                } elseif ($field === 'brand') {
                    $row['brand'] = $value;
                } elseif ($field === 'tab') {
                    $row['tab'] = $value;
                } elseif ($field === 'remark') {
                    $row['remark'] = $value;
                } elseif ($field === 'notice_text') {
                    $row['notice_text'] = $value;
                } elseif ($field === 'capacity_name') {
                    $row['raw']['capacity_name'] = $value;
                    $row['raw']['capacity'] = $value;
                } elseif (is_string($field) && str_starts_with($field, 'price:')) {
                    $row['prices'][] = $value;
                }
            }
            if ($row['model_name'] === '') {
                continue;
            }
            $rows[] = $row;
        }

        $noticeText = $firstRowNoticeText;
        foreach ($rows as $row) {
            if ($noticeText !== '') {
                break;
            }
            $noticeText = $this->normalizeNoticeText($row['notice_text'] ?? '');
            if ($noticeText !== '') {
                break;
            }
        }

        return ['price_columns' => $priceColumns, 'rows' => $rows, 'notice_text' => $noticeText];
    }

    private function resolveMappingField(array $mapping, int $index, string $header): string
    {
        $inferredField = $this->inferHeaderField($header);
        if ($inferredField !== '') {
            return $inferredField;
        }
        if ($this->isMetaOnlyHeader($header)) {
            return '';
        }

        $field = $mapping[$index] ?? '';
        if (is_string($field) && $field !== '') {
            return $field;
        }

        return '';
    }

    private function suggestMapping(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $index => $header) {
            $field = $this->inferHeaderField((string)$header);
            if ($field !== '') {
                $mapping[$index] = $field;
            }
        }
        return $mapping;
    }

    /**
     * 采样某列的数据单元格,判断是否为「数字列」(用于价格列兜底识别)
     */
    private function columnLooksNumeric($sheet, string $col, int $headerRow, int $highestRow): bool
    {
        $total = 0;
        $numeric = 0;
        for ($row = $headerRow + 1; $row <= $highestRow && $total < 12; $row++) {
            $value = trim((string)$sheet->getCell($col . $row)->getFormattedValue());
            if ($value === '') {
                continue;
            }
            $total++;
            $normalized = str_replace([',', '，', '￥', '¥', ' '], '', $value);
            if (is_numeric($normalized)) {
                $numeric++;
            }
        }
        return $total >= 2 && ($numeric / $total) >= 0.6;
    }

    private function inferHeaderField(string $header): string
    {
        $name = trim($header);
        if ($name === '') {
            return '';
        }
        if (str_contains($name, '品牌')) {
            return 'brand';
        }
        if (str_contains($name, '分类')) {
            return 'category';
        }
        if (str_contains($name, '分组') || str_contains($name, '系列') || str_contains($name, '标签') || strtolower($name) === 'tab') {
            return 'tab';
        }
        if (str_contains($name, '型号') || str_contains($name, '名称') || str_contains($name, '机型')) {
            return 'model_name';
        }
        if (preg_match('/报价提示|提示文案|详情提示|温馨提示|notice_text|notice|tips/i', $name)) {
            return 'notice_text';
        }
        if ($this->isMetaOnlyHeader($name)) {
            return 'capacity_name';
        }
        if (str_contains($name, '备注')) {
            return 'remark';
        }
        if (preg_match('/价|充新|准新|靓机|小花|大花|微瑕|内爆|外爆|开机|不开机|废板/u', $name)) {
            return 'price:' . $name;
        }
        return '';
    }

    private function isMetaOnlyHeader(string $header): bool
    {
        return preg_match('/内存|容量|规格|存储|memory|capacity|storage|rom/i', trim($header)) === 1;
    }

    private function normalizeImportFallback(string $value, array $blockedKeywords): string
    {
        $value = trim($value);
        foreach ($blockedKeywords as $keyword) {
            if ($keyword !== '' && str_contains($value, $keyword)) {
                return '';
            }
        }
        return $value;
    }

    private function firstParsedValue(array $rows, string $field): string
    {
        foreach ($rows as $row) {
            $value = trim((string)($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function resolveNoticeText($formValue, $excelValue): string
    {
        $formText = $this->normalizeNoticeText($formValue);
        if ($formText !== '') {
            return $formText;
        }
        $excelText = $this->normalizeNoticeText($excelValue);
        return $excelText !== '' ? $excelText : self::DEFAULT_NOTICE_TEXT;
    }

    private function normalizeNoticeText($value): string
    {
        return str_replace(["\r\n", "\r"], "\n", trim((string)$value));
    }
}
