<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin;

use addon\recycle\app\model\RecycleDeviceBrand;
use addon\recycle\app\model\RecycleDeviceModel;
use addon\recycle\app\model\RecycleDevicePrice;
use addon\recycle\app\model\RecyclePriceImportRecord;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Log;
use think\facade\Db;
use think\facade\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Excel导入管理服务
 * Class ExcelImportService
 * @package addon\recycle\app\service\admin
 */
class ExcelImportService extends BaseAdminService
{
    /**
     * @var RecycleDeviceBrand
     */
    protected $brandModel;

    /**
     * @var RecycleDeviceModel
     */
    protected $modelModel;

    /**
     * @var RecycleDevicePrice
     */
    protected $priceModel;

    /**
     * @var RecyclePriceImportRecord
     */
    protected $importRecordModel;

    /**
     * 品牌映射
     * @var array
     */
    protected $brandMapping = [
        '苹果' => ['brand_code' => 'apple', 'brand_name' => '苹果'],
        '华为' => ['brand_code' => 'huawei', 'brand_name' => '华为'],
        '三星' => ['brand_code' => 'samsung', 'brand_name' => '三星'],
        '荣耀' => ['brand_code' => 'honor', 'brand_name' => '荣耀'],
        'OPPO' => ['brand_code' => 'oppo', 'brand_name' => 'OPPO'],
        '小米' => ['brand_code' => 'xiaomi', 'brand_name' => '小米'],
        'VIVO' => ['brand_code' => 'vivo', 'brand_name' => 'VIVO'],
        'vivo' => ['brand_code' => 'vivo', 'brand_name' => 'VIVO'],
        'vo' => ['brand_code' => 'vivo', 'brand_name' => 'VIVO'],
        'iqoo' => ['brand_code' => 'iqoo', 'brand_name' => 'iQOO']
    ];

    public function __construct()
    {
        parent::__construct();
        $this->brandModel = new RecycleDeviceBrand();
        $this->modelModel = new RecycleDeviceModel();
        $this->priceModel = new RecycleDevicePrice();
        $this->importRecordModel = new RecyclePriceImportRecord();
    }

