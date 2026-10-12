<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\service;

use addon\hsx_kingdee\app\gateway\ApiFailure;
use addon\hsx_kingdee\app\gateway\EndpointPolicy;
use addon\hsx_kingdee\app\gateway\OfficialSdk;
use addon\hsx_kingdee\app\gateway\ReadOnlyCurlTransport;
use addon\hsx_kingdee\app\gateway\WebApiClient;
use addon\hsx_kingdee\app\support\CredentialCipher;
use think\facade\Db;

final class ConnectionProbeService
{
    public function __construct(private ?\Closure $clientFactory = null) {}

    public function run(int $site, int $uid, int $revision): array
    {
        $service = new ConfigService();
        if (!$service->installed($site)) throw new \RuntimeException('当前站点需同时安装 ERP 和金蝶插件');
        // Serialize the local rate gate only. No network request is made while holding a DB lock.
        $config = Db::transaction(function () use ($service, $site, $uid, $revision): array {
            if (!Db::name('site')->where('site_id', $site)->lock(true)->find()) throw new \RuntimeException('站点不存在');
            $config = $service->get($site, false);
            if ((int)$config['revision'] !== $revision) throw new \RuntimeException('配置已变更，请刷新配置后再检查连接');
            $recent = Db::name('hsx_kingdee_log')->where('site_id', $site)->where('action', 'probe_requested')
                ->where('create_at', '>', time() - 30)->count();
            if ($recent) throw new \RuntimeException('检查过于频繁，请等待30秒后再试');
            TaskService::audit($site, 0, 'probe_requested', 'checking', '人工发起只读连接检查，配置版本 ' . $revision, $uid);
            return $config;
        });
        $report = ['status' => 'failed', 'code' => '', 'message' => '', 'revision' => $revision,
            'checked_at' => time(), 'write_enabled' => false, 'sdk_version' => OfficialSdk::VERSION];
        try {
            foreach (['endpoint', 'account_id', 'username', 'app_id', 'organization', 'secret_cipher'] as $field) {
                if (($config[$field] ?? '') === '') throw new ApiFailure('credentials_incomplete', '请先保存完整的服务地址、账套、组织、集成用户、应用 ID 和应用密钥');
            }
            try { $secret = CredentialCipher::open($config['secret_cipher'], (string)env('app.auth_key', ''), $site); }
            catch (\Throwable $error) { throw new ApiFailure('credential_unreadable', '无法读取已保存的应用密钥，请重新配置或检查服务器加密配置'); }
            if ($this->clientFactory) {
                $client = ($this->clientFactory)($config, $site, $secret);
            } else {
                $endpoint = new EndpointPolicy($config['endpoint'], explode(',', (string)env('hsx_kingdee.allowed_hosts', '')));
                $client = new WebApiClient($endpoint, $config, $secret, new ReadOnlyCurlTransport($endpoint));
            }
            // A deliberately nonexistent material code checks authorization without exporting master data.
            $client->queryExact('BD_MATERIAL', ['FNumber'], ['FNumber' => '__HSX_CONNECTION_PROBE__'], 1);
            $report['status'] = 'passed';
            $report['code'] = 'query_verified';
            $report['message'] = '签名查询通过，未写入单据。组织权限、客户供应商、单据字段及写入权限仍需分别验收。';
        } catch (ApiFailure $error) {
            $report['code'] = $error->reason;
            $report['message'] = $error->getMessage();
        } catch (\Throwable $error) {
            $report['code'] = 'probe_failed';
            $report['message'] = '连接检查未完成，请检查服务器配置；未执行写单，原始响应和密钥未记录。';
        }
        if ((int)$service->get($site)['revision'] !== $revision) {
            $report['status'] = 'stale';
            $report['code'] = 'config_changed';
            $report['message'] = '检查期间配置已变更，本次结果仅对应旧配置，请重新检查。';
        }
        TaskService::audit($site, 0, 'probe_result', $report['status'], 'SDK ' . OfficialSdk::VERSION . '；配置版本 ' . $revision . '；' . $report['message'], $uid);
        return $report;
    }
}
