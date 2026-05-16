<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core;

use core\base\BaseCoreService;
use think\facade\Log;

/**
 * 合并单元格处理器
 * Class MergedCellProcessor
 * @package addon\recycle\app\service\core
 */
class MergedCellProcessor extends BaseCoreService
{
    /**
     * 合并单元格数据缓存
     * @var array
     */
    protected $mergedCellCache = [];

    /**
     * 处理合并单元格数据填充
     * @param array $rows
     * @param array $mergedCells
     * @return array
     */
    public function fillMergedCellData(array $rows, array $mergedCells): array
    {
        Log::info("开始处理合并单元格数据", [
            'rows_count' => count($rows),
            'merged_cells_count' => count($mergedCells)
        ]);

        // 为每个合并单元格区域填充数据
        foreach ($mergedCells as $merged) {
            $this->fillMergedRegion($rows, $merged);
        }

        return $rows;
    }

    /**
     * 填充特定合并区域的数据
     * @param array &$rows
     * @param array $merged
     */
    protected function fillMergedRegion(array &$rows, array $merged): void
    {
        $masterValue = null;
        $startRow = $merged['start_row'];
        $endRow = $merged['end_row'];
        $startCol = $merged['start_col'];
        $endCol = $merged['end_col'];

        // 查找主值（合并区域左上角的值）
        foreach ($rows as $rowIndex => $row) {
            if ($row['row_number'] == $startRow) {
                $masterValue = $row['data'][$startCol] ?? $merged['master_value'] ?? '';
                break;
            }
        }

        // 如果没有找到主值，使用合并单元格的主值
        if (empty($masterValue)) {
            $masterValue = $merged['master_value'] ?? '';
        }

        // 填充合并区域内的所有单元格
        if (!empty($masterValue)) {
            foreach ($rows as &$row) {
                if ($row['row_number'] >= $startRow && $row['row_number'] <= $endRow) {
                    // 填充该行的合并列范围
                    for ($col = $startCol; $col <= $endCol; $col++) {
                        if (empty($row['data'][$col])) {
                            $row['data'][$col] = $masterValue;
                        }
                    }
                }
            }
        }
    }

    /**
     * 智能检测和处理跨行数据
     * @param array $rows
     * @param array $mergedCells
     * @return array
     */
    public function smartFillSpanningData(array $rows, array $mergedCells): array
    {
        $processedRows = [];
        $spanningValues = [];

        foreach ($rows as $rowData) {
            $rowNumber = $rowData['row_number'];
            $processedRow = $rowData;

            // 检查每一列是否需要填充跨行数据
            foreach ($processedRow['data'] as $col => $value) {
                // 如果当前单元格为空，尝试从合并单元格或跨行缓存中获取值
                if (empty(trim($value))) {
                    $filledValue = $this->getSpanningValue($rowNumber, $col, $mergedCells, $spanningValues);
                    if (!empty($filledValue)) {
                        $processedRow['data'][$col] = $filledValue;
                    }
                } else {
                    // 如果当前单元格有值，更新跨行缓存
                    $spanningValues[$col] = $value;
                }
            }

            $processedRows[] = $processedRow;
        }

        return $processedRows;
    }

    /**
     * 获取跨行值
     * @param int $rowNumber
     * @param string $col
     * @param array $mergedCells
     * @param array $spanningValues
     * @return string
     */
    protected function getSpanningValue(int $rowNumber, string $col, array $mergedCells, array $spanningValues): string
    {
        // 首先检查是否在合并单元格范围内
        foreach ($mergedCells as $merged) {
            if ($rowNumber >= $merged['start_row'] && $rowNumber <= $merged['end_row'] &&
                $col >= $merged['start_col'] && $col <= $merged['end_col']) {
                return $merged['master_value'] ?? '';
            }
        }

        // 如果不在合并范围内，使用跨行缓存值
        return $spanningValues[$col] ?? '';
    }

    /**
     * 验证合并单元格数据的完整性
     * @param array $rows
     * @param array $mergedCells
     * @return array
     */
    public function validateMergedCellData(array $rows, array $mergedCells): array
    {
        $validationResult = [
            'is_valid' => true,
            'warnings' => [],
            'errors' => []
        ];

        foreach ($mergedCells as $merged) {
            $validation = $this->validateSingleMergedCell($rows, $merged);
            
            if (!$validation['is_valid']) {
                $validationResult['is_valid'] = false;
                $validationResult['errors'][] = $validation['error'];
            }

            if (!empty($validation['warnings'])) {
                $validationResult['warnings'] = array_merge($validationResult['warnings'], $validation['warnings']);
            }
        }

        return $validationResult;
    }

    /**
     * 验证单个合并单元格
     * @param array $rows
     * @param array $merged
     * @return array
     */
    protected function validateSingleMergedCell(array $rows, array $merged): array
    {
        $result = [
            'is_valid' => true,
            'warnings' => [],
            'error' => null
        ];

        $range = $merged['range'];
        $masterValue = $merged['master_value'] ?? '';

        // 检查合并区域是否有数据冲突
        $conflictingValues = [];
        
        foreach ($rows as $row) {
            if ($row['row_number'] >= $merged['start_row'] && 
                $row['row_number'] <= $merged['end_row']) {
                
                for ($col = $merged['start_col']; $col <= $merged['end_col']; $col++) {
                    $cellValue = $row['data'][$col] ?? '';
                    
                    if (!empty($cellValue) && $cellValue !== $masterValue) {
                        $conflictingValues[] = [
                            'row' => $row['row_number'],
                            'col' => $col,
                            'value' => $cellValue,
                            'expected' => $masterValue
                        ];
                    }
                }
            }
        }

        if (!empty($conflictingValues)) {
            $result['warnings'][] = "合并单元格 {$range} 存在数据冲突";
        }

        return $result;
    }

    /**
     * 获取合并单元格统计信息
     * @param array $mergedCells
     * @return array
     */
    public function getMergedCellStatistics(array $mergedCells): array
    {
        $stats = [
            'total_count' => count($mergedCells),
            'by_type' => [
                'horizontal' => 0, // 横向合并
                'vertical' => 0,   // 纵向合并
                'both' => 0        // 横纵向都合并
            ],
            'largest_span' => [
                'rows' => 0,
                'cols' => 0,
                'total_cells' => 0
            ]
        ];

        foreach ($mergedCells as $merged) {
            $rowSpan = $merged['end_row'] - $merged['start_row'] + 1;
            $colSpan = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($merged['end_col']) - 
                      \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($merged['start_col']) + 1;
            
            // 分类统计
            if ($rowSpan > 1 && $colSpan > 1) {
                $stats['by_type']['both']++;
            } elseif ($rowSpan > 1) {
                $stats['by_type']['vertical']++;
            } elseif ($colSpan > 1) {
                $stats['by_type']['horizontal']++;
            }

            // 记录最大跨度
            if ($rowSpan > $stats['largest_span']['rows']) {
                $stats['largest_span']['rows'] = $rowSpan;
            }
            if ($colSpan > $stats['largest_span']['cols']) {
                $stats['largest_span']['cols'] = $colSpan;
            }

            $totalCells = $rowSpan * $colSpan;
            if ($totalCells > $stats['largest_span']['total_cells']) {
                $stats['largest_span']['total_cells'] = $totalCells;
            }
        }

        return $stats;
    }
} 