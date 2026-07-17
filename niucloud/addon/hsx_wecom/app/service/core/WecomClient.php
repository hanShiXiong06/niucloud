<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use core\exception\CommonException;
use think\facade\Cache;

final class WecomClient
{
    private const API_BASE = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function test(array $config): array
    {
        $token = $this->accessToken($config, true);
        $result = $this->request('GET', '/agent/get', [
            'access_token' => $token,
            'agentid' => (int)$config['agent_id'],
        ]);
        return [
            'connected' => true,
            'agent_name' => (string)($result['name'] ?? ''),
            'agent_id' => (int)$config['agent_id'],
        ];
    }

    public function sendTextCard(array $config, string $userId, array $card): array
    {
        $payload = [
            'touser' => $userId,
            'msgtype' => 'textcard',
            'agentid' => (int)$config['agent_id'],
            'textcard' => [
                'title' => (string)($card['title'] ?? '新的待办任务'),
                'description' => (string)($card['description'] ?? ''),
                'url' => (string)($card['url'] ?? ''),
                'btntxt' => (string)($card['button_text'] ?? '查看任务'),
            ],
            'enable_id_trans' => 0,
            'enable_duplicate_check' => 1,
            'duplicate_check_interval' => 1800,
        ];
        return $this->request('POST', '/message/send', [
            'access_token' => $this->accessToken($config),
        ], $payload);
    }

    public function sendTaskCard(array $config, string $userId, array $card): array
    {
        $jumpList = [];
        $miniappAppid = trim((string)($card['miniapp_appid'] ?? ''));
        $miniappPath = ltrim(trim((string)($card['miniapp_path'] ?? '')), '/');
        $webUrl = trim((string)($card['web_url'] ?? ''));
        if ($miniappAppid !== '' && $miniappPath !== '') {
            $jumpList[] = [
                'type' => 2,
                'title' => '小程序处理',
                'appid' => $miniappAppid,
                'pagepath' => $miniappPath,
            ];
        }
        if ($webUrl !== '') {
            $jumpList[] = [
                'type' => 1,
                'title' => '网页处理',
                'url' => $webUrl,
            ];
        }
        if ($jumpList === []) {
            return $this->sendTextCard($config, $userId, $card);
        }

        $primary = $jumpList[0];
        $cardAction = ['type' => (int)$primary['type']];
        if ((int)$primary['type'] === 2) {
            $cardAction['appid'] = (string)$primary['appid'];
            $cardAction['pagepath'] = (string)$primary['pagepath'];
        } else {
            $cardAction['url'] = (string)$primary['url'];
        }
        $payload = [
            'touser' => $userId,
            'msgtype' => 'template_card',
            'agentid' => (int)$config['agent_id'],
            'template_card' => [
                'card_type' => 'text_notice',
                'source' => ['desc' => (string)($card['source_desc'] ?? '业务待办')],
                'main_title' => [
                    'title' => (string)($card['title'] ?? '新的待办任务'),
                    'desc' => (string)($card['summary'] ?? '任务已分配给你，请及时处理'),
                ],
                'sub_title_text' => (string)($card['plain_content'] ?? ''),
                'jump_list' => $jumpList,
                'card_action' => $cardAction,
            ],
            'enable_id_trans' => 0,
            'enable_duplicate_check' => 1,
            'duplicate_check_interval' => 1800,
        ];
        return $this->request('POST', '/message/send', [
            'access_token' => $this->accessToken($config),
        ], $payload);
    }

    private function accessToken(array $config, bool $refresh = false): string
    {
        $corpId = trim((string)($config['corp_id'] ?? ''));
        $secret = trim((string)($config['secret'] ?? ''));
        if ($corpId === '' || $secret === '' || (int)($config['agent_id'] ?? 0) <= 0) {
            throw new CommonException('请先完整配置企业 ID、应用 AgentId 和 Secret');
        }
        $cacheKey = 'hsx_wecom_token_' . md5($corpId . ':' . $secret);
        if (!$refresh) {
            $cached = (string)Cache::get($cacheKey, '');
            if ($cached !== '') return $cached;
        }
        $response = $this->request('GET', '/gettoken', ['corpid' => $corpId, 'corpsecret' => $secret]);
        $token = trim((string)($response['access_token'] ?? ''));
        if ($token === '') throw new CommonException('企业微信未返回 access_token');
        Cache::set($cacheKey, $token, max(60, (int)($response['expires_in'] ?? 7200) - 300));
        return $token;
    }

    private function request(string $method, string $path, array $query = [], array $json = []): array
    {
        $url = self::API_BASE . $path . ($query ? '?' . http_build_query($query) : '');
        $curl = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($body === false || $error !== '') throw new CommonException('请求企业微信失败：' . $error);
        if ($status < 200 || $status >= 300) throw new CommonException('企业微信接口 HTTP 状态异常：' . $status);
        $result = json_decode((string)$body, true);
        if (!is_array($result)) throw new CommonException('企业微信接口返回格式异常');
        if ((int)($result['errcode'] ?? 0) !== 0) {
            throw new CommonException('企业微信接口错误：' . (string)($result['errmsg'] ?? $result['errcode']));
        }
        return $result;
    }
}
