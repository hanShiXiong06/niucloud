<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

use InvalidArgumentException;

/** 纯计算：所有加价都只基于输入的最低售价，绝不在上一次零售价上再次加价。 */
final class TierPriceRule
{
    public static function cents($value): int
    {
        if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || (float)$value > 99999999) {
            throw new InvalidArgumentException('价格或加价值必须是 0 到 99999999 的数字');
        }
        return (int)round((float)$value * 100);
    }

    public static function normalize(array $config, array $levels): array
    {
        $enabled = (int)($config['enabled'] ?? 0) === 1;
        $baseNo = (int)($config['base_level_no'] ?? 0);
        $map = array_column($levels, null, 'level_no');
        if ($enabled && (!isset($map[$baseNo]) || $baseNo <= 0)) throw new InvalidArgumentException('请先选择本站最高等级会员作为基准');
        if ($enabled && (int)$map[$baseNo]['growth'] < max(array_column($levels, 'growth'))) {
            throw new InvalidArgumentException('基准等级必须是本站成长值门槛最高的会员等级');
        }
        $rules = [];
        foreach (array_merge([['level_no' => 0]], $levels) as $level) {
            $no = (int)$level['level_no'];
            $raw = (array)($config['rules'][(string)$no] ?? []);
            $rule = self::markup($raw);
            $rule['bands'] = [];
            foreach ((array)($raw['bands'] ?? []) as $band) {
                $min = self::cents($band['min'] ?? 0);
                $max = ($band['max'] ?? '') === '' || $band['max'] === null ? null : self::cents($band['max']);
                if ($max !== null && $max <= $min) throw new InvalidArgumentException('价格区间上限必须大于下限');
                $rule['bands'][] = array_merge(self::markup($band), ['min' => $min / 100, 'max' => $max === null ? null : $max / 100]);
            }
            usort($rule['bands'], static fn($a, $b) => $a['min'] <=> $b['min']);
            $last = -1;
            foreach ($rule['bands'] as $band) {
                if ($band['min'] < $last) throw new InvalidArgumentException('同一身份的价格区间不能重叠');
                $last = $band['max'] ?? INF;
            }
            $rules[(string)$no] = $no === $baseNo ? ['type' => 'fixed', 'value' => 0, 'bands' => []] : $rule;
        }
        return ['enabled' => $enabled ? 1 : 0, 'base_level_no' => $baseNo, 'rules' => $rules];
    }

    private static function markup(array $rule): array
    {
        $type = (string)($rule['type'] ?? 'fixed');
        if (!in_array($type, ['fixed', 'percent'], true)) throw new InvalidArgumentException('加价方式只能选择固定金额或百分比');
        return ['type' => $type, 'value' => self::cents($rule['value'] ?? 0) / 100];
    }

    public static function quote($base, array $config, array $levels): array
    {
        $config = self::normalize($config, $levels);
        $cents = self::cents($base);
        if ($cents <= 0) throw new InvalidArgumentException('基准售价必须大于 0');
        if (!$config['enabled']) return ['enabled' => 0, 'base_price' => $cents / 100, 'retail_price' => $cents / 100, 'member_price' => [], 'prices' => []];
        $prices = [];
        $memberPrices = [];
        foreach (array_merge([['level_no' => 0, 'level_name' => '普通客户', 'growth' => -1]], $levels) as $level) {
            $no = (int)$level['level_no'];
            $rule = $config['rules'][(string)$no];
            foreach ($rule['bands'] as $band) {
                if ($cents >= self::cents($band['min']) && ($band['max'] === null || $cents < self::cents($band['max']))) { $rule = $band; break; }
            }
            $extra = $rule['type'] === 'percent' ? (int)round($cents * self::cents($rule['value']) / 10000) : self::cents($rule['value']);
            $amount = $cents + $extra;
            if ($amount > 9999999900) throw new InvalidArgumentException('计算后的售价超出范围');
            $prices[] = ['level_no' => $no, 'name' => $level['level_name'], 'growth' => (int)$level['growth'], 'price' => number_format($amount / 100, 2, '.', '')];
            if ($no > 0) $memberPrices['level_' . $no] = number_format($amount / 100, 2, '.', '');
        }
        // 不允许普通客户反而比会员便宜，或高等级会员比低等级贵。
        foreach ($prices as $a) foreach ($prices as $b) {
            if ($a['growth'] < $b['growth'] && (float)$a['price'] < (float)$b['price']) {
                throw new InvalidArgumentException($a['name'] . '价格不能低于更高等级的' . $b['name'] . '，请检查加价规则');
            }
        }
        return ['enabled' => 1, 'base_level_no' => $config['base_level_no'], 'base_price' => $cents / 100,
            'retail_price' => (float)$prices[0]['price'], 'member_price' => $memberPrices, 'prices' => $prices];
    }
}