    /**
     * 执行Excel文件导入
     * @param string $filePath 文件路径
     * @param array $options 选项
     * @return array
     */
    public function executeImport(string $filePath, array $options = []): array
    {
        $siteId = $this->site_id;
        $fileName = basename($filePath);
        $batchId = 'import_' . date('YmdHis');
        $startTime = time();

        try {
            // 验证文件
            if (!file_exists($filePath)) {
                throw new CommonException('文件不存在: ' . $filePath);
            }

            Log::info("开始Excel导入", [
                'file_path' => $filePath,
                'site_id' => $siteId,
                'batch_id' => $batchId
            ]);

            // 加载Excel文件
            $spreadsheet = IOFactory::load($filePath);
            $worksheetNames = $spreadsheet->getSheetNames();

            // 统计信息
            $stats = [
                'brands_inserted' => 0,
                'models_inserted' => 0,
                'prices_inserted' => 0,
                'total_processed' => 0,
                'sheets_processed' => 0
            ];

            $insertedBrands = [];

            // 开始事务
            Db::startTrans();

            foreach ($worksheetNames as $sheetIndex => $sheetName) {
                Log::info("处理工作表: {$sheetName}");

                // 检测品牌
                $brandInfo = $this->detectBrand($sheetName);
                if (!$brandInfo) {
                    Log::info("未识别品牌，跳过工作表: {$sheetName}");
                    continue;
                }

                // 处理品牌
                $brandId = $this->processBrand($brandInfo, $insertedBrands, $stats);
                if (!$brandId) {
                    continue;
                }

                // 处理工作表数据
                $sheetStats = $this->processWorksheet(
                    $spreadsheet->getSheet($sheetIndex),
                    $sheetName,
                    $brandId,
                    $batchId
                );

                $stats['models_inserted'] += $sheetStats['models_inserted'];
                $stats['prices_inserted'] += $sheetStats['prices_inserted'];
                $stats['total_processed'] += $sheetStats['total_processed'];
                $stats['sheets_processed']++;
            }

            // 创建导入记录
            $this->createImportRecord($batchId, $fileName, $stats, $startTime);

            Db::commit();

            // 导入完成后清除缓存
            try {
                Cache::tag('recycle')->clear();
            } catch (\Exception $e) {
                Log::warning('缓存清除失败：' . $e->getMessage());
            }

            Log::info("Excel导入完成", [
                'batch_id' => $batchId,
                'stats' => $stats,
                'summary' => "新增品牌: {$stats['brands_inserted']}, 新增型号: {$stats['models_inserted']}, 新增价格: {$stats['prices_inserted']}, 总处理行: {$stats['total_processed']}"
            ]);

            return [
                'batch_id' => $batchId,
                'stats' => $stats,
                'message' => "导入完成 - 新增品牌: {$stats['brands_inserted']}, 新增型号: {$stats['models_inserted']}, 新增价格: {$stats['prices_inserted']}, 总处理行: {$stats['total_processed']}"
            ];

        } catch (\Exception $e) {
            Db::rollback();
            Log::error('Excel导入失败：' . $e->getMessage(), [
                'file_path' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new CommonException('导入失败：' . $e->getMessage());
        }
    }

    /**
     * 检测品牌信息
     * @param string $sheetName
     * @return array|null
     */
    protected function detectBrand(string $sheetName): ?array
    {
        Log::info("检测品牌信息: {$sheetName}");
        foreach ($this->brandMapping as $key => $info) {
            if (strpos($sheetName, $key) !== false) {
                Log::info("匹配到品牌: {$key} -> {$info['brand_name']}");
                return $info;
            }
        }
        Log::warning("未找到匹配的品牌: {$sheetName}");
        return null;
    }

    /**
     * 处理品牌
     * @param array $brandInfo
     * @param array &$insertedBrands
     * @param array &$stats
     * @return int|null
     */
    protected function processBrand(array $brandInfo, array &$insertedBrands, array &$stats): ?int
    {
        $brandCode = $brandInfo['brand_code'];
        
        Log::info("处理品牌: {$brandInfo['brand_name']} (code: {$brandCode})");
        
        if (!in_array($brandCode, $insertedBrands)) {
            // 检查是否已存在 - 创建新的模型实例避免状态混淆
            $brandModel = new RecycleDeviceBrand();
            $existing = $brandModel->where([
                'site_id' => $this->site_id,
                'brand_code' => $brandCode
            ])->find();

            if (!$existing) {
                // 创建新品牌 - 使用新的模型实例
                $newBrandModel = new RecycleDeviceBrand();
                $brandData = [
                    'site_id' => $this->site_id,
                    'brand_name' => $brandInfo['brand_name'],
                    'brand_code' => $brandCode,
                    'status' => 1,
                    'create_at' => time(),
                    'update_at' => time()
                ];

                $newBrandModel->save($brandData);
                $stats['brands_inserted']++;
                Log::info("品牌插入成功: {$brandInfo['brand_name']}");
            } else {
                Log::info("品牌已存在: {$brandInfo['brand_name']} (ID: {$existing->id})");
            }

            $insertedBrands[] = $brandCode;
        } else {
            Log::info("品牌已在本次导入中处理过: {$brandInfo['brand_name']}");
        }

        // 获取品牌ID - 创建新的模型实例避免状态混淆
        $queryModel = new RecycleDeviceBrand();
        $brand = $queryModel->where([
            'site_id' => $this->site_id,
            'brand_code' => $brandCode
        ])->find();

        if ($brand) {
            Log::info("获取品牌ID成功: {$brandInfo['brand_name']} -> ID: {$brand->id}");
            return $brand->id;
        } else {
            Log::error("获取品牌ID失败: {$brandInfo['brand_name']}");
            return null;
        }
    }

    /**
     * 处理工作表数据
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet
     * @param string $sheetName
     * @param int $brandId
     * @param string $batchId
     * @return array
     */
    protected function processWorksheet($worksheet, string $sheetName, int $brandId, string $batchId): array
    {
        $highestRow = $worksheet->getHighestRow();
        $stats = [
            'models_inserted' => 0,
            'prices_inserted' => 0,
            'total_processed' => 0
        ];

        $lastModelName = '';
        $lastNetworkModel = '';
        $lastVersion = '';

        // 设备类型判断
        $deviceType = $this->detectDeviceType($sheetName);

        for ($row = 3; $row <= $highestRow; $row++) {
            // 首先读取A列数据进行关键字判断
            $cellAValue = trim((string)$worksheet->getCell('A' . $row)->getValue());
            
            // 跳过包含"型号"或"系列"关键字的行
            if (strpos($cellAValue, '型号') !== false || strpos($cellAValue, '系列') !== false) {
                Log::info("跳过包含关键字的行: 第{$row}行, A列内容: {$cellAValue}");
                continue;
            }
            
            // 根据设备类型读取不同的字段
            if ($deviceType === 'tablet') {
                // 平板：A列=型号，B列=网络型号，C列=版本，D列=容量
                $modelName = $cellAValue; // 使用已读取的A列值
                $networkModel = trim((string)$worksheet->getCell('B' . $row)->getValue());
                $version = trim((string)$worksheet->getCell('C' . $row)->getValue());
                $capacity = trim((string)$worksheet->getCell('D' . $row)->getValue());
            } elseif ($deviceType === 'watch') {
                // 手表：A列=型号，B列=尺寸，C列=内存，D列=版本
                $modelName = $cellAValue; // 使用已读取的A列值
                $size = trim((string)$worksheet->getCell('B' . $row)->getValue()); // 尺寸作为网络型号
                $memory = trim((string)$worksheet->getCell('C' . $row)->getValue()); // 内存作为容量
                $version = trim((string)$worksheet->getCell('D' . $row)->getValue());
                
                $networkModel = $size; // 将尺寸映射到网络型号字段
                $capacity = $memory;   // 将内存映射到容量字段
            } else {
                // 手机：A列=型号，B列=网络型号，C列=容量
                $modelName = $cellAValue; // 使用已读取的A列值
                $networkModel = trim((string)$worksheet->getCell('B' . $row)->getValue());
                $capacity = trim((string)$worksheet->getCell('C' . $row)->getValue());
                $version = ''; // 手机没有版本字段
            }

            // 合并单元格处理
            if (empty($modelName)) {
                $modelName = $lastModelName;
            } else {
                $lastModelName = $modelName;
            }

            if (empty($networkModel)) {
                $networkModel = $lastNetworkModel;
            } else {
                $lastNetworkModel = $networkModel;
            }

            if ($deviceType === 'tablet' || $deviceType === 'watch') {
                if (empty($version)) {
                    $version = $lastVersion;
                } else {
                    $lastVersion = $version;
                }
            }

            // 标准化容量
            $capacity = $this->standardizeCapacity($capacity);

            // 读取价格数据 - 根据设备类型调整价格列的起始位置
            $prices = $this->extractPrices($worksheet, $row, $deviceType);

            // 兼容性处理：如果按新映射读取不到价格，尝试使用手机映射
            if (empty($prices) && $deviceType !== 'phone') {
                $prices = $this->extractPrices($worksheet, $row, 'phone');
                // 如果用手机映射能读到价格，说明实际是手机格式，重新读取字段
                if (!empty($prices)) {
                    $modelName = $cellAValue; // 使用已读取的A列值
                    $networkModel = trim((string)$worksheet->getCell('B' . $row)->getValue());
                    $capacity = trim((string)$worksheet->getCell('C' . $row)->getValue());
                    $version = '';
                    
                    // 重新处理合并单元格
                    if (empty($modelName)) {
                        $modelName = $lastModelName;
                    } else {
                        $lastModelName = $modelName;
                    }
                    if (empty($networkModel)) {
                        $networkModel = $lastNetworkModel;
                    } else {
                        $lastNetworkModel = $networkModel;
                    }
                    
                    $capacity = $this->standardizeCapacity($capacity);
                }
            }

            // 验证数据有效性
            if (empty($capacity) || empty($prices) || empty($modelName)) {
                continue;
            }

            // 如果有版本信息，将其加入到网络型号中
            if (!empty($version) && ($deviceType === 'tablet' || $deviceType === 'watch')) {
                $networkModel = $networkModel . ' ' . $version;
            }

            // 处理设备型号和价格
            $result = $this->processDeviceAndPrice(
                $brandId,
                $modelName,
                $networkModel,
                $capacity,
                $deviceType,
                $prices,
                $batchId
            );

            if ($result['model_inserted']) {
                $stats['models_inserted']++;
            }
            if ($result['price_inserted']) {
                $stats['prices_inserted']++;
            }

            $stats['total_processed']++;
        }

        return $stats;
    }

    /**
     * 标准化容量格式
     * @param string $capacity
     * @return string
     */
    protected function standardizeCapacity(string $capacity): string
    {
        if (empty($capacity)) {
            return '';
        }

        if (preg_match('/^(\d+)$/', $capacity)) {
            $value = intval($capacity);
            return $value >= 1000 ? round($value / 1024, 1) . 'TB' : $value . 'GB';
        } elseif (preg_match('/^(\d+)T$/i', $capacity)) {
            return strtoupper($capacity);
        }

        return $capacity;
    }

    /**
     * 提取价格数据
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet
     * @param int $row
     * @param string $deviceType
     * @return array
     */
    protected function extractPrices($worksheet, int $row, string $deviceType): array
    {
        $prices = [];
        
        // 根据设备类型确定价格列的起始位置
        if ($deviceType === 'tablet') {
            // 平板：价格从E列开始
            $priceColumns = [
                'E' => '高保充新',
                'F' => '充新',
                'G' => '靓机',
                'H' => '小花',
                'I' => '大花',
                'J' => '外爆',
                'K' => '内爆'
            ];
        } elseif ($deviceType === 'watch') {
            // 手表：价格从E列开始
            $priceColumns = [
                'E' => '高保充新',
                'F' => '充新',
                'G' => '靓机',
                'H' => '小花',
                'I' => '大花',
                'J' => '外爆',
                'K' => '内爆'
            ];
        } else {
            // 手机：价格从D列开始
            $priceColumns = [
                'D' => '高保充新',
                'E' => '充新',
                'F' => '靓机',
                'G' => '小花',
                'H' => '大花',
                'I' => '外爆',
                'J' => '内爆'
            ];
        }

        foreach ($priceColumns as $col => $type) {
            $price = $worksheet->getCell($col . $row)->getValue();
            if (is_numeric($price) && $price > 0) {
                $prices[$type] = floatval($price);
            }
        }

        return $prices;
    }

    /**
     * 检测设备类型
     * @param string $sheetName
     * @return string
     */
    protected function detectDeviceType(string $sheetName): string
    {
        if (strpos($sheetName, '平板') !== false) {
            return 'tablet';
        } elseif (strpos($sheetName, '手表') !== false) {
            return 'watch';
        }
        return 'phone';
    }

    /**
     * 处理设备型号和价格
     * @param int $brandId
     * @param string $modelName
     * @param string $networkModel
     * @param string $capacity
     * @param string $deviceType
     * @param array $prices
     * @param string $batchId
     * @return array
     */
    protected function processDeviceAndPrice(
        int $brandId,
        string $modelName,
        string $networkModel,
        string $capacity,
        string $deviceType,
        array $prices,
        string $batchId
    ): array {
        $result = ['model_inserted' => false, 'price_inserted' => false];
        $modelId = null; // 初始化modelId变量

        // 插入或更新设备型号
        $modelData = [
            'site_id' => $this->site_id,
            'brand_id' => $brandId,
            'model_name' => $modelName,
            'network_model' => $networkModel,
            'capacity' => $capacity,
            'device_type' => $deviceType,
            'status' => 1,
            'create_at' => time(),
            'update_at' => time()
        ];

        // 查找现有型号 - 用更精确的条件查找
        $existingModel = $this->modelModel->where([
            'site_id' => $this->site_id,
            'brand_id' => $brandId,
            'model_name' => $modelName,
            'network_model' => $networkModel,
            'capacity' => $capacity
        ])->find();

        if ($existingModel) {
            $modelId = $existingModel->id;
            // 型号已存在，更新型号信息
            $this->modelModel->where('id', $modelId)->update([
                'update_at' => time()
            ]);
            Log::info("设备型号已存在，跳过插入: {$modelName} - {$capacity}");
        } else {
            // 尝试插入新型号，如果唯一索引冲突则查找现有记录
            try {
                // 创建新的模型实例以避免ID被复用
                $newModel = new \addon\recycle\app\model\RecycleDeviceModel();
                $newModel->save($modelData);
                $modelId = $newModel->id;
                $result['model_inserted'] = true;
                Log::info("新增设备型号: {$modelName} - {$capacity}");
            } catch (\Exception $e) {
                // 如果是唯一索引冲突，尝试重新查找
                if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), '1062') !== false) {
                    Log::info("设备型号插入冲突，查找现有记录: {$modelName} - {$capacity}");
                    $existingModel = $this->modelModel->where([
                        'site_id' => $this->site_id,
                        'brand_id' => $brandId,
                        'model_name' => $modelName,
                        'network_model' => $networkModel,
                        'capacity' => $capacity
                    ])->find();
                    
                    if ($existingModel) {
                        $modelId = $existingModel->id;
                        Log::info("找到冲突的设备型号，使用现有ID: {$modelId}");
                    } else {
                        Log::error("设备型号冲突但无法找到现有记录: {$modelName} - {$capacity}");
                        throw $e;
                    }
                } else {
                    // 其他类型的数据库错误，继续抛出
                    throw $e;
                }
            }
        }

