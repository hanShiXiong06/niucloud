<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\admin\quotation;

use addon\recycle_daheng_quote\app\service\core\quotation\QuotationCrawlerConfigService as CoreQuotationCrawlerConfigService;
use core\base\BaseAdminService;

/**
 * 管理端报价爬虫配置
 */
class QuotationCrawlerConfigService extends BaseAdminService
{
    private $coreService;

    public function __construct()
    {
        parent::__construct();
        $this->coreService = new CoreQuotationCrawlerConfigService();
    }

    public function getConfig(): array
    {
        return $this->coreService->getConfig($this->site_id, false);
    }

    public function setConfig(array $data): bool
    {
        return $this->coreService->setConfig($this->site_id, $data);
    }

    public function saveConfig(array $data): array
    {
        return $this->coreService->saveConfig($this->site_id, $data, false);
    }

    public function getDefaultConfig(): array
    {
        return $this->coreService->getDefaultConfig();
    }
}
