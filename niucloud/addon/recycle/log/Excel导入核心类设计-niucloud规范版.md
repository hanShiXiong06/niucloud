# Excel价格导入核心类设计 - NiuCloud规范版

## 🎯 遵循NiuCloud规范的调整

### ❌ **禁止使用的方式**
```php
// ❌ 不要直接使用DB::facade
Db::table('table_name')->insert($data);
Db::startTrans();
```

### ✅ **正确的niucloud规范**
```php
// ✅ 继承BaseAdminService
class PriceImportService extends BaseAdminService

// ✅ 使用模型操作
$this->model = new RecyclePriceImport();
$this->model->create($data);
$this->model->saveAll($dataList);

// ✅ 使用pageQuery方法
$this->pageQuery($search_model);
```

## 🏗️ 核心类设计（符合NiuCloud规范）

### 1. 📊 ExcelParserService - Excel解析服务
```php
<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\price_import;

use core\base\BaseAdminService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use core\exception\CommonException;

/**
 * Excel解析服务
 */
class ExcelParserService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 解析Excel文件主入口
     * @param string $filePath Excel文件路径
     * @return array 解析结果
     */
    public function parseExcelFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheetNames = $spreadsheet->getSheetNames();
            
            $result = [
                'total_sheets' => count($worksheetNames),
                'sheets_data' => [],
                'total_records' => 0
            ];
            
            foreach ($worksheetNames as $sheetName) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                $sheetData = $this->parseWorksheet($worksheet, $sheetName);
                $result['sheets_data'][] = $sheetData;
                $result['total_records'] += count($sheetData['devices']);
            }
            
            return $result;
        } catch (\Exception $e) {
            throw new CommonException('Excel文件解析失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 处理单个工作表
     * @param $worksheet 工作表对象
     * @param string $sheetName 工作表名
     * @return array 工作表数据
     */
    private function parseWorksheet($worksheet, string $sheetName): array
    {
        $brandInfo = $this->extractBrandInfo($sheetName);
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // 读取所有数据
        $rawData = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $worksheet->getCell($col . $row)->getCalculatedValue();
                $rowData[$col] = $cellValue;
            }
            $rawData[] = $rowData;
        }
        
        // 验证表头
        if (!$this->validateHeaders($rawData[1] ?? [])) {
            throw new CommonException("工作表 {$sheetName} 的表头格式不正确");
        }
        
        // 处理合并单元格
        $processedData = $this->handleMergedCells(array_slice($rawData, 2));
        
        return [
            'brand' => $brandInfo['brand'],
            'category_id' => $brandInfo['category_id'],
            'sheet_name' => $sheetName,
            'devices' => $processedData
        ];
    }
    
    /**
     * 智能处理合并单元格（核心算法）
     * @param array $rawData 原始数据
     * @return array 处理后的完整数据
     */
    private function handleMergedCells(array $rawData): array
    {
        $processedData = [];
        $lastModel = '';
        $lastNetworkModel = '';
        
        foreach ($rawData as $row) {
            // 跳过空行
            if (empty(array_filter($row))) {
                continue;
            }
            
            // 处理型号字段合并单元格
            if (!empty($row['A'])) {
                $lastModel = $row['A'];
                $lastNetworkModel = $row['B'] ?? '';
            }
            
            // 只有容量不为空才认为是有效数据行
            if (!empty($row['C'])) {
                $processedData[] = [
                    'model' => $lastModel,
                    'network_model' => $lastNetworkModel,
                    'capacity' => $row['C'],
                    'price_perfect' => $this->parsePrice($row['D'] ?? ''),
                    'price_excellent' => $this->parsePrice($row['E'] ?? ''),
                    'price_good' => $this->parsePrice($row['F'] ?? ''),
                    'price_fair' => $this->parsePrice($row['G'] ?? ''),
                    'price_poor' => $this->parsePrice($row['H'] ?? ''),
                    'price_broken_outer' => $this->parsePrice($row['I'] ?? ''),
                    'price_broken_inner' => $this->parsePrice($row['J'] ?? ''),
                    'remark' => $row['K'] ?? ''
                ];
            }
        }
        
        return $processedData;
    }
    
    /**
     * 解析价格字段
     */
    private function parsePrice($value): float
    {
        if (empty($value) || $value === '/' || !is_numeric($value)) {
            return 0.00;
        }
        return (float)$value;
    }
    
    /**
     * 识别并提取品牌信息
     * @param string $sheetName 工作表名
     * @return array [brand, category_id]
     */
    private function extractBrandInfo(string $sheetName): array
    {
        $brandMap = [
            '苹果' => ['brand' => '苹果', 'category_id' => 1],
            '华为' => ['brand' => '华为', 'category_id' => 1],
            '三星' => ['brand' => '三星', 'category_id' => 1],
            '荣耀' => ['brand' => '荣耀', 'category_id' => 1],
            'OPPO' => ['brand' => 'OPPO', 'category_id' => 1],
            '小米' => ['brand' => '小米', 'category_id' => 1],
            'VIVO' => ['brand' => 'VIVO', 'category_id' => 1],
            'iqoo' => ['brand' => 'iQOO', 'category_id' => 1],
            '苹果平板' => ['brand' => '苹果', 'category_id' => 2],
            '华为平板' => ['brand' => '华为', 'category_id' => 2],
            '苹果手表' => ['brand' => '苹果', 'category_id' => 3],
            '华为手表' => ['brand' => '华为', 'category_id' => 3],
            'vo手表' => ['brand' => 'VIVO', 'category_id' => 3],
        ];
        
        return $brandMap[$sheetName] ?? ['brand' => $sheetName, 'category_id' => 1];
    }
    
    /**
     * 验证表头结构
     * @param array $headers 表头数组
     * @return bool 是否符合标准格式
     */
    private function validateHeaders(array $headers): bool
    {
        $requiredHeaders = ['型号', '容量'];
        foreach ($requiredHeaders as $header) {
            if (!in_array($header, $headers)) {
                return false;
            }
        }
        return true;
    }
}
```

