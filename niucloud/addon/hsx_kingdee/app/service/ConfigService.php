<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\service;

use addon\hsx_kingdee\app\support\CredentialCipher;
use app\model\site\Site;
use app\model\sys\SysConfig;
use app\model\sys\SysUserRole;
use app\model\sys\SysUser;
use app\service\core\site\CoreSiteService;
use app\service\core\sys\CoreConfigService;
use think\facade\Cache;
use think\facade\Db;

final class ConfigService
{
    private const KEY = 'HSX_KINGDEE_CONNECTION';

    public function installed(int $siteId): bool
    {
        $keys = (new CoreSiteService())->getAddonKeysBySiteId($siteId);
        return in_array('hsx_erp', $keys, true) && in_array('hsx_kingdee', $keys, true);
    }

    public function get(int $siteId, bool $public = true): array
    {
        $rows = SysConfig::where([['site_id', '=', $siteId], ['config_key', '=', self::KEY]])->limit(2)->select()->toArray();
        if (count($rows) > 1) throw new \RuntimeException('金蝶配置重复，请先核查配置记录');
        $config = array_merge([
            'mode' => 'disabled', 'connection_key' => '', 'endpoint' => '', 'account_id' => '',
            'username' => '', 'app_id' => '', 'secret_cipher' => '', 'organization' => '',
            'assignee_uid' => 0, 'collect_from' => 0, 'revision' => 0,
        ], $rows[0]['value'] ?? []);
        if ($public) {
            $config['secret_configured'] = $config['secret_cipher'] !== '';
            unset($config['secret_cipher']);
        }
        $config['write_enabled'] = false;
        return $config;
    }

    public function save(int $siteId, int $uid, array $input): array
    {
        if (!$this->installed($siteId)) throw new \RuntimeException('当前站点需同时安装 ERP 和金蝶插件');
        Db::transaction(function () use ($siteId, $uid, $input): void {
            if (Site::where('site_id', $siteId)->lock(true)->findOrEmpty()->isEmpty()) throw new \RuntimeException('站点不存在');
            $old = $this->get($siteId, false);
            if ((int)($input['revision'] ?? -1) !== (int)$old['revision']) throw new \RuntimeException('配置已被其他人修改，请刷新后重试');
            $mode = (string)($input['mode'] ?? 'disabled');
            if (!in_array($mode, ['disabled', 'preview'], true)) throw new \RuntimeException('当前版本仅支持关闭或预览，不允许写账套');
            $new = $old;
            unset($new['write_enabled']);
            foreach (['endpoint', 'account_id', 'username', 'app_id', 'organization'] as $key) {
                $new[$key] = trim((string)($input[$key] ?? ''));
                if (strlen($new[$key]) > 255) throw new \RuntimeException('配置字段过长');
            }
            if ($new['endpoint'] !== '') {
                $url = parse_url($new['endpoint']);
                if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
                    throw new \RuntimeException('请输入不带账号、参数或片段的 HTTPS 金蝶服务地址');
                }
                $new['endpoint'] = rtrim($new['endpoint'], '/') . '/';
            }
            $hasTasks = Db::name('hsx_kingdee_task')->where('site_id', $siteId)->count() > 0;
            if ($hasTasks && ($old['endpoint'] !== $new['endpoint'] || $old['account_id'] !== $new['account_id'] || $old['organization'] !== $new['organization'])) {
                throw new \RuntimeException('已有业务记录，不能切换账套、地址或组织；需要建立新连接，不能复用原映射');
            }
            $assignee = max(0, (int)($input['assignee_uid'] ?? 0));
            if ($assignee > 0 && (!SysUserRole::where([['site_id', '=', $siteId], ['uid', '=', $assignee], ['status', '=', 1]])->count()
                || !SysUser::where([['uid', '=', $assignee], ['status', '=', 1]])->count())) {
                throw new \RuntimeException('责任人必须是本站有效员工');
            }
            if ($mode === 'preview' && ($new['endpoint'] === '' || $new['account_id'] === '' || $new['organization'] === '')) {
                throw new \RuntimeException('先填写测试账套地址、账套ID和组织，避免后续映射归属不明');
            }
            $secret = (string)($input['app_secret'] ?? '');
            if (strlen($secret) > 2000) throw new \RuntimeException('应用密钥过长');
            if ($secret !== '') $new['secret_cipher'] = CredentialCipher::seal($secret, (string)env('app.auth_key', ''), $siteId);
            if (!empty($input['clear_secret'])) $new['secret_cipher'] = '';
            $new['connection_key'] = $old['connection_key'] ?: bin2hex(random_bytes(16));
            $new['mode'] = $mode;
            $new['assignee_uid'] = $assignee;
            $new['collect_from'] = $mode === 'preview' && $old['mode'] !== 'preview' ? time() + 1 : $old['collect_from'];
            $new['revision'] = (int)$old['revision'] + 1;
            $service = new class extends CoreConfigService {
                public function getConfig(int $site_id, string $key): array
                {
                    return SysConfig::where([['site_id', '=', $site_id], ['config_key', '=', $key]])->findOrEmpty()->toArray();
                }
            };
            if (!$service->setConfig($siteId, self::KEY, $new)) throw new \RuntimeException('配置保存失败');
            TaskService::audit($siteId, 0, 'config', 'saved', '配置已更新；运行模式：' . $mode, $uid);
        });
        Cache::tag(CoreConfigService::$cache_tag_name . $siteId)->clear();
        return $this->get($siteId);
    }
}
