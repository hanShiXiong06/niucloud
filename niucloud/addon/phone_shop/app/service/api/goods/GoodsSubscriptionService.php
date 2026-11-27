<?php

namespace addon\phone_shop\app\service\api\goods;

use addon\phone_shop\app\model\goods\GoodsSubscription;
use addon\phone_shop\app\service\core\goods\CoreGoodsNoticeService;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 会员商品筛选订阅
 */
class GoodsSubscriptionService extends BaseApiService
{
    private const RULE_KEYS = [
        'keyword', 'category_ids', 'memory_group', 'condition_grade', 'device_color',
        'battery_range', 'warranty_range',
        'label_ids', 'service_ids', 'brand_ids', 'start_price', 'end_price',
        'warehouse', 'in_stock', 'arrival_notice',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->model = new GoodsSubscription();
    }

    public function getPage(): array
    {
        $query = $this->model
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'member_id', '=', $this->member_id ],
            ])
            ->field('subscription_id,subscription_name,rule_json,rule_hash,status,match_count,last_notify_time,create_time,update_time')
            ->order('status desc,create_time desc');
        $list = $this->pageQuery($query);
        foreach ($list['data'] as &$item) {
            $item['rule'] = json_decode((string)$item['rule_json'], true) ?: [];
            unset($item['rule']['_weapp_consent']);
            unset($item['rule_json']);
        }
        unset($item);
        return $list;
    }

    public function getStatus(array $rule): array
    {
        $normalized = $this->normalizeRule($rule);
        if (empty($normalized)) return [ 'subscribed' => 0, 'subscription_id' => 0 ];
        $info = $this->model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $this->member_id ],
            [ 'rule_hash', '=', $this->ruleHash($normalized) ],
            [ 'status', '=', 1 ],
        ])->field('subscription_id')->findOrEmpty()->toArray();
        return [
            'subscribed' => empty($info) ? 0 : 1,
            'subscription_id' => (int)($info['subscription_id'] ?? 0),
        ];
    }

    public function add(array $data): int
    {
        $rule = $this->normalizeRule((array)($data['rule'] ?? []));
        if (empty($rule)) throw new CommonException('请至少选择一个订阅条件');

        $hash = $this->ruleHash($rule);
        $existing = $this->model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $this->member_id ],
            [ 'rule_hash', '=', $hash ],
        ])->findOrEmpty();
        $now = time();
        $consent = (new CoreGoodsNoticeService())->consent((int)$this->site_id, (string)$this->channel, (array)($data['authorization'] ?? []));
        if (!empty($rule['arrival_notice']) && empty($consent)) {
            throw new CommonException('请在本站小程序点击订阅并允许微信通知后再保存');
        }
        $storedRule = $rule;
        if ($consent) $storedRule['_weapp_consent'] = $consent;
        $payload = [
            'subscription_name' => trim((string)($data['name'] ?? '')) ?: $this->buildName($rule),
            'rule_json' => json_encode($storedRule, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 1,
            'update_time' => $now,
        ];
        if (!$existing->isEmpty()) {
            $existing->save($payload);
            return (int)$existing->subscription_id;
        }
        $payload += [
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'rule_hash' => $hash,
            'match_count' => 0,
            'last_notify_time' => 0,
            'create_time' => $now,
        ];
        return (int)$this->model->create($payload)->subscription_id;
    }

    public function cancel(array $data): void
    {
        $where = [
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $this->member_id ],
        ];
        if (!empty($data['subscription_id'])) {
            $where[] = [ 'subscription_id', '=', (int)$data['subscription_id'] ];
        } else {
            $rule = $this->normalizeRule((array)($data['rule'] ?? []));
            if (empty($rule)) throw new CommonException('订阅条件不能为空');
            $where[] = [ 'rule_hash', '=', $this->ruleHash($rule) ];
        }
        $this->model->where($where)->update([ 'status' => 0, 'update_time' => time() ]);
    }

    public function normalizeRule(array $rule): array
    {
        $normalized = [];
        foreach (self::RULE_KEYS as $key) {
            if (!array_key_exists($key, $rule)) continue;
            $value = $rule[$key];
            if (in_array($key, [ 'category_ids', 'memory_group', 'condition_grade', 'device_color', 'battery_range', 'warranty_range', 'label_ids', 'service_ids', 'brand_ids' ], true)) {
                $value = is_array($value) ? $value : explode(',', (string)$value);
                $value = array_values(array_unique(array_filter(array_map(static fn($item) => trim((string)$item), $value), static fn($item) => $item !== '')));
                sort($value, SORT_NATURAL);
                if (!empty($value)) $normalized[$key] = $value;
                continue;
            }
            if ($key === 'in_stock' || $key === 'arrival_notice') {
                if (!empty($value)) $normalized[$key] = 1;
                continue;
            }
            $value = trim((string)$value);
            if ($value !== '') $normalized[$key] = $value;
        }
        ksort($normalized);
        return $normalized;
    }

    private function ruleHash(array $rule): string
    {
        return hash('sha256', json_encode($rule, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function buildName(array $rule): string
    {
        if (!empty($rule['arrival_notice'])) return '本站批量上新提醒';
        $parts = [];
        if (!empty($rule['keyword'])) $parts[] = $rule['keyword'];
        if (!empty($rule['memory_group'])) $parts[] = implode('/', $rule['memory_group']);
        if (!empty($rule['condition_grade'])) $parts[] = implode('/', $rule['condition_grade']);
        if (!empty($rule['device_color'])) $parts[] = implode('/', $rule['device_color']);
        if (!empty($rule['battery_range'])) $parts[] = '电池' . implode('/', $rule['battery_range']);
        if (!empty($rule['warranty_range'])) $parts[] = '保修' . implode('/', $rule['warranty_range']);
        return empty($parts) ? '商品到货订阅' : mb_substr(implode(' · ', $parts), 0, 60);
    }
}
