<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller;

use core\base\BaseAdminController;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\Response;
use addon\recycle\app\service\admin\excel_parser\ExcelParserService;

/**
 * Excel解析Demo控制器
 */
class ExcelDemoController extends BaseAdminController
{
    /**
     * 读取Excel文件demo
     * @return Response
     */
    public function readExcel(): Response
    {
        try {
            // Excel文件路径
            $excelPath = root_path() . 'public/upload/export/bill.xlsx';
            
            // 检查文件是否存在
            if (!file_exists($excelPath)) {
                return fail('Excel文件不存在: ' . $excelPath);
            }
            
            // 加载Excel文件
            $spreadsheet = IOFactory::load($excelPath);
            
            // 获取所有工作表名称
            $worksheetNames = $spreadsheet->getSheetNames();
            
            $result = [
                'file_info' => [
                    'path' => $excelPath,
                    'size' => filesize($excelPath),
                    'sheet_count' => count($worksheetNames),
                    'sheet_names' => $worksheetNames
                ],
                'sheets_data' => []
            ];
            
            // 遍历每个工作表
            foreach ($worksheetNames as $sheetName) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                
                // 获取工作表的最高行数和最高列数
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                
                $sheetData = [
                    'sheet_name' => $sheetName,
                    'highest_row' => $highestRow,
                    'highest_column' => $highestColumn,
                    'data' => []
                ];
                
                // 读取前20行数据作为示例（避免数据过多）
                $maxRows = min($highestRow, 20);
                
                for ($row = 1; $row <= $maxRows; $row++) {
                    $rowData = [];
                    for ($col = 1; $col <= $highestColumnIndex; $col++) {
                        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                        $cellValue = $worksheet->getCell($columnLetter . $row)->getCalculatedValue();
                        $rowData[$columnLetter] = $cellValue;
                    }
                    $sheetData['data'][] = [
                        'row' => $row,
                        'cells' => $rowData
                    ];
                }
                
                $result['sheets_data'][] = $sheetData;
            }
            
            return success('Excel文件读取成功', $result);
            
        } catch (\Exception $e) {
            return fail('读取Excel文件失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 智能解析Excel文件
     * @return Response
     */
    public function smartParse(): Response
    {
        try {
            // Excel文件路径
            $excelPath = root_path() . 'public/upload/export/bill.xlsx';
            
            // 使用智能解析服务
            $parserService = new ExcelParserService();
            $result = $parserService->parseExcelFile($excelPath);
            
            // 验证解析结果
            $validation = $parserService->validateParsedData($result);
            $result['validation'] = $validation;
            
            return success('Excel智能解析成功', $result);
            
        } catch (\Exception $e) {
            return fail('智能解析失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 分析价格趋势
     * @return Response
     */
    public function analyzePriceTrends(): Response
    {
        try {
            $excelPath = root_path() . 'public/upload/export/bill.xlsx';
            
            $parserService = new ExcelParserService();
            $result = $parserService->parseExcelFile($excelPath);
            
            $analysis = [
                'summary' => [],
                'brand_comparison' => [],
                'price_distribution' => [],
                'model_analysis' => []
            ];
            
            foreach ($result['sheets'] as $sheet) {
                $brand = $sheet['brand'];
                $devices = $sheet['parsed_data']['devices'] ?? [];
                
                // 品牌总结
                $analysis['summary'][$brand] = [
                    'total_models' => count(array_unique(array_column($devices, 'model'))),
                    'total_records' => count($devices),
                    'avg_price' => $this->calculateAveragePrice($devices),
                    'price_range' => $this->calculatePriceRange($devices)
                ];
                
                // 型号分析
                $modelStats = [];
                foreach ($devices as $device) {
                    $model = $device['model'];
                    if (!isset($modelStats[$model])) {
                        $modelStats[$model] = [
                            'model' => $model,
                            'capacities' => [],
                            'prices' => [],
                            'min_price' => PHP_INT_MAX,
                            'max_price' => 0
                        ];
                    }
                    
                    $modelStats[$model]['capacities'][] = $device['capacity'];
                    
                    foreach ($device['prices'] as $condition => $price) {
                        if ($price > 0) {
                            $modelStats[$model]['prices'][] = $price;
                            $modelStats[$model]['min_price'] = min($modelStats[$model]['min_price'], $price);
                            $modelStats[$model]['max_price'] = max($modelStats[$model]['max_price'], $price);
                        }
                    }
                }
                
                // 清理无效数据
                foreach ($modelStats as &$stats) {
                    if ($stats['min_price'] === PHP_INT_MAX) {
                        $stats['min_price'] = 0;
                    }
                    $stats['capacities'] = array_unique($stats['capacities']);
                    $stats['avg_price'] = !empty($stats['prices']) ? array_sum($stats['prices']) / count($stats['prices']) : 0;
                }
                
                $analysis['model_analysis'][$brand] = array_values($modelStats);
            }
            
            return success('价格趋势分析完成', $analysis);
            
        } catch (\Exception $e) {
            return fail('价格趋势分析失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 计算平均价格
     * @param array $devices
     * @return float
     */
    private function calculateAveragePrice(array $devices): float
    {
        $allPrices = [];
        
        foreach ($devices as $device) {
            foreach ($device['prices'] as $price) {
                if ($price > 0) {
                    $allPrices[] = $price;
                }
            }
        }
        
        return !empty($allPrices) ? array_sum($allPrices) / count($allPrices) : 0;
    }
    
    /**
     * 计算价格范围
     * @param array $devices
     * @return array
     */
    private function calculatePriceRange(array $devices): array
    {
        $allPrices = [];
        
        foreach ($devices as $device) {
            foreach ($device['prices'] as $price) {
                if ($price > 0) {
                    $allPrices[] = $price;
                }
            }
        }
        
        if (empty($allPrices)) {
            return ['min' => 0, 'max' => 0];
        }
        
        return [
            'min' => min($allPrices),
            'max' => max($allPrices)
        ];
    }
}
