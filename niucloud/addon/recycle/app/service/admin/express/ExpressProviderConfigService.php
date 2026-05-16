<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\express;

use addon\recycle\app\dict\express\ExpressProviderDict;
use addon\recycle\app\model\express\ExpressProviderConfig;
use addon\recycle\app\service\core\express\RecycleExpressService;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 快递服务商配置管理服务（管理端）
 * Class ExpressProviderConfigService
 * @package addon\recycle\app\service\admin\express
 */
class ExpressProviderConfigService extends BaseAdminService
{
    /**
     * @var ExpressProviderConfig
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ExpressProviderConfig();
    }

    /**
     * 获取服务商配置列表
     * @return array
     */
    public function getList(): array
    {
        $list = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['provider', '=', ExpressProviderDict::PROVIDER_YISU],
        ])->order('sort asc, id asc')->select()->toArray();

        // 如果没有配置，初始化默认配置
        if (empty($list)) {
            ExpressProviderConfig::initSiteConfig($this->site_id);
            $list = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['provider', '=', ExpressProviderDict::PROVIDER_YISU],
            ])->order('sort asc, id asc')->select()->toArray();
        }

        // 附加服务商信息
        $allProviders = ExpressProviderDict::getProviders();
        foreach ($list as &$item) {
            $providerInfo = $allProviders[$item['provider']] ?? [];
            $item['provider_label'] = $providerInfo['name'] ?? $item['provider_name'];
            $item['support_quote'] = $providerInfo['support_quote'] ?? false;
            $item['support_cancel'] = $providerInfo['support_cancel'] ?? false;
            $item['support_track'] = $providerInfo['support_track'] ?? false;
            $item['status_name'] = $item['status'] ? '启用' : '停用';
        }

        return $list;
    }

    /**
     * 获取服务商配置详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id): array
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();

        if (empty($info)) {
            throw new AdminException('配置不存在');
        }

        return $info;
    }

    /**
     * 编辑服务商配置
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data): bool
    {
        $record = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (!$record) {
            throw new AdminException('配置不存在');
        }
        if ($record->provider !== ExpressProviderDict::PROVIDER_YISU) {
            throw new AdminException('2.0 阶段仅支持亿速快递');
        }

        $updateData = [];

        if (isset($data['status'])) {
            $updateData['status'] = (int)$data['status'];
        }

        if (isset($data['config'])) {
            $updateData['config'] = is_string($data['config']) ? $data['config'] : json_encode($data['config'], JSON_UNESCAPED_UNICODE);
        }

        if (isset($data['sort'])) {
            $updateData['sort'] = (int)$data['sort'];
        }

        if (isset($data['provider_name'])) {
            $updateData['provider_name'] = $data['provider_name'];
        }

        if (!empty($updateData)) {
            $updateData['update_at'] = time();
            $record->save($updateData);
        }

        return true;
    }

    /**
     * 设置默认服务商
     * @param int $id
     * @return bool
     */
    public function setDefault(int $id): bool
    {
        $record = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (!$record) {
            throw new AdminException('配置不存在');
        }
        if ($record->provider !== ExpressProviderDict::PROVIDER_YISU) {
            throw new AdminException('2.0 阶段仅支持亿速快递');
        }

        if (!$record->status) {
            throw new AdminException('请先启用该服务商');
        }

        // 将该服务商设为默认
        ExpressProviderConfig::setDefault($this->site_id, $record->provider);

        return true;
    }

    /**
     * 切换服务商启用状态
     * @param int $id
     * @return bool
     */
    public function toggleStatus(int $id): bool
    {
        $record = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (!$record) {
            throw new AdminException('配置不存在');
        }
        if ($record->provider !== ExpressProviderDict::PROVIDER_YISU) {
            throw new AdminException('2.0 阶段仅支持亿速快递');
        }

        $newStatus = $record->status ? 0 : 1;

        // 如果要停用默认服务商，检查是否还有其他启用的服务商
        if ($newStatus === 0 && $record->is_default) {
            $otherEnabled = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['id', '<>', $id],
                ['status', '=', 1],
                ['provider', '=', ExpressProviderDict::PROVIDER_YISU],
            ])->find();

            if ($otherEnabled) {
                // 将其他启用的设为默认
                ExpressProviderConfig::setDefault($this->site_id, $otherEnabled->provider);
            }

            // 取消当前默认
            $record->save([
                'is_default' => 0,
                'status' => $newStatus,
                'update_at' => time()
            ]);
        } else {
            $record->save([
                'status' => $newStatus,
                'update_at' => time()
            ]);
        }

        return true;
    }

    /**
     * 获取当前启用的服务商信息
     * @return array
     */
    public function getActiveProvider(): array
    {
        $expressService = new RecycleExpressService();

        try {
            $provider = $expressService->getActiveProvider($this->site_id);
            $allProviders = ExpressProviderDict::getProviders();
            $providerInfo = $allProviders[$provider] ?? [];

            return [
                'provider' => $provider,
                'provider_name' => ExpressProviderDict::getProviderName($provider),
                'support_quote' => $providerInfo['support_quote'] ?? false,
                'support_cancel' => $providerInfo['support_cancel'] ?? false,
                'support_track' => $providerInfo['support_track'] ?? false,
            ];
        } catch (\Exception $e) {
            return [
                'provider' => '',
                'provider_name' => '未配置',
                'support_quote' => false,
                'support_cancel' => false,
                'support_track' => false,
            ];
        }
    }

    /**
     * 检查快递服务是否可用
     * @return array
     */
    public function checkExpressStatus(): array
    {
        $expressService = new RecycleExpressService();

        $enabled = $expressService->isExpressEnabled($this->site_id);
        $shopAddress = null;
        $activeProvider = '';

        if ($enabled) {
            $shopAddress = $expressService->getShopAddress($this->site_id);
            try {
                $activeProvider = $expressService->getActiveProvider($this->site_id);
            } catch (\Exception $e) {
                $activeProvider = '';
            }
        }

        return [
            'enabled' => $enabled,
            'active_provider' => $activeProvider,
            'active_provider_name' => $activeProvider ? ExpressProviderDict::getProviderName($activeProvider) : '未配置',
            'has_shop_address' => !empty($shopAddress),
            'shop_address' => $shopAddress,
        ];
    }
}
