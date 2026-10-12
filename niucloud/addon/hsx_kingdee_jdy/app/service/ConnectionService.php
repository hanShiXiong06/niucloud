<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\service;

use addon\hsx_kingdee_jdy\app\gateway\Client;
use addon\hsx_kingdee_jdy\app\support\Problem;
use addon\hsx_kingdee_jdy\app\support\Secret;
use app\model\sys\SysConfig;
use think\facade\Cache;
use think\facade\Db;

final class ConnectionService
{
    public function probe(int $site, int $uid, int $revision, ?Client $client = null): array
    {
        $service = new ConfigService();
        if (!$service->installed($site)) throw new Problem('ADDON_MISSING', '本站未安装ERP和精斗云插件');
        $config = $service->credentials($site);
        if ((int)$config['revision'] !== $revision) throw new Problem('CONFIG_STALE', '授权已更新，请刷新后检查连接');
        Db::transaction(function () use ($site, $revision): void {
            Db::name('site')->where('site_id', $site)->lock(true)->find();
            $row = SysConfig::where('site_id', $site)->where('config_key', ConfigService::KEY)->findOrEmpty();
            $value = $row->value;
            if ((int)($value['revision'] ?? 0) !== $revision) throw new Problem('CONFIG_STALE', '授权已更新，请刷新后检查连接');
            if ((int)($value['probe_next_at'] ?? 0) > time()) throw new Problem('PROBE_BUSY', '正在检查连接或刚刚检查过，请稍候');
            $value['probe_next_at'] = time() + 60;
            $row->save(['value' => $value]);
        });
        try {
            $client = $client ?? new Client();
            $tokenKey = 'hsx_kingdee_jdy_token_' . $site . '_' . $revision;
            $cached = Cache::get($tokenKey);
            $token = is_string($cached) && $cached !== '' ? Secret::open($cached, (string)env('app.auth_key', ''), $site, 'token:' . $revision) : '';
            if ($token === '') {
                $auth = $client->token($config);
                $token = $auth['access_token'];
                Cache::set($tokenKey, Secret::seal($token, (string)env('app.auth_key', ''), $site, 'token:' . $revision),
                    max(1, (int)floor($auth['expires'] / 1000) - time() - 60));
            }
            // Query only one document; discard customer, serial and commercial details.
            $client->query($config, $token, 'purchase', ['checkStatus' => 2, 'pageSize' => 1]);
            Db::transaction(function () use ($site, $uid, $revision): void {
                Db::name('site')->where('site_id', $site)->lock(true)->find();
                $row = SysConfig::where('site_id', $site)->where('config_key', ConfigService::KEY)->findOrEmpty();
                $value = $row->value;
                if ((int)($value['revision'] ?? 0) !== $revision) throw new Problem('CONFIG_STALE', '检查期间授权已更改，本次结果未保存，请重新检查');
                $value['probe_revision'] = $revision;
                $value['probe_at'] = time();
                $row->save(['value' => $value]);
                TaskService::audit($site, 0, 'probe', 'read_verified', '授权与购货单只读查询通过；不代表写单、库存或账务验收通过', $uid);
            });
            return ['status' => 'read_verified', 'message' => '授权与账套只读查询通过，未写单、未导入库存', 'write_enabled' => false];
        } catch (Problem $e) {
            TaskService::audit($site, 0, 'probe', $e->reason, $e->getMessage(), $uid);
            throw $e;
        }
    }
}
