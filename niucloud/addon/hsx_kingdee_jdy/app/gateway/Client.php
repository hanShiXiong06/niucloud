<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\gateway;

use addon\hsx_kingdee_jdy\app\support\Problem;

final class Client
{
    private Transport $transport;
    public function __construct(?Transport $transport = null) { $this->transport = $transport ?? new ReadOnlyTransport(); }

    public function token(array $config): array
    {
        foreach (['client_id', 'client_secret', 'app_key', 'app_secret'] as $key) {
            if (empty($config[$key])) throw new Problem('AUTH_INCOMPLETE', '请补齐应用ID、应用密钥、授权Key和授权密钥');
        }
        $query = ['app_key' => $config['app_key'], 'app_signature' => Signature::hmac($config['app_key'], $config['app_secret'])];
        $data = $this->transport->send('GET', ReadOnlyTransport::TOKEN_PATH, $query,
            Signature::headers($config['client_id'], $config['client_secret'], ReadOnlyTransport::TOKEN_PATH, $query), []);
        if (!isset($data['errcode']) || (string)$data['errcode'] !== '0') {
            throw new Problem('AUTH_REJECTED', '精斗云授权校验未通过，请检查两组密钥及授权有效期');
        }
        $token = $data['data']['access_token'] ?? null;
        $expires = $data['data']['expires'] ?? null;
        if (!is_string($token) || $token === '' || !is_numeric($expires) || (float)$expires <= microtime(true) * 1000 + 60000) {
            throw new Problem('TOKEN_INVALID', '精斗云未返回有效访问凭证或凭证即将过期');
        }
        return ['access_token' => $token, 'expires' => (int)$expires];
    }

    public function query(array $config, string $token, string $kind, array $filter = []): array
    {
        $paths = ['purchase' => 'purchase', 'sale' => 'delivery', 'purchase_return' => 'purchaseReturn',
            'sale_return' => 'saleReturn', 'customer' => 'customer'];
        if (!isset($paths[$kind])) throw new Problem('QUERY_UNSUPPORTED', '暂不支持此查询类型');
        if (!preg_match('/^\d+$/D', (string)($config['account_id'] ?? '')) || $token === '') {
            throw new Problem('ACCOUNT_INVALID', '请填写精斗云账套ID并完成授权');
        }
        $router = ReadOnlyTransport::router((string)($config['domain'] ?? ''));
        $filter['page'] = max(1, (int)($filter['page'] ?? 1));
        $filter['pageSize'] = min(100, max(1, (int)($filter['pageSize'] ?? 20)));
        $data = $this->transport->send('POST', '/jdyscm/' . $paths[$kind] . '/list',
            ['access_token' => $token, 'dbId' => $config['account_id']],
            ['Content-Type' => 'application/json', 'X-GW-Router-Addr' => $router], ['filter' => $filter]);
        if (!isset($data['code']) || (string)$data['code'] !== '0') {
            if (isset($data['code']) && (string)$data['code'] === '46002') return ['items' => [], 'records' => 0, 'totalPages' => 0, 'page' => $filter['page']];
            throw new Problem('QUERY_REJECTED', '账套查询未通过，请检查API权限、账套ID与IDC域名');
        }
        if (!isset($data['items']) || !is_array($data['items'])) throw new Problem('RESPONSE_INVALID', '账套响应缺少列表，不能视为查询成功');
        return $data;
    }
}
