<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationRequest;
use addon\recycle\app\model\quotation\RecycleQuotationConfig;
use addon\recycle\app\dict\quotation\QuotationDict;
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

    public function __construct()
    {
        parent::__construct();
        $this->requestModel = new RecycleQuotationRequest();
        $this->configModel = new RecycleQuotationConfig();
    }

    /**
     * 发送请求到外部接口
     * @param int $configId 报价单配置ID
     * @return array
     */
    public function sendRequest(int $configId): array
    {
        // 获取配置信息
        $configService = new QuotationConfigService();
        $config = $configService->getInfo($configId);
        
        if ($config['is_enable'] != QuotationDict::STATUS_ENABLED) {
            throw new CommonException('报价单配置未启用');
        }

        // 获取Token（直接使用配置中的值，明文存储）
        $configInfo = $configService->getInfo($configId);
        $tokens = [
            'authorization_token' => $configInfo['authorization_token'] ?? '',
            'open_id' => $configInfo['open_id'] ?? '',
        ];
        
        // 验证Token是否为空
        if (empty($tokens['authorization_token'])) {
            throw new CommonException('Authorization Token为空，请检查配置');
        }
        if (empty($tokens['open_id'])) {
            throw new CommonException('OpenId为空，请检查配置');
        }
        
    
        /*
     'https://dhmall.chaoniu.top/api/v1/quotation/detail?
     // quotation_id=114&
     // price_name=%E9%9D%93%E6%9C%BA%2F%E5%B0%8F%E8%8A%B1&
     // default_price_value=200&
     // default_percentage_value=3&
     // price_adjustment_type=2&
     // price_adjustment_value=-200&
     // quotation_background_color=linear-gradient%28%2090deg%2C%20%23000000%200%25%2C%20%23666666%20100%25%29&
     // quotation_text_color=%23ffffff' \*/ 


        // 创建请求记录
        $requestData = [

            'quotation_id' => $config['quotation_id'],
            'price_name' => $config['price_name'],
            'default_price_value'=>200,
            'default_percentage_value'=>3,
            'price_adjustment_type'=>2,
            'price_adjustment_value'=>200,
            'quotation_background_color'=>'linear-gradient( 90deg, #000000 0%, #666666 100%)',
            'quotation_text_color'=>'#ffffff',
            // 'request_status' => QuotationDict::REQUEST_STATUS_PENDING,
            // 'request_time' => time(),69除
        ];

        Db::startTrans();
        try {
            $requestRecord = $this->requestModel->create($requestData);
            $requestId = $requestRecord->id;

            // 构建请求URL（按照curl命令的原始参数）
            $baseUrl = 'https://dhmall.chaoniu.top/api/v1/quotation/detail';
            
            // 构建URL参数 - 所有必传参数
            // 1. quotation_id: 报价单ID（114或115），从配置表中获取
            $quotationId = isset($config['quotation_id']) && $config['quotation_id'] !== '' && $config['quotation_id'] !== null
                ? intval($config['quotation_id']) : 0;
            
            if ($quotationId == 0) {
                throw new CommonException('报价单ID不能为空，请在配置中设置quotation_id（114或115）');
            }
            
            // 2. price_name: 价格名称，从配置中获取
            $priceName = isset($config['price_name']) && $config['price_name'] !== '' && $config['price_name'] !== null
                ? $config['price_name'] : '';
            
            if (empty($priceName)) {
                throw new CommonException('价格名称不能为空，请在配置中设置price_name');
            }
            
            // 3. default_price_value: 默认价格值，从配置中获取，默认200
            $defaultPriceValue = isset($config['default_price_value']) && $config['default_price_value'] !== '' && $config['default_price_value'] !== null
                ? intval($config['default_price_value']) : 200;
            
            // 4. default_percentage_value: 默认百分比值，从配置中获取，默认3
            $defaultPercentageValue = isset($config['default_percentage_value']) && $config['default_percentage_value'] !== '' && $config['default_percentage_value'] !== null
                ? intval($config['default_percentage_value']) : 3;
            
            // 5. price_adjustment_type: 价格调整类型，从配置中获取，默认2
            // 确保price_adjustment_type必须是1或2，不能是0
            $priceAdjustmentTypeRaw = $config['price_adjustment_type'] ?? null;
            $priceAdjustmentTypeInt = intval($priceAdjustmentTypeRaw);
            $priceAdjustmentType = ($priceAdjustmentTypeInt == 1 || $priceAdjustmentTypeInt == 2) ? $priceAdjustmentTypeInt : 2;
            
            // 6. price_adjustment_value: 价格调整值，从配置中获取，默认-200
            $priceAdjustmentValue = isset($config['price_adjustment_value']) && $config['price_adjustment_value'] !== '' && $config['price_adjustment_value'] !== null
                ? intval($config['price_adjustment_value']) : -200;
            
            // 根据price_adjustment_type确保必填字段有值
            // 接口要求：无论price_adjustment_type是什么，两个参数都必须不为0
            // 当price_adjustment_type=1时，default_percentage_value必须不为0
            // 当price_adjustment_type=2时，default_price_value必须不为0
            // 为了保险起见，确保两个参数都不为0
            if ($priceAdjustmentType == 1) {
                // 比例类型，确保default_percentage_value不为0
                if ($defaultPercentageValue == 0) {
                    $defaultPercentageValue = 3;
                }
                // 同时确保default_price_value也不为0
                if ($defaultPriceValue == 0) {
                    $defaultPriceValue = 200;
                }
            } elseif ($priceAdjustmentType == 2) {
                // 金额类型，确保default_price_value不为0
                if ($defaultPriceValue == 0) {
                    $defaultPriceValue = 200;
                }
                // 同时确保default_percentage_value也不为0
                if ($defaultPercentageValue == 0) {
                    $defaultPercentageValue = 3;
                }
            } else {
                // 其他类型，确保两个参数都不为0
                if ($defaultPriceValue == 0) {
                    $defaultPriceValue = 200;
                }
                if ($defaultPercentageValue == 0) {
                    $defaultPercentageValue = 3;
                }
            }
            
            // 7. quotation_background_color: 报价单背景颜色，从配置中获取，默认渐变
            $quotationBackgroundColor = !empty($config['quotation_background_color']) 
                ? $config['quotation_background_color'] 
                : 'linear-gradient( 90deg, #000000 0%, #666666 100%)';
            
            // 8. quotation_text_color: 报价单文字颜色，从配置中获取，默认白色
            $quotationTextColor = !empty($config['quotation_text_color']) 
                ? $config['quotation_text_color'] 
                : '#ffffff';
            
            // 构建所有必传参数
            $params = [
                'quotation_id' => $quotationId,
                'price_name' => $priceName,
                'default_price_value' => $defaultPriceValue,
                'default_percentage_value' => $defaultPercentageValue,
                'price_adjustment_type' => $priceAdjustmentType,
                'price_adjustment_value' => $priceAdjustmentValue,
                'quotation_background_color' => $quotationBackgroundColor,
                'quotation_text_color' => $quotationTextColor,
            ];
            
            // 使用http_build_query会自动进行URL编码
            $requestUrl = $baseUrl . '?' . http_build_query($params);
            
          

            // 构建请求头
            $headers = [
                'Version' => '2.2.3',
                'AppId' => 'wxeff5f3c92ec08aff',
                'Platform' => '2',
                'Authorization' => $tokens['authorization_token'],
                'OpenId' => $tokens['open_id'],
                'content-type' => 'application/json',
                'Accept-Encoding' => 'gzip,compress,br,deflate',
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.64(0x18004030) NetType/4G Language/zh_CN',
                'Referer' => 'https://servicewechat.com/wxeff5f3c92ec08aff/123/page-frame.html',
            ];

            // 更新请求记录
            $requestRecord->save([
                'request_url' => $requestUrl,
                'request_headers' => json_encode($headers, JSON_UNESCAPED_UNICODE),
            ]);

            // 发送HTTP请求（使用cURL）
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $requestUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders($headers));
            
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
            
          
            // 更新请求记录
            if ($responseCode == 200 && isset($responseData['code']) && $responseData['code'] == 200) {
                $requestRecord->save([
                    'request_status' => QuotationDict::REQUEST_STATUS_SUCCESS,
                    'response_code' => $responseCode,
                    'response_data' => json_encode($responseData, JSON_UNESCAPED_UNICODE),
                ]);

                // 解析并存储报价数据
                $dataService = new QuotationDataService();
                $dataService->parseAndSaveData($requestId, $responseData['data']);

                Db::commit();

              

                return [
                    'request_id' => $requestId,
                    'status' => QuotationDict::REQUEST_STATUS_SUCCESS,
                    'message' => '请求成功',
                ];
            } else {
                $errorMessage = $responseData['msg'] ?? '请求失败';
                $requestRecord->save([
                    'request_status' => QuotationDict::REQUEST_STATUS_FAILED,
                    'response_code' => $responseCode,
                    'response_data' => json_encode($responseData, JSON_UNESCAPED_UNICODE),
                    'error_message' => $errorMessage,
                ]);

                Db::commit();

                Log::error('报价请求失败', [
                    'request_id' => $requestId,
                    'error' => $errorMessage,
                    'response' => $responseData,
                ]);

                throw new CommonException('请求失败：' . $errorMessage);
            }

        } catch (\Exception $e) {
            Db::rollback();
            
            // 更新请求记录为失败状态
            if (isset($requestId)) {
                $this->requestModel->where('id', $requestId)->update([
                    'request_status' => QuotationDict::REQUEST_STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
            }

            Log::error('报价请求异常', [
                'config_id' => $configId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CommonException('请求失败：' . $e->getMessage());
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
}

