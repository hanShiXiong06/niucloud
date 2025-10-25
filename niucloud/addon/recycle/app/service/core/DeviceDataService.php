<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core;

use addon\recycle\app\model\RecycleDeviceBrand;
use addon\recycle\app\model\RecycleDeviceModel;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 设备数据处理服务
 * Class DeviceDataService
 * @package addon\recycle\app\service\core
 */
class DeviceDataService extends BaseCoreService
{
    /**
     * @var int
     */
    protected $site_id = 0;

    /**
     * @var RecycleDeviceBrand
     */
    protected $brandModel;

    /**
     * @var RecycleDeviceModel  
     */
    protected $modelModel;

    /**
     * 品牌映射表（工作表名称 -> 品牌代码）
     * @var array
     */
    protected $brandMapping = [
        // 手机品牌
        '苹果' => 'apple',
        'iPhone' => 'apple',
        'Apple' => 'apple',
        '华为' => 'huawei',
        'HUAWEI' => 'huawei',
        'Huawei' => 'huawei',
        '三星' => 'samsung',
        'Samsung' => 'samsung',
        'SAMSUNG' => 'samsung',
        '小米' => 'xiaomi',
        'Xiaomi' => 'xiaomi',
        'XIAOMI' => 'xiaomi',
        'MI' => 'xiaomi',
        'OPPO' => 'oppo',
        'oppo' => 'oppo',
        'VIVO' => 'vivo',
        'vivo' => 'vivo',
        '一加' => 'oneplus',
        'OnePlus' => 'oneplus',
        'ONEPLUS' => 'oneplus',
        
        // 平板品牌（扩展性预留）
        'iPad' => 'apple',
        '华为平板' => 'huawei',
        '小米平板' => 'xiaomi',
        '三星平板' => 'samsung',
        
        // 手表品牌（扩展性预留）
        'Apple Watch' => 'apple',
        '华为手表' => 'huawei',
        '小米手表' => 'xiaomi',
        'Galaxy Watch' => 'samsung'
    ];

    /**
     * 设备类型映射（工作表名称 -> 设备类型）
     * @var array
     */
    protected $deviceTypeMapping = [
        // 手机类型识别
        'iPhone' => 'phone',
        '苹果' => 'phone',
        '华为' => 'phone',
        '三星' => 'phone',
        '小米' => 'phone',
        'OPPO' => 'phone',
        'VIVO' => 'phone',
        '一加' => 'phone',
        
        // 平板类型识别
        'iPad' => 'tablet',
        '平板' => 'tablet',
        'Tablet' => 'tablet',
        '华为平板' => 'tablet',
        '小米平板' => 'tablet',
        '三星平板' => 'tablet',
        
        // 手表类型识别
        'Watch' => 'watch',
        '手表' => 'watch',
        'Apple Watch' => 'watch',
        '华为手表' => 'watch',
        '小米手表' => 'watch',
        'Galaxy Watch' => 'watch'
    ];

