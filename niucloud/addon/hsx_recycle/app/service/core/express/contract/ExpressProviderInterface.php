<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express\contract;

/**
 * 快递下单能力契约。
 *
 * 业务层只依赖本接口，供应商自己的 key、header、签名和响应格式
 * 必须留在各自 Provider 内部，禁止继续向订单服务泄漏。
 */
interface ExpressProviderInterface
{
    public function key(): string;

    public function name(): string;

    public function healthCheck(int $siteId): bool;

    /** @return array<int, array<string, mixed>> */
    public function products(int $siteId): array;

    /** @return array<int|string, mixed> */
    public function quote(int $siteId, array $request): array;

    /** @return array<string, mixed> */
    public function create(int $siteId, array $request): array;

    /** @return array<string, mixed> */
    public function cancel(int $siteId, array $request): array;

    /** @return array<string, mixed> */
    public function modify(int $siteId, array $request): array;

    /** @return array<string, mixed> */
    public function detail(int $siteId, array $request): array;

    /** @return array<string, mixed> */
    public function waybill(int $siteId, array $request): array;

    /** @return array<string, mixed> */
    public function account(int $siteId): array;
}
