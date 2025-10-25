# 牛云SaaS回收系统 - Excel价格导入功能分步实施方案

## 项目概述

### 核心功能
- Excel价格数据批量导入
- 智能合并单元格处理
- 每日价格更新机制
- 新设备自动识别和入库
- 完整的日志记录和调试体系

### 技术栈
- 后端：ThinkPHP 8 + PHP 8+
- 前端：Vue 3 + TypeScript + Element Plus
- 数据库：MySQL
- Excel处理：PhpSpreadsheet

## 数据库设计

### 表结构设计

#### 1. 设备品牌表 (recycle_device_brand)
```sql
CREATE TABLE `recycle_device_brand` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `brand_name` varchar(50) NOT NULL COMMENT '品牌名称',
  `brand_code` varchar(20) NOT NULL COMMENT '品牌编码',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 0禁用',
  `create_at` int(11) NOT NULL DEFAULT 0,
  `update_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) COMMENT='回收设备品牌表';
```

#### 2. 设备型号表 (recycle_device_model)
```sql
CREATE TABLE `recycle_device_model` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `brand_id` int(11) NOT NULL COMMENT '品牌ID',
  `model_name` varchar(100) NOT NULL COMMENT '型号名称',
  `network_model` varchar(100) DEFAULT '' COMMENT '网络型号',
  `capacity` varchar(50) DEFAULT '' COMMENT '容量',
  `device_type` varchar(20) DEFAULT 'phone' COMMENT '设备类型 phone平板 tablet手表 watch',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `create_at` int(11) NOT NULL DEFAULT 0,
  `update_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_model` (`site_id`,`brand_id`,`model_name`,`network_model`,`capacity`)
) COMMENT='回收设备型号表';
```

#### 3. 价格导入记录表 (recycle_price_import_record)
```sql
CREATE TABLE `recycle_price_import_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `import_batch` varchar(32) NOT NULL COMMENT '导入批次号',
  `file_name` varchar(255) NOT NULL COMMENT '文件名',
  `sheet_name` varchar(100) NOT NULL COMMENT '工作表名',
  `total_rows` int(11) NOT NULL DEFAULT 0 COMMENT '总行数',
  `success_rows` int(11) NOT NULL DEFAULT 0 COMMENT '成功行数',
  `failed_rows` int(11) NOT NULL DEFAULT 0 COMMENT '失败行数',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 0处理中 1成功 2失败',
  `error_message` text COMMENT '错误信息',
  `start_at` int(11) NOT NULL DEFAULT 0 COMMENT '开始时间',
  `end_at` int(11) NOT NULL DEFAULT 0 COMMENT '结束时间',
  `create_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) COMMENT='价格导入记录表';
```

#### 4. 设备价格表 (recycle_device_price)
```sql
CREATE TABLE `recycle_device_price` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `device_model_id` int(11) NOT NULL COMMENT '设备型号ID',
  `import_batch` varchar(32) NOT NULL COMMENT '导入批次号',
  `price_data` json NOT NULL COMMENT '价格数据JSON：{"高保充新":7000,"充新":6900,"靓机":6500,"小花":6000,"大花":5500,"外爆":5000,"内爆":4500}',
  `price_date` date NOT NULL COMMENT '价格日期',
  `is_current` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否当前价格',
  `create_at` int(11) NOT NULL DEFAULT 0,
  `update_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_device_date` (`device_model_id`,`price_date`)
) COMMENT='设备价格表';
```

#### 5. 导入日志表 (recycle_import_log)
```sql
CREATE TABLE `recycle_import_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `import_batch` varchar(32) NOT NULL,
  `level` varchar(10) NOT NULL COMMENT '日志级别 info/warning/error',
  `message` text NOT NULL COMMENT '日志消息',
  `data` json DEFAULT NULL COMMENT '相关数据',
  `create_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) COMMENT='导入日志表';
