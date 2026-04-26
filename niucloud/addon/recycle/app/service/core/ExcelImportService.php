<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core;

use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel导入核心服务
 * Class ExcelImportService
 * @package addon\recycle\app\service\core
 */
class ExcelImportService extends BaseCoreService
{
    /**
     * @var int
     */
    protected $site_id = 0;

    /**
     * @var MergedCellProcessor
     */
    protected $mergedCellProcessor;

    /**
     * 支持的文件格式
     * @var array
     */
    protected $allowedTypes = ['xlsx', 'xls', 'csv'];

    /**
     * 最大文件大小（字节）
     * @var int
     */
    protected $maxFileSize = 10 * 1024 * 1024; // 10MB

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->mergedCellProcessor = new MergedCellProcessor();
    }

    /**
     * 验证Excel文件
     * @param string $filePath
     * @return array
     */
    public function validateFile(string $filePath): array
    {
        try {
            // 检查文件是否存在
            if (!file_exists($filePath)) {
                return fail('文件不存在');
            }

            // 检查文件大小
            $fileSize = filesize($filePath);
            if ($fileSize > $this->maxFileSize) {
                return fail('文件大小超过限制，最大支持10MB');
            }

            // 检查文件类型
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowedTypes)) {
                return fail('不支持的文件格式，仅支持：' . implode(', ', $this->allowedTypes));
            }

            // 尝试读取文件
            $spreadsheet = IOFactory::load($filePath);
            $sheetsCount = $spreadsheet->getSheetCount();

            return success('文件验证通过', [
                'file_size' => $fileSize,
                'file_type' => $extension,
                'sheets_count' => $sheetsCount,
                'sheets_names' => $this->getSheetNames($spreadsheet)
            ]);

        } catch (\Exception $e) {
            Log::error('Excel文件验证失败：' . $e->getMessage());
            return fail('文件验证失败：' . $e->getMessage());
        }
    }

    /**
     * 获取工作表名称列表
     * @param Spreadsheet $spreadsheet
     * @return array
     */
    protected function getSheetNames(Spreadsheet $spreadsheet): array
    {
        $names = [];
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $names[] = $worksheet->getTitle();
        }
        return $names;
    }

    /**
     * 检测合并单元格
     * @param Worksheet $worksheet
     * @return array
     */
    public function detectMergedCells(Worksheet $worksheet): array
    {
        $mergedCells = [];
        $mergedRanges = $worksheet->getMergeCells();
        
        foreach ($mergedRanges as $range) {
            [$startCell, $endCell] = explode(':', $range);
            $startCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($startCell);
            $endCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($endCell);
            
            $mergedCells[] = [
                'range' => $range,
                'start_row' => $startCoord[1],
                'end_row' => $endCoord[1],
                'start_col' => $startCoord[0],
                'end_col' => $endCoord[0],
                'span_rows' => $endCoord[1] - $startCoord[1] + 1,
                'span_cols' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($endCoord[0]) - 
                             \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startCoord[0]) + 1
            ];
        }
        
        return $mergedCells;
    }

    /**
     * 智能填充合并单元格数据
     * @param array $rows
     * @param array $mergedCells
     * @return array
     */
    public function fillMergedCellData(array $rows, array $mergedCells): array
    {
        foreach ($mergedCells as $merged) {
            $masterValue = null;
            
            // 找到合并区域的主值
            foreach ($rows as $rowIndex => $row) {
                if ($row['row_number'] == $merged['start_row']) {
                    $masterValue = $row['data'][$merged['start_col']] ?? '';
                    break;
                }
            }
            
            // 填充合并区域内的所有单元格
            if ($masterValue !== null) {
                foreach ($rows as &$row) {
                    if ($row['row_number'] >= $merged['start_row'] && 
                        $row['row_number'] <= $merged['end_row']) {
                        
                        for ($col = $merged['start_col']; $col <= $merged['end_col']; $col++) {
                            if (empty($row['data'][$col])) {
                                $row['data'][$col] = $masterValue;
                            }
                        }
                    }
                }
            }
        }
        
        return $rows;
    }

    /**
     * 获取支持的文件类型
     * @return array
     */
    public function getSupportedTypes(): array
    {
        return $this->allowedTypes;
    }

    /**
     * 获取最大文件大小
     * @return int
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * 高级Excel文件解析（支持复杂合并单元格）
     * @param string $filePath
     * @param array $options
     * @return array
     */
    public function advancedParseExcelFile(string $filePath, array $options = []): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $result = [
                'total_sheets' => $spreadsheet->getSheetCount(),
                'sheets' => [],
                'processing_summary' => []
            ];

            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $sheetName = $worksheet->getTitle();
                
                // 跳过指定的工作表
                if (isset($options['skip_sheets']) && in_array($sheetName, $options['skip_sheets'])) {
                    continue;
                }

                Log::info("开始解析工作表: {$sheetName}");
                
                $sheetData = $this->advancedParseWorksheet($worksheet, $options);
                $result['sheets'][$sheetName] = $sheetData;
                
                // 收集处理摘要
                $result['processing_summary'][$sheetName] = [
                    'merged_ranges' => count($sheetData['merged_cells']),
                    'data_rows' => $sheetData['data_rows'],
                    'filled_cells' => $this->mergedCellProcessor->getProcessStats()['filled_cells'] ?? 0,
                    'issues_count' => count($sheetData['merge_issues'] ?? [])
                ];
            }

            return success('高级Excel解析成功', $result);

        } catch (\Exception $e) {
            Log::error('高级Excel文件解析失败：' . $e->getMessage());
            return fail('Excel解析失败：' . $e->getMessage());
        }
    }

    /**
     * 高级工作表解析
     * @param Worksheet $worksheet
     * @param array $options
     * @return array
     */
    protected function advancedParseWorksheet(Worksheet $worksheet, array $options = []): array
    {
        $sheetName = $worksheet->getTitle();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // 使用高级合并单元格检测
        $mergedCells = $this->mergedCellProcessor->advancedDetectMergedCells($worksheet);
        
        // 识别标题行和表头行
        $headerInfo = $this->detectAdvancedHeaders($worksheet, $options);
        
        // 解析数据行
        $rows = [];
        $dataStartRow = $headerInfo['data_start_row'] ?? 3;
        
        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $rowData = $this->parseRowAdvanced($worksheet, $row, $highestColumn);
            
            // 跳过空行
            if ($this->isEmptyRow($rowData)) {
                continue;
            }
            
            $rows[] = [
                'row_number' => $row,
                'data' => $rowData,
                'is_merged' => $this->isRowWithMergedCells($row, $mergedCells)
            ];
        }

        // 使用智能填充处理合并单元格
        $fillOptions = [
            'fill_strategy' => $options['merge_fill_strategy'] ?? 'smart',
            'preserve_original' => $options['preserve_original'] ?? true,
            'log_details' => $options['log_merge_details'] ?? false
        ];
        
        $rows = $this->mergedCellProcessor->smartFillMergedCellData($rows, $mergedCells, $fillOptions);
        
        // 检测合并单元格问题
        $mergeIssues = $this->mergedCellProcessor->detectMergedCellIssues($rows, $mergedCells);
        
        // 生成处理报告
        $mergeReport = $this->mergedCellProcessor->generateMergedCellReport($mergedCells, $mergeIssues);

        return [
            'sheet_name' => $sheetName,
            'total_rows' => $highestRow,
            'data_rows' => count($rows),
            'highest_column' => $highestColumn,
            'merged_cells' => $mergedCells,
            'merge_issues' => $mergeIssues,
            'merge_report' => $mergeReport,
            'header_info' => $headerInfo,
            'rows' => $rows,
            'processing_stats' => $this->mergedCellProcessor->getProcessStats()
        ];
    }

    /**
     * 高级表头检测
     * @param Worksheet $worksheet
     * @param array $options
     * @return array
     */
    protected function detectAdvancedHeaders(Worksheet $worksheet, array $options = []): array
    {
        $titleRow = 1;
        $headerRow = 2;
        $dataStartRow = 3;
        
        // 自适应检测表头位置
        $maxDetectionRows = $options['max_header_detection_rows'] ?? 5;
        
        for ($row = 1; $row <= $maxDetectionRows; $row++) {
            $rowValues = $this->getRowValues($worksheet, $row);
            
            // 检测是否为表头行（包含标准字段）
            if ($this->isHeaderRow($rowValues)) {
                $headerRow = $row;
                $dataStartRow = $row + 1;
                break;
            }
        }
        
        // 读取表头和标题
        $titleText = '';
        if ($titleRow < $headerRow) {
            $titleValues = $this->getRowValues($worksheet, $titleRow);
            $titleText = trim($titleValues['A'] ?? '');
        }
        
        $headers = [];
        $headerValues = $this->getRowValues($worksheet, $headerRow);
        
        foreach ($headerValues as $col => $value) {
            if (!empty(trim($value))) {
                $headers[$col] = trim($value);
            }
        }
        
        return [
            'title_row' => $titleRow,
            'header_row' => $headerRow, 
            'data_start_row' => $dataStartRow,
            'title_text' => $titleText,
            'headers' => $headers,
            'header_mapping' => $this->mapHeaders($headers, $this->getExpectedHeaders()),
            'detection_method' => 'advanced'
        ];
    }

    /**
     * 判断是否为表头行
     * @param array $rowValues
     * @return bool
     */
    protected function isHeaderRow(array $rowValues): bool
    {
        $expectedHeaders = $this->getExpectedHeaders();
        $matchCount = 0;
        
        foreach ($rowValues as $value) {
            $trimmedValue = trim($value);
            if (in_array($trimmedValue, $expectedHeaders)) {
                $matchCount++;
            }
        }
        
        // 如果匹配到3个或以上预期表头，认为是表头行
        return $matchCount >= 3;
    }

    /**
     * 获取预期的表头字段
     * @return array
     */
    protected function getExpectedHeaders(): array
    {
        return [
            '型号', '机型', '设备型号', 'model',
            '网络型号', '网络', 'network',
            '容量', '存储', 'storage', 'capacity',
            '高保充新', '充新', '靓机', '小花', '大花', '外爆', '内爆',
            '备注', 'remarks', '说明'
        ];
    }

    /**
     * 高级行解析
     * @param Worksheet $worksheet
     * @param int $row
     * @param string $highestColumn
     * @return array
     */
    protected function parseRowAdvanced(Worksheet $worksheet, int $row, string $highestColumn): array
    {
        $rowData = [];
        
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $cell = $worksheet->getCell($col . $row);
            $cellValue = $cell->getValue();
            
            // 处理公式
            if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                try {
                    $cellValue = $cell->getCalculatedValue();
                } catch (\Exception $e) {
                    $cellValue = $cell->getValue(); // 如果计算失败，使用原始值
                    Log::warning("公式计算失败 {$col}{$row}: " . $e->getMessage());
                }
            }
            
            $rowData[$col] = $this->formatCellValue($cellValue);
        }
        
        return $rowData;
    }

    /**
     * 批量处理大型Excel文件
     * @param string $filePath
     * @param array $options
     * @return array
     */
    public function batchProcessLargeExcel(string $filePath, array $options = []): array
    {
        try {
            // 配置批量处理参数
            $batchOptions = [
                'batch_size' => $options['batch_size'] ?? 1000,
                'memory_limit' => $options['memory_limit'] ?? 256 * 1024 * 1024,
                'merge_fill_strategy' => $options['merge_fill_strategy'] ?? 'smart'
            ];
            
            $spreadsheet = IOFactory::load($filePath);
            $result = [
                'total_sheets' => $spreadsheet->getSheetCount(),
                'sheets' => [],
                'performance_stats' => [
                    'start_time' => microtime(true),
                    'peak_memory' => 0,
                    'processed_rows' => 0
                ]
            ];

            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $sheetName = $worksheet->getTitle();
                
                Log::info("开始批量处理工作表: {$sheetName}");
                
                $sheetResult = $this->batchProcessWorksheet($worksheet, $batchOptions);
                $result['sheets'][$sheetName] = $sheetResult;
                
                $result['performance_stats']['processed_rows'] += $sheetResult['data_rows'];
                $result['performance_stats']['peak_memory'] = max(
                    $result['performance_stats']['peak_memory'],
                    memory_get_peak_usage()
                );
            }
            
            $result['performance_stats']['end_time'] = microtime(true);
            $result['performance_stats']['total_time'] = $result['performance_stats']['end_time'] - $result['performance_stats']['start_time'];
            $result['performance_stats']['peak_memory_mb'] = round($result['performance_stats']['peak_memory'] / 1024 / 1024, 2);

            return success('批量处理完成', $result);

        } catch (\Exception $e) {
            Log::error('批量处理失败：' . $e->getMessage());
            return fail('批量处理失败：' . $e->getMessage());
        }
    }

    /**
     * 批量处理工作表
     * @param Worksheet $worksheet
     * @param array $options
     * @return array
     */
    protected function batchProcessWorksheet(Worksheet $worksheet, array $options): array
    {
        $sheetName = $worksheet->getTitle();
        $highestRow = $worksheet->getHighestRow();
        
        // 先检测合并单元格和表头
        $mergedCells = $this->mergedCellProcessor->advancedDetectMergedCells($worksheet);
        $headerInfo = $this->detectAdvancedHeaders($worksheet, $options);
        
        // 批量解析数据行
        $allRows = [];
        $dataStartRow = $headerInfo['data_start_row'] ?? 3;
        
        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $rowData = $this->parseRowAdvanced($worksheet, $row, $worksheet->getHighestColumn());
            
            if (!$this->isEmptyRow($rowData)) {
                $allRows[] = [
                    'row_number' => $row,
                    'data' => $rowData,
                    'is_merged' => $this->isRowWithMergedCells($row, $mergedCells)
                ];
            }
        }
        
        // 使用优化的合并单元格处理
        $allRows = $this->mergedCellProcessor->optimizedProcessMergedCells($allRows, $mergedCells, $options);
        
                 return [
             'sheet_name' => $sheetName,
             'total_rows' => $highestRow,
             'data_rows' => count($allRows),
             'merged_cells' => $mergedCells,
             'header_info' => $headerInfo,
             'rows' => $allRows,
             'processing_stats' => $this->mergedCellProcessor->getProcessStats()
         ];
     }

    /**
     * 映射表头（增强版）
     * @param array $actualHeaders
     * @param array $expectedHeaders
     * @return array
     */
    protected function mapHeaders(array $actualHeaders, array $expectedHeaders): array
    {
        $mapping = [];
        
        foreach ($actualHeaders as $col => $header) {
            $header = trim($header);
            
            // 精确匹配
            if (in_array($header, $expectedHeaders)) {
                $mapping[$col] = $header;
                continue;
            }
            
            // 模糊匹配（包含关系）
            foreach ($expectedHeaders as $expected) {
                if (stripos($header, $expected) !== false || stripos($expected, $header) !== false) {
                    $mapping[$col] = $expected;
                    break;
                }
            }
        }
        
        return $mapping;
    }
}