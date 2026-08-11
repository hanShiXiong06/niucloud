<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\core;

use app\dict\member\MemberAccountTypeDict;
use app\service\core\member\CoreMemberAccountService;
use core\exception\CommonException;

final class MarketingRewardProviderService
{
    public function providers(int $siteId): array
    {
        $providers = [
            ['provider_key' => 'member', 'reward_type' => 'point', 'name' => '会员积分', 'value_label' => '积分数量', 'options' => []],
            ['provider_key' => 'member', 'reward_type' => 'growth', 'name' => '会员成长值', 'value_label' => '成长值', 'options' => []],
        ];
        foreach ((array)event('HsxMarketingRewardProvidersRequested', ['site_id' => $siteId, 'contract_version' => 'v1']) as $result) {
            foreach ($this->rows($result) as $row) $providers[] = $row;
        }
        $unique = [];
        foreach ($providers as $provider) {
            $key = (string)($provider['provider_key'] ?? '') . ':' . (string)($provider['reward_type'] ?? '');
            if ($key !== ':' && !isset($unique[$key])) $unique[$key] = $provider;
        }
        return array_values($unique);
    }

    public function options(int $siteId, string $providerKey, string $rewardType): array
    {
        if ($providerKey === 'member') return [];
        foreach ((array)event('HsxMarketingRewardOptionsRequested', [
            'site_id' => $siteId,
            'provider_key' => $providerKey,
            'reward_type' => $rewardType,
            'contract_version' => 'v1',
        ]) as $result) {
            if (is_array($result) && !empty($result['handled'])) return array_values((array)($result['options'] ?? []));
        }
        return [];
    }

    public function grant(array $order): array
    {
        $provider = (string)$order['provider_key'];
        $type = (string)$order['reward_type'];
        if ($provider === 'member' && in_array($type, ['point', 'growth'], true)) {
            $accountType = $type === 'point' ? MemberAccountTypeDict::POINT : MemberAccountTypeDict::GROWTH;
            $amount = (float)$order['reward_value'] * max(1, (int)$order['reward_quantity']);
            if ($amount <= 0) throw new CommonException('奖励数量必须大于0');
            $logId = (new CoreMemberAccountService())->addLog(
                (int)$order['site_id'],
                (int)$order['member_id'],
                $accountType,
                $amount,
                'hsx_marketing_reward',
                '营销活动奖励：' . (string)$order['reward_name'],
                (int)$order['id']
            );
            return ['handled' => true, 'success' => true, 'provider_no' => (string)$logId, 'expire_at' => 0, 'message' => '奖励已到账'];
        }

        foreach ((array)event('HsxMarketingRewardGrantRequested', [
            'contract_version' => 'v1',
            'request_id' => (string)$order['request_id'],
            'site_id' => (int)$order['site_id'],
            'member_id' => (int)$order['member_id'],
            'provider_key' => $provider,
            'reward_type' => $type,
            'reward_value' => (float)$order['reward_value'],
            'reward_quantity' => (int)$order['reward_quantity'],
            'reward_config' => (array)($order['reward_config_json'] ?? []),
            'source_type' => 'hsx_marketing_reward',
            'source_id' => (int)$order['id'],
            'source_no' => (string)$order['reward_no'],
        ]) as $result) {
            if (is_array($result) && !empty($result['handled'])) return $result;
        }
        throw new CommonException('奖励提供器不可用，请检查对应插件是否安装并启用');
    }

    public function reverse(array $order): array
    {
        $provider = (string)$order['provider_key'];
        $type = (string)$order['reward_type'];
        if ($provider === 'member' && in_array($type, ['point', 'growth'], true)) {
            $accountType = $type === 'point' ? MemberAccountTypeDict::POINT : MemberAccountTypeDict::GROWTH;
            $amount = (float)$order['reward_value'] * max(1, (int)$order['reward_quantity']);
            (new CoreMemberAccountService())->addLog(
                (int)$order['site_id'], (int)$order['member_id'], $accountType, -$amount,
                'hsx_marketing_reward_reversal', '营销事实冲红：' . (string)$order['reward_name'], (int)$order['id']
            );
            return ['handled' => true, 'success' => true, 'message' => '奖励已冲红'];
        }
        foreach ((array)event('HsxMarketingRewardReverseRequested', [
            'contract_version' => 'v1', 'request_id' => (string)$order['request_id'] . ':reverse',
            'site_id' => (int)$order['site_id'], 'member_id' => (int)$order['member_id'],
            'provider_key' => $provider, 'reward_type' => $type,
            'provider_no' => (string)$order['provider_no'],
            'provider_result' => (array)($order['provider_result_json'] ?? []),
            'source_no' => (string)$order['reward_no'],
        ]) as $result) {
            if (is_array($result) && !empty($result['handled'])) return $result;
        }
        return ['handled' => false, 'success' => false, 'message' => '奖励提供器暂不支持自动冲红'];
    }

    private function rows(mixed $result): array
    {
        if (!is_array($result)) return [];
        if (isset($result['provider_key'])) return [$result];
        return array_values(array_filter($result, 'is_array'));
    }
}
