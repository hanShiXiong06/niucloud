# Excel价格导入核心类设计

## 🎯 核心功能分析

### 🔥 最关键的3个技术点
1. **合并单元格智能处理** - 解析Excel中型号字段的合并单元格
2. **数据标准化转换** - 统一品牌、容量、价格格式
3. **批量数据导入** - 高效插入1万条数据

## 🏗️ 核心类设计（4个主要类）

### 1. 📊 ExcelParserService - Excel解析服务
**职责**：负责Excel文件的读取、解析和合并单元格处理

```php
class ExcelParserService
{
    // === 核心方法 ===
    
    /**
     * 解析Excel文件主入口
     * @param string $filePath Excel文件路径
     * @return array 解析结果
     */
    public function parseExcelFile(string $filePath): array
    
    /**
     * 处理单个工作表
     * @param Worksheet $worksheet 工作表对象
     * @param string $brand 品牌名（从工作表名提取）
     * @return array 工作表数据
     */
    private function parseWorksheet($worksheet, string $brand): array
    
    /**
     * 智能处理合并单元格（核心算法）
     * @param array $rawData 原始数据
     * @return array 处理后的完整数据
     */
    private function handleMergedCells(array $rawData): array
    
    /**
     * 识别并提取品牌信息
     * @param string $sheetName 工作表名
     * @return array [brand, category_id]
     */
    private function extractBrandInfo(string $sheetName): array
    
    /**
     * 验证表头结构
     * @param array $headers 表头数组
     * @return bool 是否符合标准格式
     */
    private function validateHeaders(array $headers): bool
}
```

**核心算法**：合并单元格处理
- 扫描A列(型号)的空值行
- 向上查找最近的非空值
- 同时处理B列(网络型号)的关联填充
- 维护数据完整性

---

### 2. 🔄 PriceDataProcessor - 数据处理器
**职责**：数据标准化、验证和清洗

```php
class PriceDataProcessor
{
    // === 核心方法 ===
    
    /**
     * 数据标准化主处理
     * @param array $rawData 原始解析数据
     * @return array 标准化后数据
     */
    public function processData(array $rawData): array
    
    /**
     * 标准化容量格式
     * @param string $capacity 原始容量
     * @return string 标准化容量
     */
    private function standardizeCapacity(string $capacity): string
    
    /**
     * 处理价格数据
     * @param array $priceRow 价格行数据
     * @return array 处理后的价格数组
     */
    private function processPrices(array $priceRow): array
    
    /**
     * 数据验证
     * @param array $item 单条数据
     * @return array [isValid, errors]
     */
    private function validateDataItem(array $item): array
    
    /**
     * 价格合理性检查
     * @param array $prices 价格数组
     * @return array 验证结果
     */
    private function validatePriceLogic(array $prices): array
    
    /**
     * 去重处理
     * @param array $data 待处理数据
     * @return array 去重后数据
     */
    private function removeDuplicates(array $data): array
}
```

**核心算法**：数据标准化
- 容量格式统一：`256` → `256GB`, `16+512` → `16GB+512GB`
- 价格验证：递减规律检查、范围验证
- 重复数据检测：brand+model+capacity唯一性

---

### 3. 💾 PriceImportService - 导入服务
**职责**：批量数据导入、事务处理、日志记录

```php
class PriceImportService extends BaseAdminService
{
    // === 核心方法 ===
    
    /**
     * 执行导入主流程
     * @param string $filePath 文件路径
     * @param array $options 导入选项
     * @return array 导入结果
     */
    public function executeImport(string $filePath, array $options = []): array
    
    /**
     * 批量插入临时表
     * @param array $data 处理后的数据
     * @param string $batchNo 批次号
     * @return bool 插入结果
     */
    private function batchInsertTemp(array $data, string $batchNo): bool
    
    /**
     * 从临时表转入正式表
     * @param string $batchNo 批次号
     * @return array 转入结果
     */
    private function transferToStandard(string $batchNo): array
    
    /**
     * 处理重复数据
     * @param array $duplicates 重复数据
     * @param string $strategy 处理策略 (skip|update|merge)
     * @return bool 处理结果
     */
    private function handleDuplicates(array $duplicates, string $strategy): bool
    
    /**
     * 创建导入日志
     * @param array $importData 导入信息
     * @return int 日志ID
     */
    private function createImportLog(array $importData): int
    
    /**
     * 更新导入进度
     * @param int $logId 日志ID
     * @param array $progress 进度信息
     * @return bool 更新结果
     */
    private function updateProgress(int $logId, array $progress): bool
    
    /**
     * 数据回滚
     * @param string $batchNo 批次号
     * @return bool 回滚结果
     */
    public function rollbackImport(string $batchNo): bool
}
```

