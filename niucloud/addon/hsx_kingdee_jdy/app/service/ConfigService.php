<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\service;

use addon\hsx_kingdee_jdy\app\gateway\ReadOnlyTransport;
use addon\hsx_kingdee_jdy\app\support\Problem;
use addon\hsx_kingdee_jdy\app\support\Secret;
use app\model\sys\SysConfig;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use app\service\core\site\CoreSiteService;
use think\facade\Db;

final class ConfigService
{
    public const KEY = 'HSX_KINGDEE_JDY_CONNECTION';

    public function installed(int $site): bool
    {
        $keys = (new CoreSiteService())->getAddonKeysBySiteId($site);
        return in_array('hsx_erp', $keys, true) && in_array('hsx_kingdee_jdy', $keys, true);
    }

    public function get(int $site, bool $public = true): array
    {
        $rows = SysConfig::where('site_id', $site)->where('config_key', self::KEY)->limit(2)->select()->toArray();
        if (count($rows) > 1) throw new Problem('CONFIG_DUPLICATE', '精斗云配置重复，请检查配置记录');
        $value = array_merge(['mode' => 'disabled', 'client_id' => '', 'app_key' => '', 'account_id' => '', 'domain' => '',
            'client_secret_cipher' => '', 'app_secret_cipher' => '', 'connection_key' => '', 'revision' => 0,
            'assignee_uid' => 0, 'collect_from' => 0, 'probe_revision' => 0, 'probe_at' => 0], $rows[0]['value'] ?? []);
        if ($public) {
            foreach (['client_secret', 'app_secret'] as $field) {
                $value[$field . '_configured'] = $value[$field . '_cipher'] !== '';
                unset($value[$field . '_cipher']);
            }
        }
        $value['write_enabled'] = false;
        return $value;
    }

    public function save(int $site, int $uid, array $input): array
    {
        if (!$this->installed($site)) throw new Problem('ADDON_MISSING', '本站需安装ERP和精斗云插件');
        Db::transaction(function () use ($site, $uid, $input): void {
            if (!Db::name('site')->where('site_id', $site)->lock(true)->find()) throw new Problem('SITE_INVALID', '站点不存在');
            $old = $this->get($site, false);
            if ((int)($input['revision'] ?? -1) !== (int)$old['revision']) throw new Problem('CONFIG_STALE', '配置已更新，请刷新后重新保存');
            $new = $old;
            unset($new['write_enabled']);
            foreach (['client_id', 'app_key', 'account_id', 'domain'] as $field) {
                $new[$field] = trim((string)($input[$field] ?? ''));
                if (strlen($new[$field]) > 255 || preg_match('/[\x00-\x1F]/', $new[$field])) throw new Problem('CONFIG_INVALID', '授权字段过长或含控制字符');
            }
            foreach (['client_id', 'account_id'] as $field) {
                if ($new[$field] !== '' && !preg_match('/^\d+$/D', $new[$field])) throw new Problem('CONFIG_INVALID', '应用ID和精斗云账套ID应为数字，不可填写云星空账套');
            }
            if ($new['domain'] !== '') $new['domain'] = ReadOnlyTransport::router($new['domain']);
            $hasData = Db::name('hsx_kingdee_jdy_task')->where('site_id', $site)->count()
                || Db::name('hsx_kingdee_jdy_mapping')->where('site_id', $site)->count();
            if ($hasData && $new['account_id'] !== $old['account_id']) throw new Problem('ACCOUNT_LOCKED', '已有任务或映射，不能切换账套，避免把旧业务发往新账套');
            foreach (['client_secret', 'app_secret'] as $field) {
                $plain = (string)($input[$field] ?? '');
                if (strlen($plain) > 2000) throw new Problem('CONFIG_INVALID', '密钥过长');
                if ($plain !== '') $new[$field . '_cipher'] = Secret::seal($plain, (string)env('app.auth_key', ''), $site, $field);
            }
            $new['mode'] = (string)($input['mode'] ?? 'disabled');
            if (!in_array($new['mode'], ['disabled', 'preview'], true)) throw new Problem('WRITE_DISABLED', '当前版本只允许关闭或本地预检');
            if ($new['mode'] === 'preview' && $new['account_id'] === '') throw new Problem('ACCOUNT_REQUIRED', '接收业务前需填写精斗云账套ID，防止映射归属不明');
            $new['assignee_uid'] = max(0, (int)($input['assignee_uid'] ?? 0));
            if ($new['assignee_uid'] > 0 && (!SysUserRole::where('site_id', $site)->where('uid', $new['assignee_uid'])->where('status', 1)->count()
                || !SysUser::where('uid', $new['assignee_uid'])->where('status', 1)->count())) throw new Problem('ASSIGNEE_INVALID', '请选择本站有效员工');
            $new['connection_key'] = $old['connection_key'] ?: bin2hex(random_bytes(16));
            $new['revision'] = (int)$old['revision'] + 1;
            $new['probe_revision'] = 0;
            $new['probe_at'] = 0;
            $new['collect_from'] = $new['mode'] === 'preview' && $old['mode'] !== 'preview' ? time() + 1 : $old['collect_from'];
            $row = SysConfig::where('site_id', $site)->where('config_key', self::KEY)->findOrEmpty();
            if ($row->isEmpty()) SysConfig::create(['site_id' => $site, 'config_key' => self::KEY, 'value' => $new]);
            else $row->save(['value' => $new]);
            TaskService::audit($site, 0, 'config', 'saved', '授权配置已保存；运行模式：' . $new['mode'], $uid);
        });
        return $this->get($site);
    }

    public function credentials(int $site): array
    {
        $config = $this->get($site, false);
        foreach (['client_secret', 'app_secret'] as $field) {
            if ($config[$field . '_cipher'] === '') throw new Problem('AUTH_INCOMPLETE', '请先保存应用密钥和账套授权密钥');
            $config[$field] = Secret::open($config[$field . '_cipher'], (string)env('app.auth_key', ''), $site, $field);
            unset($config[$field . '_cipher']);
        }
        return $config;
    }
}