    /**
     * 容量格式标准化规则
     * @var array
     */
    protected $capacityRules = [
        // 单个存储容量
        '/^(\d+)\s*G\s*B?$/i' => '$1GB',
        '/^(\d+)\s*T\s*B?$/i' => '$1TB',
        '/^(\d+)\s*g\s*b?$/i' => '$1GB',
        '/^(\d+)\s*t\s*b?$/i' => '$1TB',
        '/^(\d+)G$/i' => '$1GB',
        '/^(\d+)T$/i' => '$1TB',
        
        // 内存+存储组合（如：8+256、16+512）
        '/^(\d+)\s*\+\s*(\d+)\s*G?\s*B?$/i' => '$1GB+$2GB',
        '/^(\d+)\s*\+\s*(\d+)\s*T?\s*B?$/i' => '$1GB+$2TB',
        '/^(\d+)\s*G\s*\+\s*(\d+)\s*G$/i' => '$1GB+$2GB',
        '/^(\d+)\s*G\s*\+\s*(\d+)\s*T$/i' => '$1GB+$2TB',
        
        // 特殊格式
        '/^(\d+)\s*G\s*B\s*\+\s*(\d+)\s*G\s*B$/i' => '$1GB+$2GB',
        '/^(\d+)\s*\/\s*(\d+)\s*G$/i' => '$1GB+$2GB',
        '/^(\d+)G\s*\+\s*(\d+)G$/i' => '$1GB+$2GB',
        
        // 只有数字的情况，根据大小判断单位
        '/^(\d+)$/' => 'auto' // 特殊处理
    ];

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->brandModel = new RecycleDeviceBrand();
        $this->modelModel = new RecycleDeviceModel();
    }

    /**
     * 标准化容量格式
     * @param string $capacity
     * @return string
     */
    public function standardizeCapacity(string $capacity, string $deviceType = 'phone'): string
    {
        if (empty($capacity)) {
            return '';
        }

        $capacity = trim($capacity);
        $original = $capacity;

        // 移除多余的空格和特殊字符
        $capacity = preg_replace('/\s+/', ' ', $capacity);
        $capacity = str_replace(['，', '。', '；'], [',', '.', ';'], $capacity);

        // 根据设备类型进行特殊处理
        $capacity = $this->applyDeviceTypeSpecificRules($capacity, $deviceType);

        // 应用标准化规则
        foreach ($this->capacityRules as $pattern => $replacement) {
            if ($replacement === 'auto') {
                // 特殊处理：只有数字的情况
                if (preg_match($pattern, $capacity, $matches)) {
                    $num = (int)$matches[1];
                    if ($num >= 1000) {
                        // 大于等于1000认为是TB
                        $capacity = ($num / 1000) . 'TB';
                    } else {
                        // 小于1000认为是GB
                        $capacity = $num . 'GB';
                    }
                    break;
                }
            } else {
                $newCapacity = preg_replace($pattern, $replacement, $capacity);
                if ($newCapacity !== $capacity) {
                    $capacity = $newCapacity;
                    break;
                }
            }
        }

        // 记录转换过程
        if ($capacity !== $original) {
            Log::info("容量格式标准化: '{$original}' -> '{$capacity}'");
        }

        return $capacity;
    }

    /**
     * 高级容量标准化（使用AdvancedCapacityProcessor）
     * @param string $capacity
     * @param string $deviceType
     * @param array $options
     * @return array
     */
    public function advancedStandardizeCapacity(string $capacity, string $deviceType = 'phone', array $options = []): array
    {
        return $this->advancedCapacityProcessor->advancedStandardize($capacity, $deviceType, $options);
    }

    /**
     * 批量容量标准化处理
     * @param array $capacities
     * @param string $deviceType
     * @param array $options
     * @return array
     */
    public function batchStandardizeCapacities(array $capacities, string $deviceType = 'phone', array $options = []): array
    {
        return $this->advancedCapacityProcessor->batchStandardize($capacities, $deviceType, $options);
    }

    /**
     * 智能容量建议
     * @param string $capacity
     * @param string $deviceType
     * @return array
     */
    public function getCapacitySuggestions(string $capacity, string $deviceType = 'phone'): array
    {
        return $this->advancedCapacityProcessor->suggestCapacityFormat($capacity, $deviceType);
    }

    /**
     * 解析品牌信息（增强版，支持设备类型检测）
     * @param string $sheetName
     * @param string $brandText
     * @return array
     */
    public function parseBrandInfo(string $sheetName, string $brandText = ''): array
    {
        // 优先从工作表名称解析品牌
        $brandCode = $this->getBrandCodeFromText($sheetName);
        $brandName = $sheetName;

        // 如果工作表名称无法识别，尝试从品牌文本解析
        if (!$brandCode && !empty($brandText)) {
            $brandCode = $this->getBrandCodeFromText($brandText);
            $brandName = $brandText;
        }

        // 如果还是无法识别，使用工作表名称作为品牌名
        if (!$brandCode) {
            $brandCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sheetName));
            $brandName = $sheetName;
        }

        // 检测设备类型
        $deviceType = $this->detectDeviceType($sheetName, $brandText);

        return [
            'brand_name' => $brandName,
            'brand_code' => $brandCode,
            'device_type' => $deviceType,
            'is_recognized' => isset($this->brandMapping[$sheetName]) || isset($this->brandMapping[$brandText]),
            'type_recognized' => $deviceType !== 'unknown'
        ];
    }

    /**
     * 检测设备类型
     * @param string $sheetName
     * @param string $brandText
     * @return string
     */
    public function detectDeviceType(string $sheetName, string $brandText = ''): string
    {
        // 优先从工作表名称检测
        $deviceType = $this->getDeviceTypeFromText($sheetName);
        
        // 如果工作表名称无法识别，尝试从品牌文本检测
        if ($deviceType === 'unknown' && !empty($brandText)) {
            $deviceType = $this->getDeviceTypeFromText($brandText);
        }
        
        // 默认为手机类型
        if ($deviceType === 'unknown') {
            $deviceType = 'phone';
        }
        
        return $deviceType;
    }

    /**
     * 从文本中获取设备类型
     * @param string $text
     * @return string
     */
    protected function getDeviceTypeFromText(string $text): string
    {
        $text = trim($text);
        
        // 精确匹配
        if (isset($this->deviceTypeMapping[$text])) {
            return $this->deviceTypeMapping[$text];
        }

        // 模糊匹配
        foreach ($this->deviceTypeMapping as $keyword => $type) {
            if (stripos($text, $keyword) !== false) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * 从文本中获取品牌代码
     * @param string $text
     * @return string|null
     */
    protected function getBrandCodeFromText(string $text): ?string
    {
        $text = trim($text);
        
        // 精确匹配
        if (isset($this->brandMapping[$text])) {
            return $this->brandMapping[$text];
        }

        // 模糊匹配
        foreach ($this->brandMapping as $keyword => $code) {
            if (stripos($text, $keyword) !== false) {
                return $code;
            }
        }

        return null;
    }

    /**
     * 解析设备数据行
     * @param array $rowData
     * @param array $headerMapping
     * @param string $deviceType
     * @return array
     */
    public function parseDeviceRow(array $rowData, array $headerMapping, string $deviceType = 'phone'): array
    {
        $deviceInfo = [
            'model_name' => '',
            'network_model' => '',
            'capacity' => '',
            'prices' => [],
            'remarks' => '',
            'original_data' => $rowData
        ];

        // 根据表头映射提取数据
        foreach ($headerMapping as $col => $header) {
            $value = trim($rowData[$col] ?? '');
            
            switch ($header) {
                case '型号':
                    $deviceInfo['model_name'] = $value;
                    break;
                case '网络型号':
                    $deviceInfo['network_model'] = $value;
                    break;
                case '容量':
                    $deviceInfo['capacity'] = $value;
                    break;
                case '备注':
                    $deviceInfo['remarks'] = $value;
                    break;
                case '高保充新':
                case '充新':
                case '靓机':
                case '小花':
                case '大花':
                case '外爆':
                case '内爆':
                    // 解析价格
                    $price = $this->parsePrice($value);
                    if ($price > 0) {
                        $deviceInfo['prices'][$header] = $price;
                    }
                    break;
            }
        }

        // 标准化容量（使用高级处理器）
        if (!empty($deviceInfo['capacity'])) {
            $advancedResult = $this->advancedStandardizeCapacity($deviceInfo['capacity'], $deviceType);
            
            if ($advancedResult['success']) {
                $deviceInfo['capacity'] = $advancedResult['result'];
                $deviceInfo['capacity_processing'] = [
                    'method' => $advancedResult['method'],
                    'original' => $advancedResult['original'],
                    'converted' => $advancedResult['converted']
                ];
            } else {
                // 回退到基础处理方法
                $deviceInfo['capacity'] = $this->standardizeCapacity($deviceInfo['capacity'], $deviceType);
                $deviceInfo['capacity_processing'] = [
                    'method' => 'fallback_basic',
                    'original' => $deviceInfo['capacity'],
                    'converted' => false
                ];
            }
        }

        return $deviceInfo;
    }

    /**
     * 解析价格字符串
     * @param string $priceStr
     * @return float
     */
    protected function parsePrice(string $priceStr): float
    {
        if (empty($priceStr)) {
            return 0.0;
        }

        // 移除非数字字符（保留小数点）
        $priceStr = preg_replace('/[^\d.]/', '', $priceStr);
        
        return (float)$priceStr;
    }

    /**
     * 验证设备数据完整性
     * @param array $deviceInfo
     * @return array
     */
    public function validateDeviceData(array $deviceInfo): array
    {
        $errors = [];

        // 检查必填字段
        if (empty($deviceInfo['model_name'])) {
            $errors[] = '设备型号不能为空';
        }

        // 检查价格数据
        if (empty($deviceInfo['prices'])) {
            $errors[] = '至少需要一个价格信息';
        }

        // 检查容量格式
        if (!empty($deviceInfo['capacity'])) {
            $standardized = $this->standardizeCapacity($deviceInfo['capacity']);
            if ($standardized === $deviceInfo['capacity'] && !preg_match('/^\d+(GB|TB)(\+\d+(GB|TB))?$/', $standardized)) {
                $errors[] = "容量格式无法识别: {$deviceInfo['capacity']}";
            }
        }

        if (!empty($errors)) {
            return fail('数据验证失败', ['errors' => $errors]);
        }

        return success('数据验证通过');
    }

    /**
     * 获取品牌映射表
     * @return array
     */
    public function getBrandMapping(): array
    {
        return $this->brandMapping;
    }

    /**
     * 添加品牌映射
     * @param string $keyword
     * @param string $brandCode
     * @return void
     */
    public function addBrandMapping(string $keyword, string $brandCode): void
    {
        $this->brandMapping[$keyword] = $brandCode;
    }

    /**
     * 测试容量标准化
     * @param array $testCases
     * @return array
     */
    public function testCapacityStandardization(array $testCases): array
    {
        $results = [];
        
        foreach ($testCases as $input) {
            $output = $this->standardizeCapacity($input);
            $results[] = [
                'input' => $input,
                'output' => $output,
                'changed' => $input !== $output
            ];
        }
        
        return $results;
    }

    /**
     * 根据设备类型应用特殊规则
     * @param string $capacity
     * @param string $deviceType
     * @return string
     */
    protected function applyDeviceTypeSpecificRules(string $capacity, string $deviceType): string
    {
        switch ($deviceType) {
            case 'tablet':
                return $this->applyTabletSpecificRules($capacity);
            case 'watch':
                return $this->applyWatchSpecificRules($capacity);
            case 'phone':
            default:
                return $this->applyPhoneSpecificRules($capacity);
        }
    }

    /**
     * 应用手机特殊规则
     * @param string $capacity
     * @return string
     */
    protected function applyPhoneSpecificRules(string $capacity): string
    {
        // 手机特殊规则：运存+存储组合常见
        
        // 规则1: 无单位的运存+存储 (如: "8256" -> "8GB+256GB")
        if (preg_match('/^(\d)(\d{3})$/', $capacity, $matches)) {
            return $matches[1] . 'GB+' . $matches[2] . 'GB';
        }

        // 规则2: 处理常见的手机容量范围
        if (preg_match('/^(\d+)$/', $capacity, $matches)) {
            $size = (int)$matches[1];
            // 手机常见容量：16GB, 32GB, 64GB, 128GB, 256GB, 512GB, 1TB
            if ($size >= 1000) {
                return round($size / 1024, 1) . 'TB';
            } else {
                return $size . 'GB';
            }
        }

        return $capacity;
    }

    /**
     * 应用平板特殊规则
     * @param string $capacity
     * @return string
     */
    protected function applyTabletSpecificRules(string $capacity): string
    {
        // 平板特殊规则：存储容量通常更大，WiFi/LTE版本区分
        
        // 规则1: WiFi/LTE版本标识 (如: "128GB WiFi", "256GB LTE")
        if (preg_match('/^(\d+)(GB|TB)?\s*(WiFi|LTE|Cellular)$/i', $capacity, $matches)) {
            $size = $matches[1];
            $unit = strtoupper($matches[2]) ?: 'GB';
            $version = strtoupper($matches[3]);
            return $size . $unit . ' ' . $version;
        }

        // 规则2: 纯数字，平板一般是较大存储
        if (preg_match('/^(\d+)$/', $capacity, $matches)) {
            $size = (int)$matches[1];
            // 平板常见容量：32GB, 64GB, 128GB, 256GB, 512GB, 1TB, 2TB
            if ($size >= 2048) {
                return round($size / 1024, 1) . 'TB';
            } else {
                return $size . 'GB';
            }
        }

        // 规则3: 运存+存储，平板通常运存更大
        if (preg_match('/^(\d+)([GT]?B?)\s*[\+\-]\s*(\d+)([GT]?B?)$/i', $capacity, $matches)) {
            $ram = (int)$matches[1];
            $storage = (int)$matches[3];
            
            // 平板运存一般8GB起步，存储128GB起步
            $ramUnit = 'GB';
            $storageUnit = 'GB';
            
            return $ram . $ramUnit . '+' . $storage . $storageUnit;
        }

        return $capacity;
    }

    /**
     * 应用手表特殊规则
     * @param string $capacity
     * @return string
     */
    protected function applyWatchSpecificRules(string $capacity): string
    {
        // 手表特殊规则：存储容量较小，尺寸+功能组合

        // 规则1: 尺寸+容量格式 (如: "44mm 32GB", "40mm GPS")
        if (preg_match('/^(\d+)mm\s*(\d+)?(GB|MB)?$/i', $capacity, $matches)) {
            $size = $matches[1] . 'mm';
            if (!empty($matches[2])) {
                $storage = $matches[2];
                $unit = strtoupper($matches[3]) ?: 'GB';
                $size .= ' ' . $storage . $unit;
            }
            return $size;
        }

        // 规则2: GPS/LTE版本 (如: "GPS", "GPS+Cellular", "LTE")
        if (preg_match('/^(GPS|LTE|Cellular)(\+\w+)?$/i', $capacity)) {
            return strtoupper($capacity);
        }

        // 规则3: 组合格式 (如: "44mm GPS 32GB", "40mm LTE 64GB")
        if (preg_match('/^(\d+)mm\s+(GPS|LTE|Cellular)\s*(\d+)?(GB|MB)?$/i', $capacity, $matches)) {
            $result = $matches[1] . 'mm ' . strtoupper($matches[2]);
            if (!empty($matches[3])) {
                $storage = $matches[3];
                $unit = strtoupper($matches[4]) ?: 'GB';
                $result .= ' ' . $storage . $unit;
            }
            return $result;
        }

        // 规则4: 纯数字，手表存储一般较小
        if (preg_match('/^(\d+)$/', $capacity, $matches)) {
            $size = (int)$matches[1];
            if ($size <= 64) {
                return $size . 'GB'; // 手表通常8GB、16GB、32GB、64GB等
            } else {
                return $size . 'MB'; // 可能是MB单位
            }
        }

        return $capacity;
    }

    /**
     * 获取设备类型映射表
     * @return array
     */
    public function getDeviceTypeMapping(): array
    {
        return $this->deviceTypeMapping;
    }

    /**
     * 添加设备类型映射
     * @param string $keyword
     * @param string $deviceType
     * @return void
     */
    public function addDeviceTypeMapping(string $keyword, string $deviceType): void
    {
        $this->deviceTypeMapping[$keyword] = $deviceType;
    }

    /**
     * 测试设备类型检测
     * @param array $testCases
     * @return array
     */
    public function testDeviceTypeDetection(array $testCases): array
    {
        $results = [];
        
        foreach ($testCases as $input) {
            $deviceType = $this->detectDeviceType($input);
            $results[] = [
                'input' => $input,
                'detected_type' => $deviceType,
                'is_recognized' => $deviceType !== 'unknown'
            ];
        }
        
        return $results;
    }

    /**
     * 查找或创建设备型号
     * @param int $siteId
     * @param array $deviceData
     * @return int|null
     */
    public function findOrCreateDeviceModel(int $siteId, array $deviceData): ?int
    {
        $modelName = $deviceData['model_name'] ?? '';
        $networkModel = $deviceData['network_model'] ?? '';
        $capacity = $deviceData['capacity'] ?? '';

        if (empty($modelName)) {
            return null;
        }

        // 先查找现有的设备型号
        $existing = $this->modelModel->where([
            'site_id' => $siteId,
            'model_name' => $modelName
        ])->find();

        if ($existing) {
            return $existing->id;
        }

        // 创建新的设备型号
        try {
            // 从型号名称中提取品牌
            $brandCode = $this->extractBrandFromModel($modelName);
            
            // 确保品牌存在
            $brandId = $this->findOrCreateBrand($siteId, $brandCode);
            
            // 标准化容量
            $standardizedCapacity = $this->standardizeCapacity($capacity);
            
            // 检测设备类型
            $deviceType = $this->detectDeviceType($modelName);

            $data = [
                'site_id' => $siteId,
                'brand_id' => $brandId,
                'model_name' => $modelName,
                'network_model' => $networkModel,
                'capacity' => $standardizedCapacity,
                'device_type' => $deviceType,
                'status' => 1,
                'create_time' => time(),
                'update_time' => time()
            ];

            $this->modelModel->save($data);
            
            Log::info("创建设备型号成功", [
                'model_name' => $modelName,
                'brand_code' => $brandCode,
                'device_type' => $deviceType,
                'capacity' => $standardizedCapacity
            ]);
            
            return $this->modelModel->id;
            
        } catch (\Exception $e) {
            Log::error('创建设备型号失败：' . $e->getMessage(), $deviceData);
            return null;
        }
    }

    /**
     * 从型号中提取品牌代码
     * @param string $modelName
     * @return string
     */
    protected function extractBrandFromModel(string $modelName): string
    {
        // 去除空格并转换为小写进行匹配
        $cleanName = strtolower(trim($modelName));
        
        // 按匹配优先级排序
        $brandPatterns = [
            'iphone' => 'apple',
            'ipad' => 'apple',
            'apple' => 'apple',
            'samsung' => 'samsung',
            'galaxy' => 'samsung',
            'huawei' => 'huawei',
            'honor' => 'huawei', // 荣耀归华为
            'xiaomi' => 'xiaomi',
            'redmi' => 'xiaomi', // 红米归小米
            'oppo' => 'oppo',
            'oneplus' => 'oppo', // 一加归OPPO
            'vivo' => 'vivo',
            'iqoo' => 'vivo', // iQOO归vivo
            'realme' => 'oppo', // realme归OPPO
        ];

        foreach ($brandPatterns as $pattern => $brandCode) {
            if (strpos($cleanName, $pattern) !== false) {
                return $brandCode;
            }
        }

        return 'other';
    }

    /**
     * 查找或创建品牌
     * @param int $siteId
     * @param string $brandCode
     * @return int
     */
    protected function findOrCreateBrand(int $siteId, string $brandCode): int
    {
        // 查找现有品牌
        $existing = $this->brandModel->where([
            'site_id' => $siteId,
            'brand_code' => $brandCode
        ])->find();

        if ($existing) {
            Log::info("使用现有品牌", ['brand_code' => $brandCode, 'brand_id' => $existing->id]);
            return $existing->id;
        }

        // 创建新品牌
        $brandNames = [
            'apple' => '苹果',
            'samsung' => '三星',
            'huawei' => '华为',
            'xiaomi' => '小米',
            'oppo' => 'OPPO',
            'vivo' => 'vivo',
            'honor' => '荣耀',
            'iqoo' => 'iQOO',
            'other' => '其他'
        ];

        $brandData = [
            'site_id' => $siteId,
            'brand_code' => $brandCode,
            'brand_name' => $brandNames[$brandCode] ?? $brandCode,
            'status' => 1,
            'create_time' => time(),
            'update_time' => time()
        ];

        try {
            $this->brandModel->save($brandData);
            Log::info("创建品牌成功", ['brand_code' => $brandCode, 'brand_name' => $brandNames[$brandCode] ?? $brandCode]);
            return $this->brandModel->id;
        } catch (\Exception $e) {
            // 如果插入失败（可能因为并发导致重复），再次查找
            Log::warning("品牌插入失败，尝试重新查找", ['brand_code' => $brandCode, 'error' => $e->getMessage()]);
            
            $existing = $this->brandModel->where([
                'site_id' => $siteId,
                'brand_code' => $brandCode
            ])->find();
            
            if ($existing) {
                return $existing->id;
            }
            
            throw $e; // 如果还是找不到，抛出原异常
        }
    }
}