<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationRequest;
use addon\recycle\app\model\quotation\RecycleQuotationConfig;
use addon\recycle\app\dict\quotation\QuotationDict;
use addon\recycle\app\dict\third_party\ThirdPartyDict;
use addon\recycle\app\model\third_party\ThirdPartyApiLog;
use addon\recycle\app\service\core\quotation\QuotationCrawlerConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 报价请求服务
 * Class QuotationRequestService
 * @package addon\recycle\app\service\admin\quotation
 */
class QuotationRequestService extends BaseAdminService
{
    /**
     * @var RecycleQuotationRequest
     */
    protected $requestModel;

    /**
     * @var RecycleQuotationConfig
     */
    protected $configModel;

    private $crawlerConfigService;

    public function __construct()
    {
        parent::__construct();
        $this->requestModel = new RecycleQuotationRequest();
        $this->configModel = new RecycleQuotationConfig();
        $this->crawlerConfigService = new QuotationCrawlerConfigService();
    }

    /**
     * 发送请求到外部接口
     * @param int $configId 报价单配置ID
     * @param int|null $siteId 指定站点ID，兼容任务调度
     * @return array
     */
    public function sendRequest(int $configId, ?int $siteId = null): array
    {
        // 设置数据库连接超时参数,防止长时间执行导致连接超时
        DatabaseHelper::setConnectionTimeout(600, 600); // 设置为10分钟

        // 检查数据库连接状态
        if (!DatabaseHelper::reconnectIfNeeded()) {
            throw new CommonException('数据库连接失败');
        }

        $config = $this->fetchConfig($configId, $siteId);
        $targetSiteId = $this->resolveSiteId($config, $siteId);
        $this->site_id = $targetSiteId;

        $crawlerConfig = $this->getCrawlerProviderConfig($targetSiteId);
        $quotationParams = $this->buildQuotationParams($config);
        $requestUrl = $this->buildRequestUrl($quotationParams, $crawlerConfig);
        $requestData = $this->buildRequestData($quotationParams, $targetSiteId);

        // 创建请求记录（缩小事务范围）
        $requestRecord = $this->requestModel->create($requestData);
        $requestId = $requestRecord->id;

        try {
            $headerMap = $this->defaultHeaderMap($crawlerConfig);
            $this->persistRequestMeta($requestRecord, $requestUrl, $headerMap);

            // HTTP请求在事务外执行，避免长时间锁表
            $requestStartTime = microtime(true);
            [$responseCode, $responseData] = $this->sendHttpRequest($requestUrl, $headerMap, (int)$crawlerConfig['timeout']);
            $duration = (int)round((microtime(true) - $requestStartTime) * 1000);

            // 处理响应时才开启事务
            $result = $this->handleResponse($requestRecord, $responseCode, $responseData);
            $this->logCrawlerCall($targetSiteId, $requestUrl, $quotationParams, $responseData, $duration, true);

            return $result;
        } catch (\Exception $e) {
            $duration = isset($requestStartTime) ? (int)round((microtime(true) - $requestStartTime) * 1000) : 0;
            $this->logCrawlerCall(
                $targetSiteId,
                $requestUrl ?? '',
                $quotationParams ?? [],
                $responseData ?? [],
                $duration,
                false,
                $e->getMessage()
            );

            // 更新请求记录为失败状态
            $this->requestModel->where('id', $requestId)->update([
                'request_status' => QuotationDict::REQUEST_STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('报价请求异常', [
                'config_id' => $configId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CommonException('请求失败：' . $e->getMessage());
        } finally {
            // 执行完成后显式关闭数据库连接,确保连接被正确释放
            DatabaseHelper::closeConnection();
        }
    }

    /**
     * 获取请求记录列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = []): array
    {
        $field = 'id,site_id,quotation_id,price_name,default_price_value,default_percentage_value,price_adjustment_type,price_adjustment_value,request_status,error_message,request_time,create_at,update_at';
        
        $search_model = $this->requestModel->where([['site_id', '=', $this->site_id]])
            ->withSearch(['quotation_id', 'price_name', 'request_status'], $where)
            ->field($field)
            ->order('request_time desc');
        
        $list = $this->pageQuery($search_model);
        
        return $list;
    }

    /**
     * 获取请求记录详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id): array
    {
        $info = $this->requestModel
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new CommonException('请求记录不存在');
        }

        return $info;
    }

    /**
     * 构建HTTP请求头数组
     * @param array $headers
     * @return array
     */
    private function buildHeaders(array $headers): array
    {
        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = $key . ': ' . $value;
        }
        return $headerArray;
    }

    private function fetchConfig(int $configId, ?int $siteId): array
    {
        $configService = new QuotationConfigService();
        $config = $configService->getInfo($configId, $siteId);

        if ($config['is_enable'] != QuotationDict::STATUS_ENABLED) {
            throw new CommonException('报价单配置未启用');
        }

        return $config;
    }

    private function resolveSiteId(array $config, ?int $siteId): int
    {
        return $siteId ?? intval($config['site_id'] ?? 0);
    }

    private function buildQuotationParams(array $config): array
    {
        $quotationId = isset($config['quotation_id']) && $config['quotation_id'] !== '' && $config['quotation_id'] !== null
            ? intval($config['quotation_id']) : 0;
        if ($quotationId == 0) {
            throw new CommonException('报价单ID不能为空，请在配置中设置quotation_id（114或115）');
        }

        $priceName = isset($config['price_name']) && $config['price_name'] !== '' && $config['price_name'] !== null
            ? $config['price_name'] : '';
        if (empty($priceName)) {
            throw new CommonException('价格名称不能为空，请在配置中设置price_name');
        }

        $defaultPriceValue = isset($config['default_price_value']) && $config['default_price_value'] !== '' && $config['default_price_value'] !== null
            ? intval($config['default_price_value']) : 200;

        $defaultPercentageValue = isset($config['default_percentage_value']) && $config['default_percentage_value'] !== '' && $config['default_percentage_value'] !== null
            ? intval($config['default_percentage_value']) : 3;

        $priceAdjustmentTypeRaw = $config['price_adjustment_type'] ?? null;
        $priceAdjustmentTypeInt = intval($priceAdjustmentTypeRaw);
        $priceAdjustmentType = ($priceAdjustmentTypeInt == 1 || $priceAdjustmentTypeInt == 2) ? $priceAdjustmentTypeInt : 2;

        $priceAdjustmentValue = isset($config['price_adjustment_value']) && $config['price_adjustment_value'] !== '' && $config['price_adjustment_value'] !== null
            ? intval($config['price_adjustment_value']) : -200;

        if ($priceAdjustmentType == 1) {
            $defaultPercentageValue = $defaultPercentageValue == 0 ? 3 : $defaultPercentageValue;
            $defaultPriceValue = $defaultPriceValue == 0 ? 200 : $defaultPriceValue;
        } elseif ($priceAdjustmentType == 2) {
            $defaultPriceValue = $defaultPriceValue == 0 ? 200 : $defaultPriceValue;
            $defaultPercentageValue = $defaultPercentageValue == 0 ? 3 : $defaultPercentageValue;
        } else {
            $defaultPriceValue = $defaultPriceValue == 0 ? 200 : $defaultPriceValue;
            $defaultPercentageValue = $defaultPercentageValue == 0 ? 3 : $defaultPercentageValue;
        }

        $quotationBackgroundColor = !empty($config['quotation_background_color'])
            ? $config['quotation_background_color']
            : 'linear-gradient( 90deg, #000000 0%, #666666 100%)';

        $quotationTextColor = !empty($config['quotation_text_color'])
            ? $config['quotation_text_color']
            : '#ffffff';

        return [
            'quotation_id' => $quotationId,
            'price_name' => $priceName,
            'default_price_value' => $defaultPriceValue,
            'default_percentage_value' => $defaultPercentageValue,
            'price_adjustment_type' => $priceAdjustmentType,
            'price_adjustment_value' => $priceAdjustmentValue,
            'quotation_background_color' => $quotationBackgroundColor,
            'quotation_text_color' => $quotationTextColor,
        ];
    }

    private function getCrawlerProviderConfig(int $siteId): array
    {
        $config = $this->crawlerConfigService->getProviderConfig($siteId);
        if (empty($config)) {
            throw new CommonException('报价爬虫配置未启用或配置不存在');
        }

        foreach (['base_url', 'detail_path', 'authorization_token', 'open_id'] as $field) {
            if (trim((string)($config[$field] ?? '')) === '') {
                throw new CommonException('报价爬虫配置不完整：' . $field);
            }
        }

        $config['timeout'] = max(1, (int)($config['timeout'] ?? 30));

        return $config;
    }

    private function buildRequestUrl(array $params, array $crawlerConfig): string
    {
        return rtrim((string)$crawlerConfig['base_url'], '/') . '/' . ltrim((string)$crawlerConfig['detail_path'], '/') . '?' . http_build_query($params);
    }

    private function buildRequestData(array $params, int $siteId): array
    {
        return [
            'site_id' => $siteId,
            'quotation_id' => $params['quotation_id'],
            'price_name' => $params['price_name'],
            'default_price_value' => $params['default_price_value'],
            'default_percentage_value' => $params['default_percentage_value'],
            'price_adjustment_type' => $params['price_adjustment_type'],
            'price_adjustment_value' => $params['price_adjustment_value'],
            'quotation_background_color' => $params['quotation_background_color'],
            'quotation_text_color' => $params['quotation_text_color'],
            'request_status' => QuotationDict::REQUEST_STATUS_PENDING,
            'request_time' => time(),
        ];
    }

    private function defaultHeaderMap(array $crawlerConfig): array
    {
        return [
            'Version' => (string)($crawlerConfig['version'] ?? '2.2.3'),
            'AppId' => (string)($crawlerConfig['app_id'] ?? 'wxeff5f3c92ec08aff'),
            'Platform' => (string)($crawlerConfig['platform'] ?? '2'),
            'Authorization' => (string)$crawlerConfig['authorization_token'],
            'OpenId' => (string)$crawlerConfig['open_id'],
            'content-type' => 'application/json',
            'Accept-Encoding' => (string)($crawlerConfig['accept_encoding'] ?? 'gzip,compress,br,deflate'),
            'User-Agent' => (string)($crawlerConfig['user_agent'] ?? ''),
            'Referer' => (string)($crawlerConfig['referer'] ?? ''),
        ];
    }

    private function persistRequestMeta(RecycleQuotationRequest $requestRecord, string $requestUrl, array $headerMap): void
    {
        $requestRecord->save([
            'request_url' => $requestUrl,
            'request_headers' => json_encode($headerMap, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function sendHttpRequest(string $url, array $headerMap, int $timeout): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders($headerMap));

        $responseBody = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new CommonException('请求失败：' . $curlError);
        }

        $responseData = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CommonException('响应数据解析失败：' . json_last_error_msg());
        }

        return [$responseCode, $responseData];
    }

    private function handleResponse(RecycleQuotationRequest $requestRecord, int $responseCode, array $responseData): array
    {
        if ($responseCode == 200 && isset($responseData['code']) && $responseData['code'] == 200) {
            // 先更新请求记录状态(事务外执行,避免长时间锁表)
            try {
                $requestRecord->save([
                    'request_status' => QuotationDict::REQUEST_STATUS_SUCCESS,
                    'response_code' => $responseCode,
                    'response_data' => json_encode($responseData, JSON_UNESCAPED_UNICODE),
                ]);

                // 数据解析和保存在独立的事务中处理,减少锁持有时间
                $dataService = (new QuotationDataService())->setSiteId($this->site_id);
                $stats = $dataService->parseAndSaveData($requestRecord->id, $responseData['data']);

                Log::info('报价数据导入成功', [
                    'request_id' => $requestRecord->id,
                    'stats' => $stats,
                ]);

                return [
                    'request_id' => $requestRecord->id,
                    'status' => QuotationDict::REQUEST_STATUS_SUCCESS,
                    'message' => '请求成功',
                    'stats' => $stats,
                ];
            } catch (\Exception $e) {
                // 如果数据保存失败,更新请求记录状态
                $requestRecord->save([
                    'request_status' => QuotationDict::REQUEST_STATUS_FAILED,
                    'error_message' => '数据保存失败: ' . $e->getMessage(),
                ]);

                Log::error('报价数据保存异常', [
                    'request_id' => $requestRecord->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        }

        $errorMessage = $responseData['msg'] ?? '请求失败';
        $requestRecord->save([
            'request_status' => QuotationDict::REQUEST_STATUS_FAILED,
            'response_code' => $responseCode,
            'response_data' => json_encode($responseData, JSON_UNESCAPED_UNICODE),
            'error_message' => $errorMessage,
        ]);

        Log::error('报价请求失败', [
            'request_id' => $requestRecord->id,
            'error' => $errorMessage,
            'response' => $responseData,
        ]);

        throw new CommonException('请求失败：' . $errorMessage);
    }

    private function logCrawlerCall(
        int $siteId,
        string $requestUrl,
        array $requestParams,
        array $responseData,
        int $duration,
        bool $success,
        string $errorMsg = ''
    ): void {
        ThirdPartyApiLog::log([
            'site_id' => $siteId,
            'service_type' => ThirdPartyDict::SERVICE_TYPE_QUOTATION_CRAWLER,
            'provider_name' => ThirdPartyDict::PROVIDER_CHAONIU_QUOTATION,
            'method' => 'quotation.detail',
            'request_params' => [
                'request_url' => $requestUrl,
                'params' => $requestParams,
            ],
            'response_data' => $responseData,
            'cost' => 0,
            'duration' => $duration,
            'status' => $success ? ThirdPartyDict::CALL_STATUS_SUCCESS : ThirdPartyDict::CALL_STATUS_FAILED,
            'error_msg' => $errorMsg,
        ]);
    }
}