### 2. 🔄 PriceDataProcessor - 数据处理器
```php
<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\price_import;

use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 价格数据处理器
 */
class PriceDataProcessor extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 数据标准化主处理
     * @param array $rawData 原始解析数据
     * @return array 标准化后数据
     */
    public function processData(array $rawData): array
    {
        $processedData = [];
        $errors = [];
        
        foreach ($rawData['sheets_data'] as $sheet) {
            foreach ($sheet['devices'] as $device) {
                $item = [
                    'site_id' => $this->site_id,
                    'brand' => $sheet['brand'],
                    'category_id' => $sheet['category_id'],
                    'model' => trim($device['model']),
                    'network_model' => trim($device['network_model']),
                    'capacity' => $this->standardizeCapacity($device['capacity']),
                    'price_perfect' => $device['price_perfect'],
                    'price_excellent' => $device['price_excellent'],
                    'price_good' => $device['price_good'],
                    'price_fair' => $device['price_fair'],
                    'price_poor' => $device['price_poor'],
                    'price_broken_outer' => $device['price_broken_outer'],
                    'price_broken_inner' => $device['price_broken_inner'],
                    'remark' => $device['remark'],
                    'create_at' => time(),
                    'update_at' => time()
                ];
                
                // 数据验证
                $validation = $this->validateDataItem($item);
                if ($validation['isValid']) {
                    $processedData[] = $item;
                } else {
                    $errors[] = [
                        'data' => $item,
                        'errors' => $validation['errors']
                    ];
                }
            }
        }
        
        return [
            'valid_data' => $processedData,
            'error_data' => $errors,
            'total_count' => count($processedData) + count($errors),
            'valid_count' => count($processedData),
            'error_count' => count($errors)
        ];
    }
    
    /**
     * 标准化容量格式
     * @param string $capacity 原始容量
     * @return string 标准化容量
     */
    private function standardizeCapacity(string $capacity): string
    {
        $capacity = trim($capacity);
        
        // 容量格式映射
        $capacityMap = [
            '1T' => '1TB',
            '1t' => '1TB',
            '512' => '512GB',
            '256' => '256GB',
            '128' => '128GB',
            '64' => '64GB',
            '32' => '32GB',
        ];
        
        // 直接映射
        if (isset($capacityMap[$capacity])) {
            return $capacityMap[$capacity];
        }
        
        // 处理 16+512 格式
        if (preg_match('/^(\d+)\+(\d+)$/', $capacity, $matches)) {
            return $matches[1] . 'GB+' . $matches[2] . 'GB';
        }
        
        // 已经标准化的格式直接返回
        if (preg_match('/^\d+(GB|TB)(\+\d+(GB|TB))?$/i', $capacity)) {
            return strtoupper($capacity);
        }
        
        return $capacity;
    }
    
    /**
     * 数据验证
     * @param array $item 单条数据
     * @return array [isValid, errors]
     */
    private function validateDataItem(array $item): array
    {
        $errors = [];
        
        // 必填字段检查
        if (empty($item['brand'])) {
            $errors[] = '品牌不能为空';
        }
        if (empty($item['model'])) {
            $errors[] = '型号不能为空';
        }
        if (empty($item['capacity'])) {
            $errors[] = '容量不能为空';
        }
        
        // 价格合理性检查
        $priceValidation = $this->validatePriceLogic([
            'perfect' => $item['price_perfect'],
            'excellent' => $item['price_excellent'],
            'good' => $item['price_good'],
            'fair' => $item['price_fair'],
            'poor' => $item['price_poor'],
            'broken_outer' => $item['price_broken_outer'],
            'broken_inner' => $item['price_broken_inner']
        ]);
        
        if (!$priceValidation['isValid']) {
            $errors = array_merge($errors, $priceValidation['errors']);
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * 价格合理性检查
     * @param array $prices 价格数组
     * @return array 验证结果
     */
    private function validatePriceLogic(array $prices): array
    {
        $errors = [];
        
        // 检查至少有一个价格大于0
        $hasValidPrice = false;
        foreach ($prices as $price) {
            if ($price > 0) {
                $hasValidPrice = true;
                break;
            }
        }
        
        if (!$hasValidPrice) {
            $errors[] = '至少需要一个有效价格';
        }
        
        // 检查价格范围（10-50000）
        foreach ($prices as $key => $price) {
            if ($price > 0 && ($price < 10 || $price > 50000)) {
                $errors[] = "价格{$key}超出合理范围(10-50000)";
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
}
```