```

## 开发节点分解

### 阶段一：基础框架搭建

#### 节点 1.1：创建基础模型类
**开发内容：**
- 创建 RecycleDeviceBrand 模型
- 创建 RecycleDeviceModel 模型
- 创建 RecyclePriceImportRecord 模型
- 创建 RecycleDevicePrice 模型
- 创建 RecycleImportLog 模型

**验收标准：**
- [ ] 所有模型类创建完成
- [ ] 模型关联关系定义正确
- [ ] 站点隔离功能正常
- [ ] 基础CRUD操作测试通过

**日志调试：**
```php
// 在每个模型的save方法中添加日志
Log::info('模型保存', ['model' => get_class($this), 'data' => $this->getData()]);
```

#### 节点 1.2：创建数据库迁移脚本
**开发内容：**
- 编写数据库建表SQL
- 创建迁移命令
- 测试数据库结构

**验收标准：**
- [ ] 数据库表结构创建成功
- [ ] 索引创建正确
- [ ] 外键关系正确

**调试命令：**
```bash
# 查看表结构
DESCRIBE recycle_device_brand;
DESCRIBE recycle_device_model;
# 检查索引
SHOW INDEX FROM recycle_device_model;
```

#### 节点 1.3：创建日志服务类
**开发内容：**
- 创建 ImportLogService 类
- 实现日志记录方法
- 实现日志查询方法

**验收标准：**
- [ ] 日志记录功能正常
- [ ] 日志级别区分正确
- [ ] 日志查询功能正常

**测试代码：**
```php
// 测试日志记录
$logService = new ImportLogService();
$logService->info('测试信息日志', ['test' => 'data']);
$logService->warning('测试警告日志');
$logService->error('测试错误日志', ['error' => 'detail']);

// 查询验证
$logs = $logService->getLogs('batch_test_001');
var_dump($logs);
```

### 阶段二：Excel解析核心功能

#### 节点 2.1：Excel文件上传和基础读取
**开发内容：**
- 实现文件上传功能
- 实现Excel文件格式验证
- 实现基础读取测试

**验收标准：**
- [ ] 文件上传成功
- [ ] Excel文件可以正常打开
- [ ] 工作表数量读取正确
- [ ] 文件大小限制正常

**调试输出：**
```php
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load($filePath);
$worksheetNames = $spreadsheet->getSheetNames();

