<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\admin;

use addon\recycle_quote_spider\app\model\QuoteImportTask;
use addon\recycle_quote_spider\app\model\QuoteItem;
use addon\recycle_quote_spider\app\model\QuoteRow;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class QuoteImportService extends BaseAdminService
{
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
        $headers = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $value = trim((string)$sheet->getCell($col . $headerRow)->getFormattedValue());
            if ($value !== '') {
                $headers[$col] = $value;
            }
        }

        $rows = [];
        for ($row = $headerRow + 1; $row <= min($highestRow, $headerRow + $previewCount); $row++) {
            $rowData = [];
            foreach ($headers as $col => $header) {
                $rowData[$header] = $sheet->getCell($col . $row)->getFormattedValue();
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
        $task = $this->model->where('site_id', $this->site_id)->where('id', $taskId)->findOrEmpty()->toArray();
        if (empty($task)) {
            throw new CommonException('导入任务不存在');
        }

        $sourceId = (int)($data['source_id'] ?? $task['source_id'] ?? 0);
        $categoryId = (int)($data['category_id'] ?? 0);
        if ($sourceId <= 0) {
            throw new CommonException('请选择报价源');
        }
        if ($categoryId <= 0 && empty($data['item_id'])) {
            throw new CommonException('请选择分类');
        }

        $mapping = $data['mapping'] ?? ($task['mapping'] ?? []);
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true) ?: [];
        }
        $parsed = $this->parseRows((string)$task['file_path'], (string)($data['sheet_name'] ?? ''), (int)($data['header_row'] ?? 1), $mapping);
        if (empty($parsed['rows'])) {
            throw new CommonException('没有可导入的报价行');
        }

        $itemId = (int)($data['item_id'] ?? 0);
        if ($itemId <= 0) {
            $itemName = trim((string)($data['item_name'] ?? ''));
            if ($itemName === '') {
                $itemName = $task['file_name'] ?: '手工报价单';
            }
            $item = (new QuoteItem())->create([
                'site_id' => $this->site_id,
                'source_id' => $sourceId,
                'category_id' => $categoryId,
                'source_item_id' => 'manual_import_' . uniqid('', true),
                'brand' => (string)($data['brand'] ?? ''),
                'tab' => (string)($data['tab'] ?? ''),
                'name' => $itemName,
                'quote_type' => 'manual_excel',
                'is_image_quote' => 0,
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
            $itemModel->where('site_id', $this->site_id)->where('id', $itemId)->update([
                'source_id' => $sourceId,
                'category_id' => $categoryId > 0 ? $categoryId : (int)$oldItem['category_id'],
                'brand' => (string)($data['brand'] ?? ''),
                'tab' => (string)($data['tab'] ?? ''),
                'quote_type' => 'manual_excel',
                'is_image_quote' => 0,
                'follow_source' => 0,
                'columns' => $parsed['price_columns'],
                'last_sync_at' => time(),
            ]);
        }

        if (($data['mode'] ?? 'replace') === 'replace') {
            (new QuoteRow())->where('site_id', $this->site_id)->where('item_id', $itemId)->delete();
        }

        $rowModel = new QuoteRow();
        foreach ($parsed['rows'] as $index => $row) {
            $prices = $row['prices'];
            $rowModel->create([
                'site_id' => $this->site_id,
                'source_id' => $sourceId,
                'item_id' => $itemId,
                'source_row_id' => 'manual_import_' . md5($row['model_name'] . '#' . $index),
                'brand' => $row['brand'] ?: (string)($data['brand'] ?? ''),
                'tab' => $row['tab'] ?: (string)($data['tab'] ?? ''),
                'model_name' => $row['model_name'],
                'columns' => $parsed['price_columns'],
                'source_prices' => $prices,
                'manual_prices' => $prices,
                'final_prices' => $prices,
                'remark' => $row['remark'],
                'source_is_show' => 1,
                'is_show' => 1,
                'sort' => $index,
                'follow_source' => 0,
                'adjust_type' => 0,
                'adjust_value' => 0,
                'adjust_ratio' => 1,
                'round_mode' => 'round',
                'raw_data' => $row['raw'],
                'source_hash' => md5(json_encode($row, JSON_UNESCAPED_UNICODE)),
            ]);
        }

        $this->model->where('site_id', $this->site_id)->where('id', $taskId)->update([
            'source_id' => $sourceId,
            'status' => 1,
            'row_count' => count($parsed['rows']),
            'mapping' => $mapping,
            'message' => '已导入并覆盖报价项',
        ]);

        return [
            'item_id' => $itemId,
            'rows' => count($parsed['rows']),
            'price_columns' => $parsed['price_columns'],
        ];
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
        $headers = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $value = trim((string)$sheet->getCell($col . $headerRow)->getFormattedValue());
            if ($value !== '') {
                $headers[$col] = $value;
            }
        }

        $fieldByHeader = [];
        foreach ($headers as $index => $header) {
            $mapKey = array_search($header, array_values($headers), true);
            $fieldByHeader[$header] = $mapping[$mapKey] ?? '';
        }
        $priceColumns = [];
        foreach ($fieldByHeader as $header => $field) {
            if (is_string($field) && str_starts_with($field, 'price:')) {
                $priceColumns[] = substr($field, 6) ?: $header;
            }
        }
        if (empty($priceColumns)) {
            throw new CommonException('没有识别到价格列');
        }

        $rows = [];
        for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
            $raw = [];
            foreach ($headers as $col => $header) {
                $raw[$header] = trim((string)$sheet->getCell($col . $rowNumber)->getFormattedValue());
            }
            if (empty(array_filter($raw, fn($value) => $value !== ''))) {
                continue;
            }

            $row = ['model_name' => '', 'brand' => '', 'tab' => '', 'remark' => '', 'prices' => [], 'raw' => $raw];
            foreach ($raw as $header => $value) {
                $field = $fieldByHeader[$header] ?? '';
                if ($field === 'model_name') {
                    $row['model_name'] = $value;
                } elseif ($field === 'brand') {
                    $row['brand'] = $value;
                } elseif ($field === 'tab') {
                    $row['tab'] = $value;
                } elseif ($field === 'remark') {
                    $row['remark'] = $value;
                } elseif (is_string($field) && str_starts_with($field, 'price:')) {
                    $row['prices'][] = $value;
                }
            }
            if ($row['model_name'] === '') {
                continue;
            }
            $rows[] = $row;
        }

        return ['price_columns' => $priceColumns, 'rows' => $rows];
    }

    private function suggestMapping(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $index => $header) {
            $name = trim((string)$header);
            if (str_contains($name, '品牌')) {
                $mapping[$index] = 'brand';
            } elseif (str_contains($name, '分类')) {
                $mapping[$index] = 'category';
            } elseif (str_contains($name, '分组') || str_contains($name, '标签') || strtolower($name) === 'tab') {
                $mapping[$index] = 'tab';
            } elseif (str_contains($name, '型号') || str_contains($name, '名称') || str_contains($name, '机型')) {
                $mapping[$index] = 'model_name';
            } elseif (str_contains($name, '备注')) {
                $mapping[$index] = 'remark';
            } elseif (preg_match('/价|靓机|小花|内爆|外爆|开机|不开机|废板/u', $name)) {
                $mapping[$index] = 'price:' . $name;
            }
        }
        return $mapping;
    }
}
