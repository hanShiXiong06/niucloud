<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\api;

use addon\hsx_recycle\app\service\core\express_query\ExpressQueryGatewayService;
use core\base\BaseApiService;

/** 用户端快递轨迹查询服务。 */
class ExpressQueryService extends BaseApiService
{
    public function getExpress(string $expressCode = '', string $mobile = ''): array
    {
        return (new ExpressQueryGatewayService())->query($this->site_id, $expressCode, $mobile);
    }
}
