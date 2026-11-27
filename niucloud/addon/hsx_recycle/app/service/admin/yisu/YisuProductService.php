<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\yisu;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\express\ExpressProductCatalogService;
use core\base\BaseAdminService;

/**
 * 快递产品配置后台兼容入口。
 *
 * 保留原有类名和 API，内部统一走 sys_config 产品目录。
 */
class YisuProductService extends BaseAdminService
{
    private ExpressProductCatalogService $catalogService;

    public function __construct()
    {
        parent::__construct();
        $this->catalogService = new ExpressProductCatalogService();
    }

    public function getList(): array
    {
        return $this->catalogService->getProducts((int)$this->site_id, ThirdPartyDict::PROVIDER_YISU);
    }

    public function getView(string $provider = ThirdPartyDict::PROVIDER_YISU): array
    {
        return $this->catalogService->getView((int)$this->site_id, $provider);
    }

    public function batchUpdate(array $products, string $provider = ThirdPartyDict::PROVIDER_YISU): bool
    {
        return $this->catalogService->saveProducts((int)$this->site_id, $provider, $products);
    }

    public function modifyStatus(string $productCode, int $status, string $provider = ThirdPartyDict::PROVIDER_YISU): bool
    {
        return $this->catalogService->modifyStatus(
            (int)$this->site_id,
            $provider,
            $productCode,
            $status
        );
    }

    public function getEnabledProducts(string $provider = ThirdPartyDict::PROVIDER_YISU): array
    {
        return $this->catalogService->getEnabledProducts((int)$this->site_id, $provider);
    }
}
