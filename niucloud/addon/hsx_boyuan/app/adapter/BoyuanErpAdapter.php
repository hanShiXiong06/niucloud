<?php
declare(strict_types=1);

namespace addon\hsx_boyuan\app\adapter;

use addon\hsx_erp_integration\app\contract\ErpAdapterInterface;
use addon\hsx_erp_integration\app\service\MappingService;
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_erp_integration\app\contract\{ManualDocumentAdapterInterface,LocalOriginalReceiptInterface};
use addon\hsx_erp_integration\app\contract\DisjointManualScopeInterface;
use addon\hsx_boyuan\app\service\BridgeOriginalDocumentService;
use addon\hsx_boyuan\app\service\ConfigService;

/**
 * 博远ERP适配器
 *
 * 实现统一的ERP集成接口，将hsx_erp业务数据推送到博远ERP
 */
class BoyuanErpAdapter implements ErpAdapterInterface, ManualDocumentAdapterInterface, LocalOriginalReceiptInterface, DisjointManualScopeInterface
{
    private MappingService $mappingService;

    public function __construct()
    {
        $this->mappingService = new MappingService();
    }

    public function getAdapterCode(): string
    {
        return 'boyuan';
    }

    public function getAdapterName(): string
    {
        return '博远ERP';
    }

    public function getAdapterVersion(): string
    {
        return '2.0.0';
    }

    public function getSupportedBusinessTypes(): array
    {
        return ['purchase', 'sale', 'payment', 'receipt']; // receipt is exclusively an explicit UAT original-document command.
    }

    /**
     * 推送采购入库
     */
    public function syncPurchase(array $purchase, array $connection): array
    {
        return $this->originalRequired();
    }

    /**
     * 推送销售出库
     */
    public function syncSale(array $sale, array $connection): array
    {
        return $this->originalRequired();
    }

    /**
     * 推送付款
     */
    public function syncPayment(array $payment, array $connection): array
    {
        return $this->originalRequired();
    }

    private function originalRequired(): array
    {
        return ['success'=>false, 'error_code'=>'ORIGINAL_DOCUMENT_REQUIRED', 'message'=>'请从原单工作台关联博远核对任务；不可凭汇总数据创建采购、批发出库或登记付款。'];
    }

    public function originalDocument(array $event, array $context, bool $readOnly): array
    {
        return (new BridgeOriginalDocumentService())->handle($event, $context, $readOnly);
    }

    public function localOriginalReceipt(array $event,array $context): array
    {
        return (new BridgeOriginalDocumentService())->handle($event,$context,true,true);
    }

    public function manualBusinessTypes(string $mode): array
    {
        return $mode==='test' ? ['purchase','payment','sale','receipt'] : ['purchase','payment'];
    }

    public function originalProfile(int $siteId): array
    {
        return (new BridgeOriginalDocumentService())->publicProfile($siteId);
    }

    public function admitsManualFact(int $site,array $connectionConfig,array $event): bool
    {
        return (new BridgeOriginalDocumentService())->admitsManualFact($site,$connectionConfig,$event);
    }

    public function manualDisjointScopes(int $site): array
    {
        return (new BridgeOriginalDocumentService())->manualDisjointScopes($site);
    }

    /**
     * 推送收款
     */
    public function syncReceipt(array $receipt, array $connection): array
    {
        // Generic/automatic payloads have neither the original voucher nor a confirmed, single-use authorization.
        return [
            'success' => false,
            'message' => '收款只能在测试账套从已确认的同一张销售收款原凭证人工登记，不接受通用或自动推送',
            'error_code' => 'ORIGINAL_DOCUMENT_REQUIRED',
        ];
    }

    /**
     * 推送退货
     */
    public function syncReturn(array $return, array $connection): array
    {
        // 博远当前版本暂不支持退货推送
        return [
            'success' => false,
            'message' => '博远当前版本暂不支持退货推送',
            'error_code' => 'NOT_SUPPORTED',
        ];
    }

    /**
     * 查询原单状态
     */
    public function queryOperation(string $operationId, array $connection): array
    {
        // 博远暂不支持通用查询接口
        return [
            'status' => 'unknown',
            'message' => '博远暂不支持通用查询接口',
        ];
    }

    /**
     * 测试连接
     */
    public function testConnection(array $connection): array
    {
        try {
            $config = $this->getConfig($connection);
            $client = $this->createClient($config);

            // verify() 只查公司资料并比对启用中的目标账套，不调用业务单接口。
            $result = $client->verify();
            $ok = ($result['code'] ?? '') === 'OK' && (string)($result['data']['account_id'] ?? '') === (string)$config['account_code'];
            return ['success' => $ok, 'message' => $ok ? '账套身份核验通过' : '未核实目标账套'];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => '连接异常: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 获取配置表单字段
     */
    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'api_url',
                'label' => 'API地址',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请填写所选环境的 HTTPS API 地址',
                'help' => '请按博远提供的地址填写，保留 /api；网关路径在下一项填写。',
            ],
            [
                'name' => 'gateway_path',
                'label' => '网关路径',
                'type' => 'text',
                'required' => true,
                'default' => '/gateway.do',
                'placeholder' => '/gateway.do',
            ],
            [
                'name' => 'app_id',
                'label' => 'AppID',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'app_secret',
                'label' => 'AppSecret',
                'type' => 'password',
                'required' => true,
            ],
            [
                'name' => 'account_code',
                'label' => '账套代码',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'big_gsdm',
                'help' => '博远账套代码（big_gsdm）',
            ],
            [
                'name' => 'mode',
                'label' => '环境模式',
                'type' => 'select',
                'required' => true,
                'default' => 'test',
                'options' => [
                    ['value' => 'test', 'label' => '测试环境'],
                    ['value' => 'production', 'label' => '生产环境'],
                ],
            ],
        ];
    }

    /**
     * 获取映射需求
     */
    public function getMappingRequirements(): array
    {
        return [
            'product' => true,
            'warehouse' => true,
            'party' => true,
            'staff' => true,
        ];
    }

    /**
     * 验证映射完整性
     */
    public function validateMappings(array $data, int $connectionId): array
    {
        return $this->mappingService->validateBusinessMappings($connectionId, $data);
    }

    /**
     * 获取博远配置
     */
    private function getConfig(array $connection): array
    {
        return [
            'endpoint' => $connection['api_url'],
            'gateway_path' => $connection['gateway_path'] ?? '/api/gateway.do',
            'app_id' => $connection['app_id'],
            'app_secret' => $connection['app_secret'],
            'account_code' => $connection['account_code'],
            'mode' => $connection['mode'] ?? 'test',
        ];
    }

    /**
     * 创建博远客户端
     */
    private function createClient(array $config): OpenApi
    {
        $profile = [
            'endpoint' => $config['endpoint'],
            'gateway_path' => $config['gateway_path'],
            'app_id' => $config['app_id'],
            'account_id' => $config['account_code'],
        ];

        return new OpenApi(
            $profile,
            $config['app_secret'],
            null,
            $config['mode'],
            null
        );
    }
}
