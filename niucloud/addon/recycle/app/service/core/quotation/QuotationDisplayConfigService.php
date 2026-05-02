<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\quotation;

use addon\recycle\app\dict\config\RecycleConfigKeyDict;
use app\service\core\sys\CoreConfigService;

/**
 * 报价 2.0 前台展示配置
 */
class QuotationDisplayConfigService
{
    public const MODE_ALL = 'all';
    public const MODE_CUSTOM = 'custom';

    private CoreConfigService $configService;

    public function __construct()
    {
        $this->configService = new CoreConfigService();
    }

    public function getConfig(int $siteId): array
    {
        $saved = $this->configService->getConfigValue($siteId, RecycleConfigKeyDict::QUOTATION_DISPLAY);
        return $this->sanitizeConfig(is_array($saved) ? $saved : []);
    }

    public function setConfig(int $siteId, array $data): bool
    {
        return (bool)$this->configService->setConfig(
            $siteId,
            RecycleConfigKeyDict::QUOTATION_DISPLAY,
            $this->sanitizeConfig($data)
        );
    }

    public function isDatasetVisible(int $siteId, int $datasetId): bool
    {
        $config = $this->getConfig($siteId);
        if ((int)$config['enabled'] !== 1) {
            return false;
        }

        if ($config['mode'] === self::MODE_ALL) {
            return true;
        }

        return in_array($datasetId, $config['dataset_ids'], true);
    }

    public function sanitizeConfig(array $config): array
    {
        $mode = (string)($config['mode'] ?? self::MODE_ALL);
        if (!in_array($mode, [self::MODE_ALL, self::MODE_CUSTOM], true)) {
            $mode = self::MODE_ALL;
        }

        return [
            'enabled' => (int)(bool)($config['enabled'] ?? 1),
            'mode' => $mode,
            'dataset_ids' => $this->normalizeIdList($config['dataset_ids'] ?? []),
        ];
    }

    private function normalizeIdList($value): array
    {
        if (is_string($value)) {
            $value = array_filter(explode(',', $value), static fn($item) => trim((string)$item) !== '');
        }
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            $id = (int)$item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