        // 插入价格数据
        if (!$modelId) {
            Log::warning("ModelId为空，无法插入价格: {$modelName} - {$capacity}");
        } elseif (empty($prices)) {
            Log::warning("价格数据为空，跳过价格插入: {$modelName} - {$capacity}");
        } else {
            Log::info("开始插入价格数据: {$modelName} - {$capacity}, ModelId: {$modelId}, 价格数量: " . count($prices));
            
            $priceJson = json_encode($prices, JSON_UNESCAPED_UNICODE);
            $priceData = [
                'site_id' => $this->site_id,
                'device_model_id' => $modelId,
                'import_batch' => $batchId,
                'price_data' => $priceJson,
                'price_date' => date('Y-m-d'),
                'is_current' => 1,
                'status' => 1,
                'create_at' => time(),
                'update_at' => time()
            ];

            // 检查当日是否已有价格
            $existingPrice = $this->priceModel->where([
                'site_id' => $this->site_id,
                'device_model_id' => $modelId,
                'price_date' => date('Y-m-d')
            ])->find();

            if ($existingPrice) {
                // 更新价格
                $this->priceModel->where('id', $existingPrice->id)->update([
                    'price_data' => $priceJson,
                    'import_batch' => $batchId,
                    'update_at' => time()
                ]);
                Log::info("更新价格数据成功: {$modelName} - {$capacity}");
            } else {
                // 插入新价格
                $newPriceModel = new \addon\recycle\app\model\RecycleDevicePrice();
                $newPriceModel->save($priceData);
                Log::info("插入新价格数据成功: {$modelName} - {$capacity}");
            }

            $result['price_inserted'] = true;
        }

