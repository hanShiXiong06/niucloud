<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\admin\quotation_v2;

use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationDataset;
use addon\recycle_daheng_quote\app\service\core\quotation\QuotationDisplayConfigService as CoreQuotationDisplayConfigService;
use core\base\BaseAdminService;

/**
 * 报价 2.0 前台展示配置
 */
class DisplayConfigService extends BaseAdminService
{
    private CoreQuotationDisplayConfigService $coreService;

    public function __construct()
    {
        parent::__construct();
        $this->coreService = new CoreQuotationDisplayConfigService();
    }

    public function getConfig(): array
    {
        $config = $this->coreService->getConfig($this->site_id);
        $selectedIds = array_flip(array_map('intval', $config['dataset_ids'] ?? []));

        $datasets = (new QuotationDataset())->where([
            ['site_id', '=', $this->site_id],
        ])->field('id,quotation_id,price_name,dataset_name,sort,status,last_sync_at,last_sync_status')
            ->order('sort asc,id desc')
            ->select()
            ->toArray();

        foreach ($datasets as &$item) {
            $item['dataset_id'] = (int)$item['id'];
            $item['title'] = trim((string)($item['dataset_name'] ?? '')) ?: (string)($item['price_name'] ?? '');
            $item['display_enabled'] = $config['mode'] === CoreQuotationDisplayConfigService::MODE_ALL
                ? 1
                : (int)isset($selectedIds[(int)$item['id']]);
        }
        unset($item);

        return [
            'config' => $config,
            'datasets' => $datasets,
        ];
    }

    public function setConfig(array $data): bool
    {
        return $this->coreService->setConfig($this->site_id, $data);
    }
}
