<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\core;

use core\exception\CommonException;

/**
 * 点点会员卡旧平台适配器。
 *
 * 所有第三方协议细节集中在此类，业务层只消费标准分页结构。
 */
final class MemberCardLegacyClient
{
    private const BASE_URL = 'https://www.dianddnet.cn/sjk-erp-service/api';
    private const PAGE_SIZE = 100;

    public function login(string $phone, string $password): array
    {
        if (!preg_match('/^1\d{10}$/', $phone)) throw new CommonException('请输入旧平台正确的11位手机号');
        if ($password === '') throw new CommonException('请输入旧平台登录密码');
        $response = $this->post('/member/login', [
            'openId' => '',
            'phoneNumber' => $phone,
            'passwords' => $password,
        ]);
        $data = (array)($response['data'] ?? []);
        $tokenInfo = (array)($data['tokenInfo'] ?? []);
        $tokenName = trim((string)($tokenInfo['tokenName'] ?? 'recovery-token'));
        $tokenValue = trim((string)($tokenInfo['tokenValue'] ?? ''));
        if ($tokenValue === '') throw new CommonException('旧平台登录成功但未返回 Token');
        return [
            'token_name' => $tokenName !== '' ? $tokenName : 'recovery-token',
            'token_value' => $tokenValue,
            'store_id' => (string)($data['storeId'] ?? ''),
            'store_name' => (string)($data['storeInfo']['storeName'] ?? ''),
            'real_name' => (string)($data['realName'] ?? ''),
            'account_id' => (string)($data['id'] ?? ''),
        ];
    }

    public function productPage(array $credential, int $page = 1): array
    {
        return $this->page('/card/queryPage', [
            'current' => max(1, $page),
            'pageSize' => self::PAGE_SIZE,
            'name' => '',
        ], $credential);
    }

    public function customerPage(array $credential, int $page = 1): array
    {
        return $this->page('/customer/queryPage', [
            'current' => max(1, $page),
            'pageSize' => self::PAGE_SIZE,
            'searchValue' => '',
        ], $credential);
    }

    /** @return array{records:array,total:int,current:int,pages:int,size:int} */
    private function page(string $path, array $payload, array $credential): array
    {
        $tokenName = trim((string)($credential['token_name'] ?? 'recovery-token'));
        $tokenValue = trim((string)($credential['token_value'] ?? ''));
        if ($tokenValue === '') throw new CommonException('旧平台登录状态已失效，请重新输入账号密码');
        $response = $this->post($path, $payload, [$tokenName . ': ' . $tokenValue]);
        $data = (array)($response['data'] ?? []);
        return [
            'records' => array_values(array_filter((array)($data['records'] ?? []), 'is_array')),
            'total' => max(0, (int)($data['total'] ?? 0)),
            'current' => max(1, (int)($data['current'] ?? 1)),
            'pages' => max(1, (int)($data['pages'] ?? 1)),
            'size' => max(1, (int)($data['size'] ?? self::PAGE_SIZE)),
        ];
    }

    private function post(string $path, array $payload, array $headers = []): array
    {
        $ch = curl_init(self::BASE_URL . $path);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Niucloud-HsxMemberCard-Migration/1.0',
            ], $headers),
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $message = curl_error($ch) ?: '网络连接失败';
            curl_close($ch);
            throw new CommonException('旧平台请求失败：' . $message);
        }
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode >= 400) throw new CommonException('旧平台接口 HTTP ' . $httpCode);
        $response = json_decode((string)$raw, true);
        if (!is_array($response)) throw new CommonException('旧平台未返回有效 JSON');
        if ((int)($response['code'] ?? 0) !== 200) {
            $message = trim((string)($response['msg'] ?? $response['message'] ?? '未知错误'));
            throw new CommonException('旧平台返回失败：' . ($message !== '' ? $message : '未知错误'));
        }
        return $response;
    }
}
