<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\service\core\provider\contract;

/**
 * 第三方查机服务商协议。
 *
 * 适配器只负责第三方协议，不处理订单、收费、退款和业务日志。
 */
interface PhoneQueryProviderInterface
{
    public function supports(string $provider): bool;

    /**
     * @return array{
     *   success:bool,
     *   provider:string,
     *   third_code:string,
     *   message:string,
     *   data:array,
     *   source:mixed,
     *   cost:float,
     *   balance:mixed,
     *   request_method:string,
     *   request_url:string,
     *   request_params:array,
     *   response:array,
     *   duration_ms:int
     * }
     */
    public function query(array $channel, array $mapping, string $queryCode): array;
}
