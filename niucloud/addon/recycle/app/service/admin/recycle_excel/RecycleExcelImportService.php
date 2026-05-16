<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\service\admin\recycle_excel;

use addon\recycle\app\model\RecycleDeviceModel;
use addon\recycle\app\model\RecycleDeviceBrand;
use addon\recycle\app\model\RecycleDevicePrice;
use core\base\BaseAdminService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;
use think\facade\Cache;

/**
 * Excel导入服务类
 */
class RecycleExcelImportService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeviceModel();
    }

    /**
     * 上传Excel文件
     * @param object $file
     * @return array
     */
    public function uploadFile($file)
    {
        // 在移动文件前先获取文件信息
        $originalName = $file->getOriginalName();
        $fileSize = $file->getSize();
        $extension = $file->getOriginalExtension();
        
        // 生成文件名
        $fileName = date('Y/m/d/') . uniqid() . '.' . $extension;
        $uploadPath = 'upload/excel/' . $fileName;
        
        // 确保目录存在
        $directory = dirname(public_path() . $uploadPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // 保存文件
        $file->move(public_path() . 'upload/excel/' . date('Y/m/d/'), basename($fileName));

        // 获取Excel工作表信息
        $sheets = $this->getExcelSheets($uploadPath);

        return [
            'file_path' => $uploadPath,
            'file_name' => $originalName,
            'file_size' => $fileSize,
            'sheets' => $sheets,
            'upload_time' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 获取Excel工作表信息
     * @param string $filePath
     * @return array
     */
    public function getExcelSheets($filePath)
    {
        try {
            $spreadsheet = IOFactory::load(public_path() . $filePath);
            $sheets = [];
            
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheets[] = [
                    'name' => $sheet->getTitle(),
                    'row_count' => $sheet->getHighestRow(),
                    'column_count' => $sheet->getHighestColumn()
                ];
            }
            
            return $sheets;
        } catch (\Exception $e) {
            throw new \Exception('读取Excel文件失败：' . $e->getMessage());
        }
    }

    /**
     * 解析Excel文件
     * @param string $filePath
     * @param string $sheetName
     * @param int $startRow
     * @return array
     */
    public function parseExcelFile($filePath, $sheetName = '', $startRow = 1)
    {
        try {
            $spreadsheet = IOFactory::load(public_path() . $filePath);
            
            // 选择工作表
            if (!empty($sheetName)) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
            } else {
                $sheet = $spreadsheet->getActiveSheet();
            }

            if (!$sheet) {
                throw new \Exception('找不到指定的工作表');
            }

            // 获取数据范围
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();

            // 读取表头
            $headers = [];
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $value = $sheet->getCell($col . $startRow)->getValue();
                if (!empty($value)) {
                    $headers[$col] = trim($value);
                }
            }

            // 读取数据行（预览前10行）
            $data = [];
            $previewRows = min(10, $highestRow - $startRow);
            
            for ($row = $startRow + 1; $row <= $startRow + $previewRows; $row++) {
                $rowData = [];
                foreach ($headers as $col => $header) {
                    $cellValue = $sheet->getCell($col . $row)->getValue();
                    $rowData[$header] = $cellValue;
                }
                $data[] = $rowData;
            }

            return [
                'headers' => array_values($headers),
                'data' => $data,
                'total_rows' => $highestRow - $startRow,
                'sheet_name' => $sheet->getTitle(),
                'suggested_mapping' => $this->suggestColumnMapping(array_values($headers))
            ];

        } catch (\Exception $e) {
            throw new \Exception('解析Excel文件失败：' . $e->getMessage());
        }
    }

    /**
     * 建议列映射
     * @param array $headers
     * @return array
     */
    private function suggestColumnMapping($headers)
    {
        $mapping = [];
        $fieldMap = [
            '型号' => 'model_name',
            '机型' => 'model_name', 
            '网络型号' => 'network_model',
            '网络制式' => 'network_model',
            '容量' => 'capacity',
            '存储容量' => 'capacity',
            '品牌' => 'brand_name',
            '高保充新' => 'price_高保充新',
            '充新' => 'price_充新',
            '靓机' => 'price_靓机',
            '小花' => 'price_小花',
            '大花' => 'price_大花',
            '外爆' => 'price_外爆',
            '内爆' => 'price_内爆'
        ];

        foreach ($headers as $index => $header) {
            $normalizedHeader = trim($header);
            if (isset($fieldMap[$normalizedHeader])) {
                $mapping[$index] = $fieldMap[$normalizedHeader];
            }
        }

        return $mapping;
    }

    /**
     * 预览导入数据
     * @param string $filePath
     * @param array $mapping
     * @param int $previewCount
     * @return array
     */
    public function previewImportData($filePath, $mapping, $previewCount = 10)
    {
        try {
            $spreadsheet = IOFactory::load(public_path() . $filePath);
            $sheet = $spreadsheet->getActiveSheet();
            
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            
            $previewData = [];
            $errorData = [];
            
            for ($row = 2; $row <= min($previewCount + 1, $highestRow); $row++) {
                $rowData = [];
                $prices = [];
                
                // 读取行数据
                foreach ($mapping as $colIndex => $field) {
                    $col = chr(65 + $colIndex); // A, B, C...
                    $value = $sheet->getCell($col . $row)->getValue();
                    
                    if (strpos($field, 'price_') === 0) {
                        $priceType = str_replace('price_', '', $field);
                        $prices[$priceType] = $value;
                    } else {
                        $rowData[$field] = $value;
                    }
                }
                
                $rowData['prices'] = $prices;
                
                // 数据验证
                $validation = $this->validateRowData($rowData);
                $rowData['validation'] = $validation;
                
                if ($validation['has_error']) {
                    $errorData[] = array_merge($rowData, ['row_number' => $row]);
                } else {
                    $previewData[] = array_merge($rowData, ['row_number' => $row]);
                }
            }
            
            return [
                'preview_data' => $previewData,
                'error_data' => $errorData,
                'total_rows' => $highestRow - 1,
                'preview_count' => count($previewData) + count($errorData)
            ];
            
        } catch (\Exception $e) {
            throw new \Exception('预览数据失败：' . $e->getMessage());
        }
    }

    /**
     * 验证行数据
     * @param array $rowData
     * @return array
     */
    private function validateRowData($rowData)
    {
        $errors = [];
        
        // 必填字段验证
        if (empty($rowData['model_name'])) {
            $errors[] = '型号不能为空';
        }
        
        if (empty($rowData['capacity'])) {
            $errors[] = '容量不能为空';
        }
        
        // 价格验证
        if (isset($rowData['prices'])) {
            foreach ($rowData['prices'] as $type => $price) {
                if (!empty($price) && !is_numeric($price)) {
                    $errors[] = "价格格式错误：{$type}";
                }
            }
        }
        
        return [
            'has_error' => !empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 执行数据导入
     * @param string $filePath
     * @param array $mapping
     * @param string $importMode
     * @param bool $skipErrors
     * @return array
     */
    public function importData($filePath, $mapping, $importMode = 'insert', $skipErrors = true)
    {
        try {
            Db::startTrans();
            
            $spreadsheet = IOFactory::load(public_path() . $filePath);
            $sheet = $spreadsheet->getActiveSheet();
            
            $highestRow = $sheet->getHighestRow();
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    $rowData = [];
                    $prices = [];
                    
                    // 读取行数据
                    foreach ($mapping as $colIndex => $field) {
                        $col = chr(65 + $colIndex);
                        $value = $sheet->getCell($col . $row)->getValue();
                        
                        if (strpos($field, 'price_') === 0) {
                            $priceType = str_replace('price_', '', $field);
                            if (!empty($value) && is_numeric($value)) {
                                $prices[$priceType] = $value;
                            }
                        } else {
                            $rowData[$field] = $value;
                        }
                    }
                    
                    // 验证数据
                    $validation = $this->validateRowData(array_merge($rowData, ['prices' => $prices]));
                    if ($validation['has_error']) {
                        if ($skipErrors) {
                            $errors[] = "第{$row}行：" . implode(', ', $validation['errors']);
                            $errorCount++;
                            continue;
                        } else {
                            throw new \Exception("第{$row}行数据验证失败：" . implode(', ', $validation['errors']));
                        }
                    }
                    
                    // 处理品牌
                    $brandId = $this->getOrCreateBrand($rowData['brand_name'] ?? '');
                    
                    // 处理设备型号
                    $deviceData = [
                        'site_id' => $this->site_id,
                        'brand_id' => $brandId,
                        'model_name' => $rowData['model_name'],
                        'network_model' => $rowData['network_model'] ?? '',
                        'capacity' => $rowData['capacity'],
                        'device_type' => 'phone',
                        'status' => 1
                    ];
                    
                    // 查找或创建设备型号
                    $deviceModel = $this->getOrCreateDeviceModel($deviceData, $importMode);
                    
                    // 处理价格数据
                    if (!empty($prices)) {
                        $this->saveDevicePrice($deviceModel['id'], $prices);
                    }
                    
                    $successCount++;
                    
                } catch (\Exception $e) {
                    if ($skipErrors) {
                        $errors[] = "第{$row}行：" . $e->getMessage();
                        $errorCount++;
                    } else {
                        throw $e;
                    }
                }
            }
            
            Db::commit();
            
            // 清除缓存
            Cache::tag('recycle_excel')->clear();
            
            return [
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'total_count' => $highestRow - 1,
                'errors' => $errors,
                'import_time' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            Db::rollback();
            throw new \Exception('数据导入失败：' . $e->getMessage());
        }
    }

    /**
     * 获取或创建品牌
     * @param string $brandName
     * @return int
     */
    private function getOrCreateBrand($brandName)
    {
        if (empty($brandName)) {
            $brandName = '未知品牌';
        }
        
        $brand = (new RecycleDeviceBrand())->where([
            ['site_id', '=', $this->site_id],
            ['brand_name', '=', $brandName]
        ])->find();
        
        if (!$brand) {
            $brandData = [
                'site_id' => $this->site_id,
                'brand_name' => $brandName,
                'brand_code' => strtolower(str_replace(' ', '_', $brandName)),
                'status' => 1
            ];
            $brand = (new RecycleDeviceBrand())->create($brandData);
        }
        
        return $brand->id;
    }

    /**
     * 获取或创建设备型号
     * @param array $deviceData
     * @param string $importMode
     * @return array
     */
    private function getOrCreateDeviceModel($deviceData, $importMode)
    {
        $existingDevice = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['brand_id', '=', $deviceData['brand_id']],
            ['model_name', '=', $deviceData['model_name']],
            ['capacity', '=', $deviceData['capacity']]
        ])->find();
        
        if ($existingDevice) {
            if ($importMode === 'update' || $importMode === 'replace') {
                $existingDevice->save($deviceData);
                return $existingDevice->toArray();
            }
            return $existingDevice->toArray();
        } else {
            $device = $this->model->create($deviceData);
            return $device->toArray();
        }
    }

    /**
     * 保存设备价格
     * @param int $deviceId
     * @param array $prices
     */
    private function saveDevicePrice($deviceId, $prices)
    {
        $priceModel = new RecycleDevicePrice();
        
        // 将当前价格设为非最新
        $priceModel->where('device_model_id', $deviceId)->update(['is_current' => 0]);
        
        // 创建新价格记录
        $priceData = [
            'site_id' => $this->site_id,
            'device_model_id' => $deviceId,
            'price_data' => json_encode($prices),
            'price_date' => date('Y-m-d'),
            'is_current' => 1
        ];
        
        $priceModel->create($priceData);
    }

    /**
     * 生成导入模板
     * @return string
     */
    public function generateTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // 设置表头
        $headers = ['型号', '网络型号', '容量', '品牌', '高保充新', '充新', '靓机', '小花', '大花', '外爆', '内爆'];
        
        foreach ($headers as $index => $header) {
            $col = chr(65 + $index);
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
        }
        
        // 添加示例数据
        $exampleData = [
            ['iPhone 14', 'A2884', '128GB', 'Apple', '4500', '4200', '3800', '3400', '2800', '1200', '800'],
            ['iPhone 14', 'A2884', '256GB', 'Apple', '5200', '4900', '4500', '4100', '3500', '1500', '1000']
        ];
        
        foreach ($exampleData as $rowIndex => $rowData) {
            foreach ($rowData as $colIndex => $value) {
                $col = chr(65 + $colIndex);
                $sheet->setCellValue($col . ($rowIndex + 2), $value);
            }
        }
        
        // 设置列宽
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $fileName = 'template_' . date('YmdHis') . '.xlsx';
        $filePath = public_path() . 'upload/excel/template/' . $fileName;
        
        // 确保目录存在
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        
        return $filePath;
    }

    /**
     * 获取导入历史
     * @param array $params
     * @return array
     */
    public function getImportHistory($params)
    {
        // 这里可以实现导入历史记录功能
        // 暂时返回空数据
        return [
            'data' => [],
            'total' => 0
        ];
    }
} 