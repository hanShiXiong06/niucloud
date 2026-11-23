<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\contract;

/**
 * 外部 ERP 适配器接口
 *
 * 所有外部 ERP（博远、金蝶、用友等）必须实现此接口
 */
interface ErpAdapterInterface
{
    /**
     * 适配器唯一标识
     * @return string 例如：boyuan, kingdee, yongyou
     */
    public function getAdapterCode(): string;

    /**
     * 适配器显示名称
     * @return string 例如：博远ERP, 金蝶云星空
     */
    public function getAdapterName(): string;

    /**
     * 适配器版本
     * @return string
     */
    public function getAdapterVersion(): string;

    /**
     * 支持的业务类型
     * @return array ['purchase', 'sale', 'payment', 'receipt', 'return']
     */
    public function getSupportedBusinessTypes(): array;

    /**
     * 推送采购入库
     * @param array $purchase ERP采购单数据
     * @param array $connection 连接配置
     * @return array ['success' => true, 'external_doc_no' => '外部单号', 'external_doc_id' => '外部ID', 'message' => '提示信息']
     */
    public function syncPurchase(array $purchase, array $connection): array;

    /**
     * 推送销售出库
     * @param array $sale ERP销售单数据
     * @param array $connection 连接配置
     * @return array
     */
    public function syncSale(array $sale, array $connection): array;

    /**
     * 推送付款
     * @param array $payment ERP付款数据
     * @param array $connection 连接配置
     * @return array
     */
    public function syncPayment(array $payment, array $connection): array;

    /**
     * 推送收款
     * @param array $receipt ERP收款数据
     * @param array $connection 连接配置
     * @return array
     */
    public function syncReceipt(array $receipt, array $connection): array;

    /**
     * 推送退货（采购退货/销售退货）
     * @param array $return 退货数据
     * @param array $connection 连接配置
     * @return array
     */
    public function syncReturn(array $return, array $connection): array;

    /**
     * 查询原单状态（用于失败后核验）
     * @param string $operationId 操作ID
     * @param array $connection 连接配置
     * @return array ['status' => 'success/failed/processing', 'external_doc_no' => '...', 'message' => '...']
     */
    public function queryOperation(string $operationId, array $connection): array;

    /**
     * 测试连接
     * @param array $connection 连接配置
     * @return array ['success' => true/false, 'message' => '测试结果']
     */
    public function testConnection(array $connection): array;

    /**
     * 获取配置表单字段定义（用于后台配置页面）
     * @return array [['name' => 'api_url', 'label' => 'API地址', 'type' => 'text', 'required' => true], ...]
     */
    public function getConfigFields(): array;

    /**
     * 获取映射需求（需要映射哪些主档）
     * @return array ['product' => true, 'warehouse' => true, 'party' => true, 'staff' => true, ...]
     */
    public function getMappingRequirements(): array;

    /**
     * 验证映射完整性
     * @param array $data 业务数据
     * @param int $connectionId 连接ID
     * @return array ['valid' => true/false, 'missing' => [缺失的映射]]
     */
    public function validateMappings(array $data, int $connectionId): array;
}
