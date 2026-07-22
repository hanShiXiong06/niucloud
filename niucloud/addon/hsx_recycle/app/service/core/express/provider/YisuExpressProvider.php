<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express\provider;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\express\contract\ExpressProviderInterface;
use addon\hsx_recycle\app\service\core\express\ExpressProductCatalogService;
use addon\hsx_recycle\app\service\core\third_party\CoreThirdPartyService;
use core\exception\CommonException;

/**
 * 亿速快递适配器。
 *
 * 第一阶段继续复用已在线运行的 ProviderYisu 和配置、日志实现，
 * 仅在本层完成协议收口，避免一次重写生产链路。
 */
class YisuExpressProvider implements ExpressProviderInterface
{
    private $thirdPartyService;

    public function __construct()
    {
        $this->thirdPartyService = new CoreThirdPartyService();
    }

    public function key(): string
    {
        return ThirdPartyDict::PROVIDER_YISU;
    }

    public function name(): string
    {
        return '亿速物流';
    }

    public function healthCheck(int $siteId): bool
    {
        $result = $this->thirdPartyService->testConnection($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER);
        return !empty($result['success']);
    }

    public function products(int $siteId): array
    {
        $products = (new ExpressProductCatalogService())->getEnabledProducts($siteId, $this->key());
        return array_values(array_map(function (array $product): array {
            return [
                'provider' => $this->key(),
                'provider_name' => $this->name(),
                'product_code' => (string)($product['product_code'] ?? ''),
                'product_name' => (string)($product['product_name'] ?? ''),
                'enabled' => 1,
                'raw' => $product,
            ];
        }, $products));
    }

    public function quote(int $siteId, array $request): array
    {
        return $this->call($siteId, 'quote', $request);
    }

    public function create(int $siteId, array $request): array
    {
        return $this->call($siteId, 'create', $request);
    }

    public function cancel(int $siteId, array $request): array
    {
        return $this->call($siteId, 'cancel', $request);
    }

    public function modify(int $siteId, array $request): array
    {
        return $this->call($siteId, 'modify', $request);
    }

    public function detail(int $siteId, array $request): array
    {
        return $this->call($siteId, 'detail', $request);
    }

    public function waybill(int $siteId, array $request): array
    {
        return $this->call($siteId, 'waybillPdf', $request);
    }

    public function account(int $siteId): array
    {
        return $this->call($siteId, 'fund', []);
    }

    private function call(int $siteId, string $operation, array $request): array
    {
        $result = $this->thirdPartyService->call(
            ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER,
            $operation,
            $request,
            $siteId
        );

        if (empty($result['success'])) {
            throw new CommonException((string)($result['message'] ?? $this->name() . '调用失败'));
        }

        $providerResult = is_array($result['data'] ?? null) ? $result['data'] : [];
        if (array_key_exists('success', $providerResult) && empty($providerResult['success'])) {
            throw new CommonException((string)($providerResult['message'] ?? $this->name() . '调用失败'));
        }

        return is_array($providerResult['data'] ?? null) ? $providerResult['data'] : [];
    }
}
