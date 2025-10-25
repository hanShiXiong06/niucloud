<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core;

use addon\recycle\app\model\RecycleDevicePrice;
use addon\recycle\app\model\RecyclePriceImportRecord;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Log;
use think\facade\Db;

/**
 * 价格导入业务服务
 * Class PriceImportService
 * @package addon\recycle\app\service\core
 */
class PriceImportService extends BaseCoreService
{
    /**
     * @var int
     */
    protected $site_id = 0;

    /**
     * @var ExcelImportService
     */
    protected $excelService;

    /**
     * @var DeviceDataService
     */
    protected $deviceService;

    /**
     * @var ImportLogService
     */
    protected $logService;

    /**
     * @var RecycleDevicePrice
     */
    protected $priceModel;

    /**
     * @var RecyclePriceImportRecord
     */
    protected $importRecordModel;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->excelService = new ExcelImportService();
        $this->deviceService = new DeviceDataService();
        $this->logService = new ImportLogService();
        $this->priceModel = new RecycleDevicePrice();
        $this->importRecordModel = new RecyclePriceImportRecord();
    }

    /**
     * 执行完整的价格导入流程
     * @param string $filePath
     * @param array $options
     * @return array
     */
    public function executeImport(string $filePath, array $options = []): array
    {
        $siteId = $options['site_id'] ?? 100000;
        $operatorId = $options['operator_id'] ?? 0;
        $fileName = basename($filePath);

        try {
            // 1. 创建导入记录
            $importRecord = $this->createImportRecord($siteId, $fileName, $filePath, $operatorId);
            $importRecordId = $importRecord['data']['id'];

            $this->logService->logProcessStep($importRecordId, '导入开始', "开始处理文件: {$fileName}", [
                'site_id' => $siteId,
                'file_path' => $filePath
            ]);

            // 2. 验证文件
            $validation = $this->excelService->validateFile($filePath);
            if (!$validation['code']) {
                $this->completeImportWithError($importRecordId, $validation['msg']);
                return fail($validation['msg']);
            }

            $this->logService->logProcessStep($importRecordId, '文件验证', '文件验证通过', [
                'site_id' => $siteId,
                'file_info' => $validation['data']
            ]);

            // 3. 解析Excel文件并处理数据
            $parseResult = $this->excelService->advancedParseExcelFile($filePath);
            if (!$parseResult['code']) {
                $this->completeImportWithError($importRecordId, $parseResult['msg']);
                return fail($parseResult['msg']);
            }

            $this->logService->logProcessStep($importRecordId, 'Excel解析', 'Excel文件解析完成', [
                'sheets_count' => count($parseResult['data']['sheets']),
                'total_rows' => $parseResult['data']['processing_summary']
            ]);

            // 4. 处理数据并插入数据库
            $processResult = $this->processExcelData($siteId, $importRecordId, $parseResult['data']);

            // 5. 完成导入
            $this->completeImport($importRecordId, $processResult);

            return success('导入完成', [
                'import_record_id' => $importRecordId,
                'result' => $processResult
            ]);

        } catch (\Exception $e) {
            Log::error('价格导入失败：' . $e->getMessage());
            
            if (isset($importRecordId)) {
                $this->completeImportWithError($importRecordId, $e->getMessage());
            }
            
            return fail('导入失败：' . $e->getMessage());
        }
    }

    /**
     * 创建导入记录
     * @param int $siteId
     * @param string $fileName
     * @param string $filePath
     * @param int $operatorId
     * @return array
     */
    protected function createImportRecord(int $siteId, string $fileName, string $filePath, int $operatorId): array
    {
        $data = [
            'site_id' => $siteId,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'import_type' => 'excel',
            'operator_id' => $operatorId,
            'total_rows' => 0
        ];

        $this->importRecordModel->createImportRecord($data);
        
        return success('导入记录创建成功', ['id' => $this->importRecordModel->id]);
    }

    /**
     * 处理Excel解析后的数据
     * @param int $siteId
     * @param int $importRecordId
     * @param array $excelData
     * @return array
     */
    protected function processExcelData(int $siteId, int $importRecordId, array $excelData): array
    {
        $totalRows = 0;
        $successRows = 0;
        $failedRows = 0;
        $newDevices = 0;
        $updatedPrices = 0;
        $errors = [];

        try {
            Db::startTrans();

            foreach ($excelData['sheets'] as $sheetName => $sheetData) {
                $this->logService->logProcessStep($importRecordId, '处理工作表', "开始处理工作表: {$sheetName}", [
                    'sheet_rows' => count($sheetData['rows'])
                ]);

                foreach ($sheetData['rows'] as $rowData) {
                    $totalRows++;
                    
                    try {
                        // 解析行数据
                        $mappedData = $this->mapRowDataFromExcel($sheetData['header_info']['headers'], $rowData['data']);
                        
                        if (empty($mappedData) || empty($mappedData['model_name']) || empty($mappedData['prices'])) {
                            $failedRows++;
                            $errors[] = "第{$rowData['row_number']}行: 缺少必需字段";
                            continue;
                        }

                        // 查找或创建设备型号
                        $deviceModelId = $this->deviceService->findOrCreateDeviceModel($siteId, [
                            'model_name' => $mappedData['model_name'],
                            'network_model' => $mappedData['network_model'] ?? '',
                            'capacity' => $mappedData['capacity'] ?? ''
                        ]);

                        if (!$deviceModelId) {
                            $failedRows++;
                            $errors[] = "第{$rowData['row_number']}行: 设备型号创建失败";
                            continue;
                        }

                        // 处理价格数据
                        $priceResult = $this->processPrice($siteId, $importRecordId, $deviceModelId, $mappedData['prices']);
                        
                        if ($priceResult['code']) {
                            $successRows++;
                            if (strpos($priceResult['msg'], '创建') !== false) {
                                $newDevices++;
                            } else {
                                $updatedPrices++;
                            }
                        } else {
                            $failedRows++;
                            $errors[] = "第{$rowData['row_number']}行: " . $priceResult['msg'];
                        }

                    } catch (\Exception $e) {
                        $failedRows++;
                        $error = "第{$rowData['row_number']}行处理失败: " . $e->getMessage();
                        $errors[] = $error;
                        Log::error($error, ['row_data' => $rowData]);
                    }
                }
            }

            Db::commit();

        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return [
            'total_sheets' => count($excelData['sheets']),
            'processed_sheets' => count($excelData['sheets']),
            'total_rows' => $totalRows,
            'success_rows' => $successRows,
            'failed_rows' => $failedRows,
            'new_devices' => $newDevices,
            'updated_prices' => $updatedPrices,
            'errors' => $errors
        ];
    }

    /**
     * 从Excel数据映射行数据
     * @param array $headers
     * @param array $rowData
     * @return array|null
     */
    protected function mapRowDataFromExcel(array $headers, array $rowData): ?array
    {
        $mapped = [];
        $priceFields = [];
        $priceColumns = ['高保充新', '充新', '靓机', '小花', '大花', '外爆', '内爆'];

        foreach ($headers as $col => $header) {
            $value = $rowData[$col] ?? '';
            
            switch (trim($header)) {
                case '型号':
                case '机型':
                case '设备型号':
                    $mapped['model_name'] = $value;
                    break;
                    
                case '网络型号':
                case '网络':
                    $mapped['network_model'] = $value;
                    break;
                    
                case '容量':
                case '存储':
                    $mapped['capacity'] = $value;
                    break;
                    
                case '备注':
                case 'remarks':
                    $mapped['remarks'] = $value;
                    break;
                    
                default:
                    if (in_array(trim($header), $priceColumns)) {
                        $price = $this->cleanPrice($value);
                        if ($price !== null) {
                            $priceFields[trim($header)] = $price;
                        }
                    }
                    break;
            }
        }

        if (empty($mapped['model_name'])) {
            return null;
        }

        $mapped['prices'] = json_encode($priceFields, JSON_UNESCAPED_UNICODE);
        return $mapped;
    }

    /**
     * 清理价格数据
     * @param string $price
     * @return float|null
     */
    protected function cleanPrice(string $price): ?float
    {
        $price = trim(str_replace(['￥', '元', ',', ' '], '', $price));
        
        if (empty($price) || $price === '/' || $price === '-') {
            return null;
        }
        
        if (is_numeric($price)) {
            return (float)$price;
        }
        
        return null;
    }

    /**
     * 处理价格数据
     * @param int $siteId
     * @param int $importRecordId
     * @param int $deviceModelId
     * @param string $priceJson
     * @return array
     */
    protected function processPrice(int $siteId, int $importRecordId, int $deviceModelId, string $priceJson): array
    {
        if (empty($priceJson)) {
            return fail('无有效价格数据');
        }

        try {
            $priceDate = date('Y-m-d');
            
            // 检查当日是否已有价格
            $existing = $this->priceModel->where([
                'site_id' => $siteId,
                'device_model_id' => $deviceModelId,
                'price_date' => $priceDate
            ])->find();

            $priceData = [
                'site_id' => $siteId,
                'device_model_id' => $deviceModelId,
                'import_batch' => 'import_' . date('YmdHis'),
                'price_data' => $priceJson,
                'price_date' => $priceDate,
                'is_current' => 1,
                'status' => 1,
                'import_record_id' => $importRecordId
            ];

            if ($existing) {
                // 更新现有价格
                $this->priceModel->where('id', $existing->id)->update([
                    'price_data' => $priceJson,
                    'import_record_id' => $importRecordId,
                    'update_at' => time()
                ]);
                $action = '更新价格';
            } else {
                // 创建新价格
                $this->priceModel->save($priceData);
                $action = '创建价格';
            }

            Log::info("{$action}成功: 设备型号ID {$deviceModelId}, 价格: " . $priceJson);
            
            return success($action . '成功');

        } catch (\Exception $e) {
            Log::error('价格处理失败：' . $e->getMessage());
            return fail('价格处理失败：' . $e->getMessage());
        }
    }

    /**
     * 完成导入
     * @param int $importRecordId
     * @param array $result
     * @return void
     */
    protected function completeImport(int $importRecordId, array $result): void
    {
        $status = 1; // 成功
        
        if ($result['failed_rows'] > 0) {
            if ($result['success_rows'] > 0) {
                $status = 3; // 部分成功
            } else {
                $status = 2; // 失败
            }
        }

        $summary = [
            'total_sheets' => $result['total_sheets'],
            'processed_sheets' => $result['processed_sheets'],
            'total_rows' => $result['total_rows'],
            'success_count' => $result['success_rows'],
            'failed_count' => $result['failed_rows'],
            'new_devices' => $result['new_devices'],
            'updated_prices' => $result['updated_prices']
        ];

        $errorDetails = [];
        if (!empty($result['errors'])) {
            $errorDetails = $result['errors'];
        }

        $this->importRecordModel->completeImport($importRecordId, $status, $summary, $errorDetails);
        
        // 更新总行数
        $this->importRecordModel->where('id', $importRecordId)->update([
            'total_rows' => $result['total_rows'],
            'success_count' => $result['success_rows'],
            'failed_count' => $result['failed_rows']
        ]);

        $this->logService->logProcessStep($importRecordId, '导入完成', '价格导入流程完成', [
            'final_result' => $summary
        ]);
    }

    /**
     * 导入失败完成
     * @param int $importRecordId
     * @param string $errorMessage
     * @return void
     */
    protected function completeImportWithError(int $importRecordId, string $errorMessage): void
    {
        $this->importRecordModel->completeImport($importRecordId, 2, [], ['error' => $errorMessage]);
        
        $this->logService->logError($importRecordId, '导入流程失败: ' . $errorMessage, [
            'error_code' => 'IMPORT_PROCESS_ERROR'
        ]);
    }

    /**
     * 获取导入进度
     * @param int $importRecordId
     * @return array
     */
    public function getImportProgress(int $importRecordId): array
    {
        try {
            $record = $this->importRecordModel->getImportRecordInfo($importRecordId);
            if (empty($record)) {
                return fail('导入记录不存在');
            }

            $logStats = $this->logService->getImportLogStatistics($importRecordId);
            
            return success('获取进度成功', [
                'record' => $record,
                'log_stats' => $logStats
            ]);

        } catch (\Exception $e) {
            return fail('获取进度失败：' . $e->getMessage());
        }
    }

    /**
     * 测试Excel文件支持
     * @return array
     */
    public function testExcelSupport(): array
    {
        $types = $this->excelService->getSupportedTypes();
        $maxSize = $this->excelService->getMaxFileSize();
        
        return success('Excel支持测试', [
            'supported_types' => $types,
            'max_file_size' => $maxSize,
            'max_size_mb' => round($maxSize / (1024 * 1024), 2)
        ]);
    }

    /**
     * 测试容量标准化
     * @return array
     */
    public function testCapacityStandardization(): array
    {
        $testCases = ['256', '256G', '256 GB', '1T', '8+256', '16 + 512'];
        $results = $this->deviceService->testCapacityStandardization($testCases);
        
        return success('容量标准化测试', ['results' => $results]);
    }
}