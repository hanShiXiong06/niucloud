<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 代理体系配置（主站ID等）
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\agent;

use app\service\core\sys\CoreConfigService;
use app\model\site\Site;
use core\base\BaseCoreService;
use core\exception\CommonException;

/**
 * 代理体系配置服务
 * 主站ID不硬编码，存全局配置(site_id=0)，默认 100005，可在后台改。
 * 主站显示别名按子站 site_id 独立保存，不改变全局主站配置。
 * Class AgentConfigService
 * @package addon\phone_shop\app\service\core\agent
 */
class AgentConfigService extends BaseCoreService
{
    /**
     * 全局配置键
     */
    const CONFIG_KEY = 'PHONE_SHOP_MASTER_SITE';

    const DISPLAY_CONFIG_KEY = 'PHONE_SHOP_AGENT_DISPLAY';

    /**
     * 默认主站ID
     */
    const DEFAULT_MASTER_SITE_ID = 100005;

    /**
     * 配置存放站点（0=全局）
     */
    const CONFIG_SITE_ID = 0;

    /**
     * 取主站ID
     * @return int
     */
    public function getMasterSiteId(): int
    {
        $value = (new CoreConfigService())->getConfigValue(self::CONFIG_SITE_ID, self::CONFIG_KEY);
        $id = is_array($value) ? (int) ($value['master_site_id'] ?? 0) : 0;
        return $id > 0 ? $id : self::DEFAULT_MASTER_SITE_ID;
    }

    /**
     * 设主站ID
     * @param int $masterSiteId
     * @return bool
     */
    public function setMasterSiteId(int $masterSiteId): bool
    {
        if ($masterSiteId <= 0) return false;
        (new CoreConfigService())->setConfig(self::CONFIG_SITE_ID, self::CONFIG_KEY, [
            'master_site_id' => $masterSiteId,
        ]);
        return true;
    }

    /**
     * 是否主站
     * @param int $siteId
     * @return bool
     */
    public function isMasterSite(int $siteId): bool
    {
        return $siteId > 0 && $siteId === $this->getMasterSiteId();
    }

    /** 子站的展示别名不改变主站名称、站点关系或商品来源。 */
    public function getDisplayConfig(int $siteId): array
    {
        $masterSiteId = $this->getMasterSiteId();
        $value = $siteId > 0 ? (new CoreConfigService())->getConfigValue($siteId, self::DISPLAY_CONFIG_KEY) : [];
        $name = is_array($value) && is_string($value['agent_name'] ?? null) ? trim($value['agent_name']) : '';
        $defaultName = trim((string)(new Site())->where('site_id', $masterSiteId)->value('site_name'));
        if ($defaultName === '') $defaultName = '代理仓';
        return [
            'master_site_id' => $masterSiteId,
            'agent_name' => $name,
            'default_agent_name' => $defaultName,
            'effective_agent_name' => $name !== '' ? $name : $defaultName,
        ];
    }

    public function setDisplayConfig(int $siteId, array $data): bool
    {
        if ($siteId <= 0 || $this->isMasterSite($siteId)) {
            throw new CommonException('仅子站可以设置主站显示名称');
        }
        $name = $data['agent_name'] ?? '';
        if (!is_string($name) || !mb_check_encoding($name, 'UTF-8')) {
            throw new CommonException('请填写有效的主站显示名称');
        }
        if (mb_strlen(trim($name), 'UTF-8') > 12 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            throw new CommonException('主站显示名称最多12个字，不能包含换行或控制字符');
        }
        $name = trim($name);
        (new CoreConfigService())->setConfig($siteId, self::DISPLAY_CONFIG_KEY, ['agent_name' => $name]);
        return true;
    }
}
