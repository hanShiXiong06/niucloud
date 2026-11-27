<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\contract\ErpAdapterInterface;

/**
 * 适配器注册中心
 *
 * 管理所有已注册的外部ERP适配器
 */
class AdapterRegistry
{
    /**
     * 已注册的适配器
     * @var array<string, ErpAdapterInterface>
     */
    private static array $adapters = [];

    /** 安装时的静态注册不会跨 PHP 请求保留。按已安装且本站可用的插件发现适配器。 */
    public static function forSite(int $siteId): self
    {
        $installed = array_keys((new \app\service\core\addon\CoreAddonService())->getInstallAddonList());
        // An outbox worker supplies a positive tenant from its frozen envelope, with no logged-in request.
        $allowed = $siteId === 0 && $siteId === (int)request()->defaultSiteId() ? $installed
            : array_intersect($installed, (new \app\service\core\site\CoreSiteService())->getAddonKeysBySiteId($siteId));
        return self::fromAddons($allowed);
    }

    private ?array $scoped = null;

    public static function fromAddons(array $keys): self
    {
        $registry = new self();
        $registry->scoped = [];
        foreach ($keys as $key) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', (string)$key)) continue;
            $namespace = 'addon\\' . $key . '\\';
            foreach (self::$adapters as $adapter) {
                if (str_starts_with(get_class($adapter), $namespace)) $registry->scoped[$adapter->getAdapterCode()] = $adapter;
            }
            foreach (glob(dirname(__DIR__, 3) . '/' . $key . '/app/adapter/*ErpAdapter.php') ?: [] as $file) {
                $class = $namespace . 'app\\adapter\\' . basename($file, '.php');
                if (is_subclass_of($class, ErpAdapterInterface::class) && (new \ReflectionClass($class))->isInstantiable()) {
                    $adapter = app($class);
                    $registry->scoped[$adapter->getAdapterCode()] = $adapter;
                }
            }
        }
        return $registry;
    }

    /**
     * 注册适配器
     * @param ErpAdapterInterface $adapter
     */
    public function register(ErpAdapterInterface $adapter): void
    {
        self::$adapters[$adapter->getAdapterCode()] = $adapter;
    }

    /**
     * 获取适配器
     * @param string $code 适配器代码
     * @return ErpAdapterInterface
     * @throws \Exception
     */
    public function getAdapter(string $code): ErpAdapterInterface
    {
        if (!$this->hasAdapter($code)) {
            throw new \Exception("适配器不存在：{$code}");
        }

        return $this->getAllAdapters()[$code];
    }

    /**
     * 获取所有已注册的适配器
     * @return array<string, ErpAdapterInterface>
     */
    public function getAllAdapters(): array
    {
        return $this->scoped ?? self::$adapters;
    }

    /**
     * 获取适配器列表（用于前端展示）
     * @return array
     */
    public function getAdapterList(): array
    {
        $list = [];
        foreach ($this->getAllAdapters() as $code => $adapter) {
            $fields = $adapter->getConfigFields();
            foreach ($fields as &$field) if (\addon\hsx_erp_integration\app\support\ConnectionFields::secret($field)) unset($field['default']);
            unset($field);
            $list[] = [
                'code' => $code,
                'name' => $adapter->getAdapterName(),
                'version' => $adapter->getAdapterVersion(),
                'supported_business_types' => $adapter->getSupportedBusinessTypes(),
                'manual_business_types' => $adapter instanceof \addon\hsx_erp_integration\app\contract\ManualDocumentAdapterInterface
                    ? $adapter->manualBusinessTypes('') : [],
                'fields' => $fields,
                'mapping_requirements' => $adapter->getMappingRequirements(),
            ];
        }
        return $list;
    }

    /**
     * 检查适配器是否已注册
     * @param string $code
     * @return bool
     */
    public function hasAdapter(string $code): bool
    {
        return isset($this->getAllAdapters()[$code]);
    }
}