**核心算法**：批量处理
- 分批插入：每500条一批，避免内存溢出
- 事务处理：确保数据一致性
- 进度跟踪：实时更新导入状态

---

### 4. 🎮 PriceImportController - 控制器
**职责**：API接口、前端交互、流程控制

```php
class PriceImportController extends BaseAdminController
{
    // === 核心方法 ===
    
    /**
     * 文件上传接口
     * @return Response
     */
    public function uploadFile(): Response
    
    /**
     * 开始导入接口
     * @return Response
     */
    public function startImport(): Response
    
    /**
     * 获取导入进度
     * @return Response
     */
    public function getProgress(): Response
    
    /**
     * 获取导入结果
     * @return Response
     */
    public function getResult(): Response
    
    /**
     * 获取导入历史
     * @return Response
     */
    public function getHistory(): Response
    
    /**
     * 数据回滚
     * @return Response
     */
    public function rollback(): Response
}
```

---

## 🗄️ 配套模型类（3个）

### 数据模型
```php
// 临时导入表模型
class RecyclePriceImport extends BaseModel

// 标准价格表模型  
class RecyclePriceStandard extends BaseModel

// 导入日志表模型
class RecyclePriceImportLog extends BaseModel
```

---

## 🔄 核心调用流程

```
Controller.startImport()
    ↓
ImportService.executeImport()
    ↓
ParserService.parseExcelFile()
    ├── handleMergedCells() ← 核心算法1
    └── extractBrandInfo()
    ↓
DataProcessor.processData()
    ├── standardizeCapacity() ← 核心算法2
    ├── processPrices()
    └── validateDataItem()
    ↓
ImportService.batchInsertTemp() ← 核心算法3
    ↓
ImportService.transferToStandard()
    ↓
返回导入结果
```

## 📊 开发优先级

### 🥇 第一优先级（核心必须）
1. **ExcelParserService.handleMergedCells()** - 合并单元格处理算法
2. **PriceDataProcessor.standardizeCapacity()** - 容量标准化
3. **PriceImportService.batchInsertTemp()** - 批量插入

### 🥈 第二优先级（功能完善）
4. 数据验证逻辑
5. 错误处理机制
6. 进度跟踪功能

### 🥉 第三优先级（用户体验）
7. 前端界面
8. 历史记录
9. 数据回滚

## 💡 技术要点

### 🔧 关键技术实现
1. **合并单元格算法**：
   ```php
   // 向上查找非空值，智能填充
   for ($row = 3; $row <= $maxRow; $row++) {
       if (empty($data[$row]['model'])) {
           $data[$row]['model'] = $lastModel;
       } else {
           $lastModel = $data[$row]['model'];
       }
   }
   ```

2. **分批处理策略**：
   ```php
   // 每批500条，避免内存溢出
   $chunks = array_chunk($data, 500);
   foreach ($chunks as $chunk) {
       $this->batchInsert($chunk);
   }
   ```

3. **事务安全**：
   ```php
   Db::startTrans();
   try {
       // 批量操作
       Db::commit();
   } catch (Exception $e) {
       Db::rollback();
   }
   ```

## 📝 总结

**4个核心类，约20个关键方法**，重点在：
- **合并单元格智能处理算法**（最复杂）
- **数据标准化引擎**（保证质量）
- **批量高性能导入**（处理大数据量）

按优先级实现，先攻克核心技术难点，再完善功能和体验。 