<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\model\ErpIntegrationMapping;

/**
 * 映射管理服务
 */
class MappingService
{
    /**
     * 获取外部编码
     * @param int $connectionId 连接ID
     * @param string $resourceType 资源类型：product/warehouse/party/staff
     * @param int $localId 本地资源ID
     * @return string|null
     */
    public function getExternalCode(int $connectionId, string $resourceType, int $localId): ?string
    {
        $mapping = ErpIntegrationMapping::where('connection_id', $connectionId)
            ->where('resource_type', $resourceType)
            ->where('local_id', $localId)
            ->findOrEmpty();

        return $mapping->isEmpty() ? null : $mapping->external_code;
    }

    /**
     * 获取外部ID
     * @param int $connectionId
     * @param string $resourceType
     * @param int $localId
     * @return string|null
     */
    public function getExternalId(int $connectionId, string $resourceType, int $localId): ?string
    {
        $mapping = ErpIntegrationMapping::where('connection_id', $connectionId)
            ->where('resource_type', $resourceType)
            ->where('local_id', $localId)
            ->findOrEmpty();

        return $mapping->isEmpty() ? null : $mapping->external_id;
    }

    /**
     * 批量获取映射
     * @param int $connectionId
     * @param string $resourceType
     * @param array $localIds
     * @return array [local_id => external_code]
     */
    public function batchGetExternalCodes(int $connectionId, string $resourceType, array $localIds): array
    {
        $mappings = ErpIntegrationMapping::where('connection_id', $connectionId)
            ->where('resource_type', $resourceType)
            ->whereIn('local_id', $localIds)
            ->select()
            ->toArray();

        $result = [];
        foreach ($mappings as $mapping) {
            $result[$mapping['local_id']] = $mapping['external_code'];
        }

        return $result;
    }

    /**
     * 保存映射
     * @param int $siteId
     * @param int $connectionId
     * @param string $resourceType
     * @param int $localId
     * @param string $localCode
     * @param string $localName
     * @param string $externalCode
     * @param string|null $externalId
     * @param string|null $externalName
     * @param bool $autoMatched
     * @return bool
     */
    public function saveMapping(
        int $siteId,
        int $connectionId,
        string $resourceType,
        int $localId,
        string $localCode,
        string $localName,
        string $externalCode,
        ?string $externalId = null,
        ?string $externalName = null,
        bool $autoMatched = false
    ): bool {
        $mapping = ErpIntegrationMapping::where('site_id', $siteId)
            ->where('connection_id', $connectionId)
            ->where('resource_type', $resourceType)
            ->where('local_id', $localId)
            ->findOrEmpty();

        $data = [
            'site_id' => $siteId,
            'connection_id' => $connectionId,
            'resource_type' => $resourceType,
            'local_id' => $localId,
            'local_code' => $localCode,
            'local_name' => $localName,
            'external_code' => $externalCode,
            'external_id' => $externalId,
            'external_name' => $externalName,
            'auto_matched' => $autoMatched ? 1 : 0,
            'updated_at' => time(),
        ];

        if ($mapping->isEmpty()) {
            $data['created_at'] = time();
            ErpIntegrationMapping::create($data);
        } else {
            $mapping->save($data);
        }

        return true;
    }

    /**
     * 删除映射
     * @param int $connectionId
     * @param string $resourceType
     * @param int $localId
     * @return bool
     */
    public function deleteMapping(int $connectionId, string $resourceType, int $localId): bool
    {
        return ErpIntegrationMapping::where('connection_id', $connectionId)
            ->where('resource_type', $resourceType)
            ->where('local_id', $localId)
            ->delete() > 0;
    }

    /**
     * 获取映射统计
     * @param int $connectionId
     * @param string $resourceType
     * @return array ['total' => 总数, 'mapped' => 已映射数]
     */
    public function getMappingStats(int $connectionId, string $resourceType): array
    {
        $mapped = ErpIntegrationMapping::where('connection_id', $connectionId)
            ->where('resource_type', $resourceType)
            ->count();

        // 根据资源类型获取总数
        $total = $this->getResourceTotal($resourceType);

        return [
            'total' => $total,
            'mapped' => $mapped,
            'unmapped' => $total - $mapped,
            'percentage' => $total > 0 ? round($mapped / $total * 100, 2) : 0,
        ];
    }

    /**
     * 获取资源总数（从各自的表中统计）
     * @param string $resourceType
     * @return int
     */
    private function getResourceTotal(string $resourceType): int
    {
        try {
            switch ($resourceType) {
                case 'product':
                    return \think\facade\Db::name('erp_product')->count();
                case 'warehouse':
                    return \think\facade\Db::name('erp_warehouse')->count();
                case 'party':
                    return \think\facade\Db::name('erp_party')->count();
                case 'staff':
                    return \think\facade\Db::name('sys_admin')->count();
                default:
                    return 0;
            }
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 检查业务数据的映射完整性
     * @param int $connectionId
     * @param array $data 业务数据
     * @return array ['valid' => true/false, 'missing' => []]
     */
    public function validateBusinessMappings(int $connectionId, array $data): array
    {
        $missing = [];

        // 检查供应商/客户
        if (isset($data['party_id'])) {
            $code = $this->getExternalCode($connectionId, 'party', $data['party_id']);
            if (!$code) {
                $missing[] = [
                    'type' => 'party',
                    'id' => $data['party_id'],
                    'name' => $data['party_name'] ?? '',
                ];
            }
        }

        // 检查仓库
        if (isset($data['warehouse_id'])) {
            $code = $this->getExternalCode($connectionId, 'warehouse', $data['warehouse_id']);
            if (!$code) {
                $missing[] = [
                    'type' => 'warehouse',
                    'id' => $data['warehouse_id'],
                    'name' => $data['warehouse_name'] ?? '',
                ];
            }
        }

        // 检查明细中的商品
        if (isset($data['lines']) && is_array($data['lines'])) {
            foreach ($data['lines'] as $line) {
                if (isset($line['product_id'])) {
                    $code = $this->getExternalCode($connectionId, 'product', $line['product_id']);
                    if (!$code) {
                        $missing[] = [
                            'type' => 'product',
                            'id' => $line['product_id'],
                            'name' => $line['product_name'] ?? '',
                        ];
                    }
                }
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }
}
