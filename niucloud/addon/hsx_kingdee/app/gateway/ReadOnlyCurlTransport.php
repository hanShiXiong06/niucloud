<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\gateway;

final class ReadOnlyCurlTransport implements Transport
{
    public const QUERY = SecureCurlTransport::QUERY;

    public function __construct(private EndpointPolicy $endpoint) {}

    public function post(string $service, array $payload, array $headers): array
    {
        if ($service !== self::QUERY) throw new ApiFailure('write_disabled', '当前连接只允许单据查询，禁止保存、审核、删除或其他操作');
        return (new SecureCurlTransport($this->endpoint))->post($service, $payload, $headers);
    }

    public static function decodeResponse(int $status, string $body): array
    {
        return SecureCurlTransport::decodeResponse($status, $body);
    }
}
