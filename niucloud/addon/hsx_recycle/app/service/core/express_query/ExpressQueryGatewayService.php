<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express_query;

use core\exception\CommonException;

class ExpressQueryGatewayService
{
    private ExpressQueryProviderRegistry $registry;

    public function __construct(?ExpressQueryProviderRegistry $registry = null)
    {
        $this->registry = $registry ?: new ExpressQueryProviderRegistry();
    }

    public function query(int $siteId, string $expressNo, string $mobile = '', string $providerKey = ''): array
    {
        $expressNo = trim($expressNo);
        if ($expressNo === '') {
            throw new CommonException('快递单号不能为空');
        }
        return $this->registry->resolve($siteId, trim($providerKey))->query($siteId, $expressNo, trim($mobile));
    }
}