### 3. 💾 PriceImportService - 导入服务（核心）
```php
<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\price_import;

use addon\recycle\app\model\RecyclePriceImport;
use addon\recycle\app\model\RecyclePriceStandard;
use addon\recycle\app\model\RecyclePriceImportLog;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 价格导入服务
 */
class PriceImportService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecyclePriceImport();
    }
    
    /**
     * 执行导入主流程
     * @param string $filePath 文件路径
     * @param array $options 导入选项
     * @return array 导入结果
     */
    public function executeImport(string $filePath, array $options = []): array
    {
        $batchNo = date('YmdHis') . '_' . $this->site_id;
        $logId = $this->createImportLog([
            'batch_no' => $batchNo,
            'file_path' => $filePath,
            'operator_id' => $this->uid,
            'operator_name' => $this->username
        ]);
        
        try {
            // 1. 解析Excel文件
            $parser = new ExcelParserService();
            $rawData = $parser->parseExcelFile($filePath);
            
            // 2. 数据处理和验证
            $processor = new PriceDataProcessor();
            $processedData = $processor->processData($rawData);
            
            // 3. 批量插入临时表
            $this->batchInsertTemp($processedData['valid_data'], $batchNo);
            
            // 4. 转入正式表
            $transferResult = $this->transferToStandard($batchNo, $options);
            
            // 5. 更新日志
            $this->updateImportLog($logId, [
                'total_count' => $processedData['total_count'],
                'success_count' => $transferResult['success_count'],
                'fail_count' => $processedData['error_count'],
                'status' => 1,
                'end_time' => time()
            ]);
            
            return [
                'success' => true,
                'batch_no' => $batchNo,
                'total_count' => $processedData['total_count'],
                'success_count' => $transferResult['success_count'],
                'fail_count' => $processedData['error_count'],
                'error_data' => $processedData['error_data']
            ];
            
        } catch (\Exception $e) {
            $this->updateImportLog($logId, [
                'status' => 2,
                'error_summary' => $e->getMessage(),
                'end_time' => time()
            ]);
            throw $e;
        }
    }
    
    /**
     * 批量插入临时表
     * @param array $data 处理后的数据
     * @param string $batchNo 批次号
     * @return bool 插入结果
     */
    private function batchInsertTemp(array $data, string $batchNo): bool
    {
        if (empty($data)) {
            return true;
        }
        
        // 添加批次号
        foreach ($data as &$item) {
            $item['import_batch'] = $batchNo;
        }
        
        // 分批插入，每批500条
        $chunks = array_chunk($data, 500);
        foreach ($chunks as $chunk) {
            $this->model->saveAll($chunk);
        }
        
        return true;
    }
    
    /**
     * 从临时表转入正式表
     * @param string $batchNo 批次号
     * @param array $options 选项
     * @return array 转入结果
     */
    private function transferToStandard(string $batchNo, array $options): array
    {
        $tempData = $this->model->where([
            ['import_batch', '=', $batchNo],
            ['status', '=', 0]
        ])->select();
        
        $standardModel = new RecyclePriceStandard();
        $successCount = 0;
        
        Db::startTrans();
        try {
            foreach ($tempData as $temp) {
                // 检查是否已存在
                $existing = $standardModel->where([
                    ['site_id', '=', $temp->site_id],
                    ['brand', '=', $temp->brand],
                    ['model', '=', $temp->model],
                    ['capacity', '=', $temp->capacity]
                ])->find();
                
                $priceData = [
                    'perfect' => $temp->price_perfect,
                    'excellent' => $temp->price_excellent,
                    'good' => $temp->price_good,
                    'fair' => $temp->price_fair,
                    'poor' => $temp->price_poor,
                    'broken_outer' => $temp->price_broken_outer,
                    'broken_inner' => $temp->price_broken_inner
                ];
                
                if ($existing) {
                    // 更新现有记录
                    $existing->save([
                        'price_levels' => json_encode($priceData),
                        'remark' => $temp->remark,
                        'update_at' => time()
                    ]);
                } else {
                    // 创建新记录
                    $standardModel->create([
                        'site_id' => $temp->site_id,
                        'category_id' => $temp->category_id ?? 1,
                        'brand' => $temp->brand,
                        'model' => $temp->model,
                        'network_model' => $temp->network_model,
                        'capacity' => $temp->capacity,
                        'price_levels' => json_encode($priceData),
                        'remark' => $temp->remark,
                        'is_active' => 1,
                        'create_at' => time(),
                        'update_at' => time()
                    ]);
                }
                
                // 更新临时表状态
                $temp->save(['status' => 1]);
                $successCount++;
            }
            
            Db::commit();
            return ['success_count' => $successCount];
            
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
    
    /**
     * 创建导入日志
     * @param array $data 导入信息
     * @return int 日志ID
     */
    private function createImportLog(array $data): int
    {
        $logModel = new RecyclePriceImportLog();
        $log = $logModel->create([
            'site_id' => $this->site_id,
            'batch_no' => $data['batch_no'],
            'file_name' => basename($data['file_path']),
            'file_path' => $data['file_path'],
            'operator_id' => $data['operator_id'],
            'operator_name' => $data['operator_name'],
            'status' => 0,
            'start_time' => time(),
            'create_at' => time()
        ]);
        return $log->id;
    }
    
    /**
     * 更新导入日志
     * @param int $logId 日志ID
     * @param array $data 更新数据
     * @return bool 更新结果
     */
    private function updateImportLog(int $logId, array $data): bool
    {
        $logModel = new RecyclePriceImportLog();
        $log = $logModel->find($logId);
        if ($log) {
            $log->save($data);
            return true;
        }
        return false;
    }
    
    /**
     * 数据回滚
     * @param string $batchNo 批次号
     * @return bool 回滚结果
     */
    public function rollbackImport(string $batchNo): bool
    {
        Db::startTrans();
        try {
            // 删除标准表中的数据（通过批次关联）
            $tempData = $this->model->where('import_batch', $batchNo)->select();
            $standardModel = new RecyclePriceStandard();
            
            foreach ($tempData as $temp) {
                $standardModel->where([
                    ['site_id', '=', $temp->site_id],
                    ['brand', '=', $temp->brand],
                    ['model', '=', $temp->model],
                    ['capacity', '=', $temp->capacity]
                ])->delete();
            }
            
            // 删除临时表数据
            $this->model->where('import_batch', $batchNo)->delete();
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException('回滚失败: ' . $e->getMessage());
        }
    }
}
```

## 🔑 关键规范遵循点

### ✅ **数据库操作规范**
1. **继承正确的基类**：`extends BaseAdminService`
2. **使用模型操作**：`$this->model->create()`, `$this->model->saveAll()`
3. **分页查询**：`$this->pageQuery($search_model)`
4. **事务处理**：`Db::startTrans()`, `Db::commit()`, `Db::rollback()`
5. **站点隔离**：使用`$this->site_id`进行多租户

### ✅ **代码结构规范**
1. **命名空间**：`addon\recycle\app\service\admin\price_import`
2. **异常处理**：使用`CommonException`
3. **类型声明**：`declare(strict_types=1);`
4. **构造函数**：调用`parent::__construct()`

### ✅ **核心优势**
- **合并单元格智能处理**：完美解决Excel复杂结构
- **分批处理**：500条一批，内存友好
- **事务安全**：确保数据一致性
- **完整日志**：导入过程全程可追溯
- **数据回滚**：支持批次回滚功能

这个设计完全符合niucloud的开发规范，确保代码质量和系统稳定性！ 