        // 清空缓存
      

        if (Cache::has('recycle_device_price')) {
            Cache::clear('recycle_device_price');
        }
        // Cache::clear('recycle_device_brand');

        return $result;
    }

    /**
     * 创建导入记录
     * @param string $batchId
     * @param string $fileName
     * @param array $stats
     * @param int $startTime
     */
    protected function createImportRecord(string $batchId, string $fileName, array $stats, int $startTime): void
    {
        $recordData = [
            'site_id' => $this->site_id,
            'import_batch' => $batchId,
            'file_name' => $fileName,
            'total_rows' => $stats['total_processed'],
            'success_rows' => $stats['models_inserted'],
            'failed_rows' => $stats['total_processed'] - $stats['models_inserted'],
            'status' => 1,
            'start_at' => $startTime,
            'end_at' => time(),
            'create_at' => time(),
            'summary' => json_encode($stats, JSON_UNESCAPED_UNICODE)
        ];

        $this->importRecordModel->save($recordData);
    }

    /**
     * 获取导入历史记录
     * @param array $where
     * @return array
     */
    public function getImportHistory(array $where = []): array
    {
        $field = 'id,import_batch,file_name,total_rows,success_rows,failed_rows,status,start_at,end_at,create_at';
        $order = 'create_at desc';

        $list = $this->importRecordModel->where([['site_id', '=', $this->site_id]])
            ->withSearch(['import_batch', 'file_name', 'status'], $where)
            ->field($field)
            ->order($order)
            ->select()
            ->toArray();

        return $list;
    }

    /**
     * 获取导入记录详情
     * @param int $id
     * @return array
     */
    public function getImportDetail(int $id): array
    {
        $info = $this->importRecordModel->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();

        if (empty($info)) {
            throw new CommonException('导入记录不存在');
        }

        // 解析summary
        if (!empty($info['summary'])) {
            $info['summary'] = json_decode($info['summary'], true);
        }

        return $info;
    }
} 