// 日志记录
$logService->info('Excel文件读取成功', [
    'file' => $fileName,
    'sheets' => $worksheetNames,
    'sheet_count' => count($worksheetNames)
]);
```

#### 节点 2.2：工作表结构分析
**开发内容：**
- 识别标题行位置
- 识别表头行位置
- 识别数据起始行
- 识别列映射关系

**验收标准：**
- [ ] 标题行正确识别
- [ ] 表头行正确识别
- [ ] 列映射关系正确
- [ ] 异常工作表能够识别

**调试代码：**
```php
public function analyzeSheetStructure($worksheet, $sheetName)
{
    $analysis = [
        'sheet_name' => $sheetName,
        'title_row' => null,
        'header_row' => null,
        'data_start_row' => null,
        'column_mapping' => []
    ];
    
    // 分析前5行
    for ($row = 1; $row <= 5; $row++) {
        $rowData = [];
        for ($col = 'A'; $col <= 'L'; $col++) {
            $rowData[$col] = $worksheet->getCell($col . $row)->getValue();
        }
        
        $this->logService->info("行分析", [
            'sheet' => $sheetName,
            'row' => $row,
            'data' => $rowData
        ]);
        
        // 识别逻辑...
    }
    
    return $analysis;
}
```

#### 节点 2.3：合并单元格检测算法
**开发内容：**
- 实现合并单元格范围检测
- 实现合并单元格数据提取
- 实现数据填充算法

**验收标准：**
- [ ] 合并单元格正确检测
- [ ] 数据填充算法正确
- [ ] 边界情况处理正常

**调试代码：**
```php
public function detectMergedCells($worksheet, $startRow, $endRow)
{
    $mergedRanges = $worksheet->getMergeCells();
    $mergedInfo = [];
    
    foreach ($mergedRanges as $range) {
        $this->logService->info('检测到合并单元格', [
            'range' => $range,
            'coordinates' => Coordinate::splitRange($range)
        ]);
        
        // 分析合并单元格影响的行
        [$startCell, $endCell] = Coordinate::splitRange($range);
        $startCoord = Coordinate::coordinateFromString($startCell);
        $endCoord = Coordinate::coordinateFromString($endCell);
        
        $mergedInfo[] = [
            'range' => $range,
            'start_row' => $startCoord[1],
            'end_row' => $endCoord[1],
            'column' => $startCoord[0]
        ];
    }
    
    return $mergedInfo;
}
```

#### 节点 2.4：数据行提取算法
**开发内容：**
- 实现逐行数据提取
- 实现合并单元格数据补全
- 实现数据类型识别

**验收标准：**
- [ ] 数据提取完整
- [ ] 合并单元格补全正确
- [ ] 空行跳过正常
- [ ] 数据类型识别正确

**调试代码：**
```php
public function extractRowData($worksheet, $row, $columnMapping, $mergedCellData)
{
    $rowData = [];
    
    foreach ($columnMapping as $column => $field) {
        $cellValue = $worksheet->getCell($column . $row)->getValue();
        
        // 处理合并单元格
        if (empty($cellValue) && isset($mergedCellData[$column][$row])) {
            $cellValue = $mergedCellData[$column][$row];
        }
        
        $rowData[$field] = $cellValue;
        
        $this->logService->info('单元格数据提取', [
            'row' => $row,
            'column' => $column,
            'field' => $field,
            'value' => $cellValue,
            'is_merged' => isset($mergedCellData[$column][$row])
        ]);
    }
    
    return $rowData;
}
```

### 阶段三：数据处理和标准化

#### 节点 3.1：设备型号识别算法
**开发内容：**
- 实现品牌识别算法
- 实现型号标准化
- 实现网络型号处理

**验收标准：**
- [ ] 品牌识别准确率>95%
- [ ] 型号标准化正确
- [ ] 网络型号处理正确

**调试代码：**
```php
public function recognizeDevice($modelName, $networkModel, $sheetName)
{
    // 品牌识别
    $brand = $this->recognizeBrand($sheetName, $modelName);
    
    // 型号标准化
    $standardModel = $this->standardizeModel($modelName);
    
    // 网络型号处理
    $standardNetwork = $this->standardizeNetworkModel($networkModel);
    
    $result = [
        'brand' => $brand,
        'model_name' => $standardModel,
        'network_model' => $standardNetwork,
        'original_data' => [
            'sheet' => $sheetName,
            'model' => $modelName,
            'network' => $networkModel
        ]
    ];
    
    $this->logService->info('设备识别结果', $result);
    
    return $result;
}
```

#### 节点 3.2：容量格式标准化
**开发内容：**
- 实现容量格式识别
- 实现容量单位转换
- 实现多容量组合处理

**验收标准：**
- [ ] 各种容量格式正确识别
- [ ] 单位转换准确
- [ ] 组合容量处理正确

**调试代码：**
```php
public function standardizeCapacity($capacity)
{
    $original = $capacity;
    
    // 移除空格
    $capacity = str_replace(' ', '', $capacity);
    
    // 标准化规则
    $patterns = [
        '/^(\d+)$/' => '$1GB',                    // 256 -> 256GB
        '/^(\d+)G$/' => '$1GB',                   // 256G -> 256GB
        '/^(\d+)GB$/' => '$1GB',                  // 保持不变
        '/^(\d+)\+(\d+)$/' => '$1GB+$2GB',       // 16+512 -> 16GB+512GB
        '/^(\d+)T$/' => '$1TB',                   // 1T -> 1TB
        '/^(\d+)TB$/' => '$1TB',                  // 保持不变
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        if (preg_match($pattern, $capacity)) {
            $capacity = preg_replace($pattern, $replacement, $capacity);
            break;
        }
    }
    
    $this->logService->info('容量标准化', [
        'original' => $original,
        'standardized' => $capacity
    ]);
    
    return $capacity;
}
```

#### 节点 3.3：价格数据验证和处理
**开发内容：**
- 实现价格格式验证
- 实现价格合理性检查
- 实现缺失价格处理

**验收标准：**
- [ ] 价格格式验证正确
- [ ] 异常价格能够识别
- [ ] 缺失价格处理合理

**调试代码：**
```php
public function processPriceData($rawPrices)
{
    $priceFields = ['高保充新', '充新', '靓机', '小花', '大花', '外爆', '内爆'];
    $processedPrices = [];
    $errors = [];
    
    foreach ($priceFields as $field) {
        $value = $rawPrices[$field] ?? '';
        
        $result = $this->validatePrice($value);
        
        $this->logService->info('价格处理', [
            'field' => $field,
            'original' => $value,
            'processed' => $result['price'],
            'is_valid' => $result['is_valid'],
            'error' => $result['error'] ?? null
        ]);
        
        if ($result['is_valid']) {
            $processedPrices[$field] = $result['price'];
        } else {
            $errors[] = "字段 {$field}: {$result['error']}";
        }
    }
    
    // 直接构建JSON格式的价格数据
    $priceJson = json_encode($processedPrices, JSON_UNESCAPED_UNICODE);
    
    $this->logService->info('价格JSON构建完成', [
        'price_json' => $priceJson,
        'price_count' => count($processedPrices)
    ]);
    
    return [
        'prices' => $processedPrices,      // 数组格式，用于处理
        'price_json' => $priceJson,       // JSON格式，用于存储
        'errors' => $errors
    ];
}
```

### 阶段四：设备管理功能

#### 节点 4.1：新设备检测算法
**开发内容：**
- 实现设备存在性检查
- 实现新设备标记
- 实现设备去重处理

**验收标准：**
- [ ] 新设备正确识别
- [ ] 重复设备正确处理
- [ ] 设备匹配准确

**调试代码：**
```php
public function checkDeviceExists($brandId, $modelName, $networkModel, $capacity)
{
    $exists = $this->deviceModel
        ->where('site_id', $this->site_id)
        ->where('brand_id', $brandId)
        ->where('model_name', $modelName)
        ->where('network_model', $networkModel)
        ->where('capacity', $capacity)
        ->find();
        
    $result = [
        'exists' => !empty($exists),
        'device_id' => $exists ? $exists['id'] : null,
        'search_params' => [
            'brand_id' => $brandId,
            'model_name' => $modelName,
            'network_model' => $networkModel,
            'capacity' => $capacity
        ]
    ];
    
    $this->logService->info('设备存在性检查', $result);
    
    return $result;
}
```

#### 节点 4.2：新设备自动入库
**开发内容：**
- 实现新设备自动创建
- 实现设备属性自动填充
- 实现设备状态管理

**验收标准：**
- [ ] 新设备创建成功
- [ ] 设备属性完整
- [ ] 状态设置正确

**调试代码：**
```php
public function createNewDevice($deviceData)
{
    $newDevice = [
        'site_id' => $this->site_id,
        'brand_id' => $deviceData['brand_id'],
        'model_name' => $deviceData['model_name'],
        'network_model' => $deviceData['network_model'],
        'capacity' => $deviceData['capacity'],
        'device_type' => $deviceData['device_type'],
        'status' => 1,
        'create_at' => time(),
        'update_at' => time()
    ];
    
    $this->logService->info('准备创建新设备', $newDevice);
    
    try {
        $deviceId = $this->deviceModel->create($newDevice);
        
        $this->logService->info('新设备创建成功', [
            'device_id' => $deviceId,
            'device_data' => $newDevice
        ]);
        
        return $deviceId;
    } catch (Exception $e) {
        $this->logService->error('新设备创建失败', [
            'error' => $e->getMessage(),
            'device_data' => $newDevice
        ]);
        throw $e;
    }
}
```

### 阶段五：价格更新机制

#### 节点 5.1：每日价格更新策略
**开发内容：**
- 实现价格历史版本管理
- 实现当前价格更新
- 实现价格变更记录

**验收标准：**
- [ ] 历史价格保留完整
- [ ] 当前价格更新正确
- [ ] 价格变更可追溯

**调试代码：**
```php
public function updateDailyPrices($deviceId, $newPrices, $priceDate, $importBatch)
{
    // 检查今日是否已有价格
    $existingPrice = $this->priceModel
        ->where('site_id', $this->site_id)
        ->where('device_model_id', $deviceId)
        ->where('price_date', $priceDate)
        ->find();
        
    $this->logService->info('价格更新检查', [
        'device_id' => $deviceId,
        'price_date' => $priceDate,
        'existing_price_id' => $existingPrice ? $existingPrice['id'] : null,
        'new_prices' => $newPrices
    ]);
    
    if ($existingPrice) {
        // 更新现有价格
        $result = $this->updateExistingPrice($existingPrice, $newPrices, $importBatch);
    } else {
        // 创建新价格记录
        $result = $this->createNewPrice($deviceId, $newPrices, $priceDate, $importBatch);
    }
    
    return $result;
}
```

#### 节点 5.2：价格变更对比算法
**开发内容：**
- 实现价格变更检测
- 实现变更幅度分析
- 实现异常变更预警

**验收标准：**
- [ ] 价格变更正确检测
- [ ] 变更幅度计算准确
- [ ] 异常预警正常触发

**调试代码：**
```php
public function comparePriceChanges($oldPriceJson, $newPrices)
{
    // 解析历史价格JSON
    $oldPrices = json_decode($oldPriceJson, true) ?? [];
    
    $changes = [];
    $warnings = [];
    
    foreach ($newPrices as $priceType => $newPrice) {
        $oldPrice = $oldPrices[$priceType] ?? 0;
        
        if ($oldPrice != $newPrice) {
            $changePercent = $oldPrice > 0 ? (($newPrice - $oldPrice) / $oldPrice) * 100 : 0;
            
            $change = [
                'type' => $priceType,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'change_amount' => $newPrice - $oldPrice,
                'change_percent' => round($changePercent, 2)
            ];
            
            // 异常变更检测（变更超过30%）
            if (abs($changePercent) > 30) {
                $warnings[] = "价格类型 {$priceType} 变更幅度过大: {$changePercent}%";
            }
            
            $changes[] = $change;
            
            $this->logService->info('价格变更检测', $change);
        }
    }
    
    if (!empty($warnings)) {
        $this->logService->warning('价格异常变更预警', $warnings);
    }
    
    // 记录完整的价格对比结果
    $this->logService->info('价格对比完成', [
        'old_price_json' => $oldPriceJson,
        'new_price_json' => json_encode($newPrices, JSON_UNESCAPED_UNICODE),
        'changes_count' => count($changes),
        'warnings_count' => count($warnings)
    ]);
    
    return [
        'changes' => $changes,
        'warnings' => $warnings
    ];
}
```

### 阶段六：批量处理和性能优化

#### 节点 6.1：分批处理机制
**开发内容：**
- 实现数据分批处理
- 实现内存使用监控
- 实现处理进度跟踪

**验收标准：**
- [ ] 分批处理正常
- [ ] 内存使用可控
- [ ] 进度跟踪准确

**调试代码：**
```php
public function processBatchData($allData, $batchSize = 500)
{
    $totalCount = count($allData);
    $batches = array_chunk($allData, $batchSize);
    $batchCount = count($batches);
    
    $this->logService->info('开始分批处理', [
        'total_rows' => $totalCount,
        'batch_size' => $batchSize,
        'batch_count' => $batchCount,
        'memory_usage' => memory_get_usage(true)
    ]);
    
    foreach ($batches as $batchIndex => $batchData) {
        $this->logService->info('处理批次', [
            'batch_index' => $batchIndex + 1,
            'batch_count' => $batchCount,
            'batch_size' => count($batchData),
            'memory_usage' => memory_get_usage(true)
        ]);
        
        $batchResult = $this->processSingleBatch($batchData, $batchIndex + 1);
        
        // 内存清理
        unset($batchData);
        gc_collect_cycles();
        
        $this->logService->info('批次处理完成', [
            'batch_index' => $batchIndex + 1,
            'success_count' => $batchResult['success'],
            'error_count' => $batchResult['errors'],
            'memory_usage' => memory_get_usage(true)
        ]);
    }
}
```

#### 节点 6.2：事务处理机制
**开发内容：**
- 实现数据库事务管理
- 实现回滚机制
- 实现异常恢复

**验收标准：**
- [ ] 事务正确提交
- [ ] 异常时正确回滚
- [ ] 数据一致性保证

**调试代码：**
```php
public function processWithTransaction($processFunction, $data)
{
    $this->model->startTrans();
    
    try {
        $this->logService->info('开始事务处理', [
            'data_count' => is_array($data) ? count($data) : 1
        ]);
        
        $result = $processFunction($data);
        
        $this->model->commit();
        
        $this->logService->info('事务提交成功', [
            'result' => $result
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        $this->model->rollback();
        
        $this->logService->error('事务回滚', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        throw $e;
    }
}
```

### 阶段七：前端界面开发

#### 节点 7.1：文件上传组件
**开发内容：**
- 实现Excel文件拖拽上传
- 实现上传进度显示
- 实现文件格式验证

**验收标准：**
- [ ] 文件上传正常
- [ ] 进度显示准确
- [ ] 格式验证生效

#### 节点 7.2：导入进度界面
**开发内容：**
- 实现实时进度显示
- 实现日志查看功能
- 实现错误信息展示

**验收标准：**
- [ ] 进度实时更新
- [ ] 日志显示正常
- [ ] 错误信息清晰

#### 节点 7.3：导入结果展示
**开发内容：**
- 实现导入结果统计
- 实现错误数据下载
- 实现成功数据预览

**验收标准：**
- [ ] 统计数据准确
- [ ] 错误数据可下载
- [ ] 预览功能正常

### 阶段八：测试和优化

#### 节点 8.1：单元测试
**开发内容：**
- 编写核心算法测试
- 编写数据处理测试
- 编写异常处理测试

**验收标准：**
- [ ] 测试覆盖率>80%
- [ ] 所有测试通过
- [ ] 边界情况测试通过

#### 节点 8.2：性能测试
**开发内容：**
- 测试大文件处理性能
- 测试内存使用情况
- 测试处理时间

**验收标准：**
- [ ] 10000条数据处理时间<5分钟
- [ ] 内存使用<512MB
- [ ] 系统响应正常

#### 节点 8.3：集成测试
**开发内容：**
- 测试完整导入流程
- 测试异常场景处理
- 测试数据一致性

**验收标准：**
- [ ] 完整流程正常
- [ ] 异常恢复正常
- [ ] 数据完整一致

## 日志和调试策略

### 日志级别定义
- **INFO**: 正常业务流程记录
- **WARNING**: 数据异常但可处理的情况
- **ERROR**: 处理失败需要人工干预的错误

### 关键调试点
1. Excel文件读取各阶段
2. 数据解析和转换过程
3. 设备识别和创建过程
4. 价格更新和对比过程
5. 批量处理各批次状态
6. 异常和错误处理过程

### 性能监控
```php
// 在关键节点添加性能监控
$startTime = microtime(true);
$startMemory = memory_get_usage(true);

// 业务处理...

$endTime = microtime(true);
$endMemory = memory_get_usage(true);

$this->logService->info('性能监控', [
    'operation' => '操作名称',
    'execution_time' => $endTime - $startTime,
    'memory_used' => $endMemory - $startMemory,
    'peak_memory' => memory_get_peak_usage(true)
]);
```

## 风险控制措施

### 数据安全
1. 导入前数据备份
2. 事务回滚机制
3. 操作日志完整记录

### 系统稳定性
1. 内存使用监控
2. 执行时间限制
3. 异常自动恢复

### 业务连续性
1. 分批处理避免阻塞
2. 异步处理机制
3. 手动干预接口

## 验收标准总览

### 功能验收
- [ ] Excel文件正确解析
- [ ] 合并单元格正确处理
- [ ] 设备信息正确识别
- [ ] 价格数据正确更新
- [ ] 新设备自动入库
- [ ] 日志记录完整准确

### 性能验收
- [ ] 10000条数据5分钟内处理完成
- [ ] 内存使用不超过512MB
- [ ] 系统响应时间正常

### 稳定性验收
- [ ] 异常文件不影响系统
- [ ] 数据错误可正确处理
- [ ] 系统故障可自动恢复

### 易用性验收
- [ ] 操作界面友好直观
- [ ] 错误提示清晰明确
- [ ] 处理进度实时显示

这个方案将整个功能分解为38个细小的开发节点，每个节点都有明确的开发内容、验收标准和调试代码。这样可以确保每一步都能得到充分验证，同时通过详细的日志记录，可以清楚地追踪整个处理过程。 