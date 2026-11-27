<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\support\{Data,PhpRuntime};
use addon\hsx_lead\app\service\virtualpay\VirtualPaySettings;
use app\service\core\weapp\CoreWeappConfigService;
use core\exception\CommonException;
use think\facade\Db;

/** External SKU links consume native member benefits; they do not define memberships. */
final class MembershipVirtualProductService
{
    /** Called by the existing PayType dictionary when the native channel is saved. */
    public static function normalizeConfig(int $site, string $channel, array $input, array $previous): array
    {
        if ($site <= 0 || $channel !== 'weapp')throw new CommonException('会员微信商品关联仅支持本站小程序');
        $products = $input['products'] ?? [];
        if (!is_array($products) || !PhpRuntime::isList($products) || count($products) > 300)throw new CommonException('会员微信商品关联最多支持300项');
        $old = is_array($previous['products'] ?? null) ? $previous['products'] : [];
        // Message credentials belong to the native miniapp, not this payment channel.
        // Preserve dormant historical copies, but never accept a second source from this form.
        foreach (['callback_token', 'encoding_aes_key'] as $key) {
            unset($input[$key]);
            if (array_key_exists($key, $previous))$input[$key] = $previous[$key];
        }
        if (!$products) {
            $input['products'] = self::retainSpecial([], $old, []);
            self::assertActiveIdentitySafety($site, $input);
            return $input;
        }
        $catalog = self::catalog(MembershipCatalogService::levels($site));
        $result = [];
        $seen = [];
        $ids = [];
        foreach ($products as $row) {
            if (!is_array($row))throw new CommonException('会员微信商品关联格式无效');
            foreach (['level_code', 'cycle', 'product_id'] as $field)if (isset($row[$field]) && !is_string($row[$field]))throw new CommonException('会员微信商品关联标识请使用字符串');
            $on = self::flag($row['enabled'] ?? false);
            $source = $row['source'] ?? null;
            if ($source !== null && !in_array($source, ['catalog', 'special_quote', 'order_quote'], true))throw new CommonException('会员微信商品关联来源无效');
            if (!$on && empty($row['level_code']) && empty($row['cycle']) && empty($row['product_id'])) {
                unset($row['confirm_price_change']);
                $result[] = $row;
                continue;
            }
            if ($source === 'order_quote') {
                $prior = null;
                foreach ($old as $candidate)if (is_array($candidate) && ($candidate['source'] ?? '') === 'order_quote'
                    && ($candidate['order_id'] ?? null) === ($row['order_id'] ?? null)
                    && self::same(array_replace($row, ['enabled' => $candidate['enabled'] ?? false]), $candidate)) {
                    $prior = $candidate;
                    break;
                }
                if ($prior !== null)$row = array_replace($prior, ['enabled' => $on]);
                else $row = self::fromOrder($site, $row);
                self::assertUnique($row, $seen, $ids);
                $result[] = $row;
                continue;
            }
            $key = self::key($row);
            $tier = $catalog[$key] ?? null;
            $unchanged = self::findPrevious($row, $old);
            // Historical unmatched quotations are preserved, never invented by a config POST.
            $special = $source === 'special_quote' || ($source === null && (!$tier || self::centsOrZero($row['amount_cents'] ?? null) !== $tier['amount_cents']));
            if ($special) {
                if ($unchanged === null)throw new CommonException('特殊报价关联只能沿用已有记录，请使用现有会员套餐关联微信商品');
                $row = $unchanged;
                unset($row['confirm_price_change']);
            } else {
                if (!$tier)throw new CommonException('会员等级或周期已停用，请重新读取会员权益');
                $id = (string) ($row['product_id'] ?? '');
                if ($id !== '')self::assertId($id);
                if ($on && $id === '')throw new CommonException('请填写微信后台已创建的商品 ID');
                if (self::centsOrZero($row['amount_cents'] ?? null) !== $tier['amount_cents'])throw new CommonException('会员价格已更新，请重新读取会员权益后再保存关联');
                $changed = false;
                $originalAmount = $tier['amount_cents'];
                foreach ($old as $prior) {
                    if (!is_array($prior) || self::key($prior) !== $key || ($prior['source'] ?? '') === 'special_quote')continue;
                    // No-source historical rows at other amounts may be genuine upgrade quotes.
                    if (($prior['source'] ?? '') !== 'catalog')continue;
                    if ((string) ($prior['product_id'] ?? '') === $id && self::centsOrZero($prior['amount_cents'] ?? null) !== $tier['amount_cents']) {
                        $changed = true;
                        $originalAmount = $prior['amount_cents'];
                    }
                }
                $confirmed = self::flag($row['confirm_price_change'] ?? false);
                if ($changed && $on && !$confirmed)throw new CommonException('会员价格已变化，请先在微信核对商品价格，再确认重新关联');
                // The client amount is a display snapshot only. Re-read native benefits on save.
                $row = ['source' => 'catalog', 'level_code' => $tier['level_code'], 'cycle' => $tier['cycle'],
                    'product_id' => $id, 'amount_cents' => $changed && !$confirmed ? $originalAmount : $tier['amount_cents'], 'enabled' => $on];
            }
            self::assertUnique($row, $seen, $ids);
            $result[] = $row;
        }
        // A normal settings edit cannot silently erase exceptional historical SKU links.
        $input['products'] = self::retainSpecial($result, $old, $catalog);
        if (count($input['products']) > 300)throw new CommonException('会员微信商品关联超过300项，请管理员核对');
        self::assertActiveIdentitySafety($site, $input);
        return $input;
    }

    /** Frozen business quote -> one provider SKU. Never infer entitlement from the SKU name. */
    public static function product(array $order, array $settings): array
    {
        if (($order['purpose'] ?? '') !== 'membership')throw new CommonException('此业务不使用会员微信支付通道');
        $rule = Data::decode($order['rule_json'] ?? '');
        EntityMembershipService::assertSnapshot($order, $rule);
        MembershipCostService::assertSnapshot($order, $rule);
        if (Rules::cents($rule['deposit_amount']) > 0 && empty($settings['allow_deposit']))throw new CommonException('本套餐含押金，当前微信支付商品范围尚不支持此项收费');
        if (Rules::cents($rule['experience_amount']) > 0 && empty($settings['allow_experience']))throw new CommonException('本套餐含经验费用，当前微信支付商品范围尚不支持此项收费');
        $amount = Rules::cents((string) $order['amount']);
        if ($amount <= 0)throw new CommonException('本单无需微信扣款或金额无效');
        $products = $settings['products'] ?? [];
        if (!is_array($products) || !PhpRuntime::isList($products) || count($products) > 300)throw new CommonException('会员微信商品关联数量异常，请平台核对');
        $matches = [];
        foreach ($products as $row) {
            if (!is_array($row) || !self::flag($row['enabled'] ?? false))continue;
            $cents = self::centsOrZero($row['amount_cents'] ?? null);
            if ($cents <= 0)throw new CommonException('微信商品价格快照必须为有效的整数分');
            self::assertId($row['product_id'] ?? null);
            if (($row['level_code'] ?? '') === ($rule['level_code'] ?? '') && ($row['cycle'] ?? '') === ($rule['cycle'] ?? '') && $cents === $amount) {
                $matches[] = ['product_id' => $row['product_id'], 'amount_cents' => $amount, 'quantity' => 1];
            }
        }
        if (count($matches) !== 1)throw new CommonException(count($matches) > 1 ? '当前会员微信商品对应不唯一，请联系平台核对' : '本次套餐或补差金额尚未关联微信商品，请联系平台核对');
        return $matches[0];
    }

    /** Display rows and prices always come from the native member-level benefits. */
    private static function catalog(array $levels): array
    {
        $out = [];
        foreach ($levels as $level)foreach ($level['cycles'] ?? [] as $cycle) {
            if (!self::flag($cycle['enabled'] ?? false))continue;
            $row = ['level_code' => $level['code'], 'cycle' => $cycle['key'], 'amount_cents' => Rules::cents($cycle['price'])];
            $out[self::key($row)] = $row;
        }
        return $out;
    }
    /** Shared miniapp catalog and unresolved original orders must not silently change price. */
    private static function assertActiveIdentitySafety(int $site, array $config): void
    {
        if (($config['payment_mode'] ?? 'standard') !== 'virtual')return;
        $active = [];
        foreach ($config['products'] ?? [] as $row)if (is_array($row) && self::flag($row['enabled'] ?? false))$active[$row['product_id']] = self::centsOrZero($row['amount_cents'] ?? null);
        if (!$active)return;
        $native = new CoreWeappConfigService();
        $app = (string) ($native->getWeappConfig($site)['app_id'] ?? '');
        if ($app === '')return;
        $offer = (string) ($config['offer_id'] ?? '');
        $env = (int) ($config['env'] ?? 0);
        foreach (Db::name('pay_channel')->where('channel', 'weapp')->where('type', VirtualPaySettings::TYPE)->where('status', 1)->where('site_id', '<>', $site)->field('site_id,config')->select()->toArray() as $other) {
            $raw = Data::decode($other['config']);
            if (($raw['payment_mode'] ?? 'standard') !== 'virtual' || (string) ($raw['offer_id'] ?? '') !== $offer || (int) ($raw['env'] ?? 0) !== $env)continue;
            if ((string) ($native->getWeappConfig((int) $other['site_id'])['app_id'] ?? '') !== $app)continue;
            foreach ($raw['products'] ?? [] as $row) {
                if (!is_array($row) || !self::flag($row['enabled'] ?? false) || !is_string($row['product_id'] ?? null) || !isset($active[$row['product_id']]))continue;
                if (self::centsOrZero($row['amount_cents'] ?? null) !== $active[$row['product_id']])throw new CommonException('同一小程序商品ID已在其他站点使用不同价格，请管理员核对；未保存');
            }
        }
        try {
            $pending = Db::name('hsx_lead_virtual_order')->where('app_id', $app)->where('offer_id', $offer)->where('env', $env)->whereIn('product_id', array_keys($active))->whereIn('status', ['prepared', 'pending', 'attention'])->field('product_id,amount_cents')->select()->toArray();
        } catch (\Throwable $error) {
            throw new CommonException('请先完成虚拟支付表结构检查，再启用微信商品关联；当前配置未保存');
        }
        foreach ($pending as $row)if ((int) $row['amount_cents'] !== $active[$row['product_id']])throw new CommonException('该商品还有旧价格的支付结果待核对，不能覆盖商品价格；请先处理原单');
    }
    private static function retainSpecial(array $rows, array $old, array $catalog): array
    {
        $seen = [];
        $ids = [];
        foreach ($rows as $row)self::assertUnique($row, $seen, $ids);
        foreach ($old as $prior) {
            if (!is_array($prior))continue;
            $tier = $catalog[self::key($prior)] ?? null;
            $special = in_array($prior['source'] ?? '', ['special_quote', 'order_quote'], true) || (!isset($prior['source']) && (!$tier || self::centsOrZero($prior['amount_cents'] ?? null) !== $tier['amount_cents']));
            if (!$special)continue;
            $found = false;
            foreach ($rows as $row) {
                // Every new order_quote row has already been re-read from its local business
                // order. An explicit edit replaces that quote link, not the frozen trades.
                $sameQuote = ($prior['source'] ?? '') === 'order_quote' && ($row['source'] ?? '') === 'order_quote'
                    && ($row['order_id'] ?? null) === ($prior['order_id'] ?? null);
                if (self::same($row, $prior) || $sameQuote) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                self::assertUnique($prior, $seen, $ids);
                $rows[] = $prior;
            }
        }
        return $rows;
    }
    private static function findPrevious(array $row, array $old): ?array
    {
        foreach ($old as $prior)if (is_array($prior) && self::same($row, $prior))return $prior;
        return null;
    }
    /** Upgrade/deposit totals come from a real frozen business quote, not a second price input. */
    private static function fromOrder(int $site, array $input): array
    {
        $id = $input['order_id'] ?? null;
        if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $id))throw new CommonException('请先选择本站已有的会员支付订单');
        $order = Store::row('coop_order', $site, (int) $id);
        if (($order['purpose'] ?? '') !== 'membership')throw new CommonException('只能关联会员订单的冻结报价，不能使用成交协作费');
        $rule = Data::decode($order['rule_json'] ?? '');
        EntityMembershipService::assertSnapshot($order, $rule);
        MembershipCostService::assertSnapshot($order, $rule);
        $amount = Rules::cents((string) $order['amount']);
        if ($amount <= 0 || self::centsOrZero($input['amount_cents'] ?? null) !== $amount)throw new CommonException('原会员订单金额已变化，请重新选择订单后核对');
        self::assertId($input['product_id'] ?? null);
        return ['source' => 'order_quote', 'level_code' => $rule['level_code'], 'cycle' => $rule['cycle'],
            'product_id' => $input['product_id'], 'amount_cents' => $amount, 'enabled' => self::flag($input['enabled'] ?? false), 'order_id' => (int) $id];
    }
    private static function same(array $a, array $b): bool
    {
        return self::key($a) === self::key($b) && (string) ($a['product_id'] ?? '') === (string) ($b['product_id'] ?? '')
            && ($a['amount_cents'] ?? null) === ($b['amount_cents'] ?? null) && self::flag($a['enabled'] ?? false) === self::flag($b['enabled'] ?? false);
    }
    private static function key(array $row): string
    {
        $values = [];
        foreach (['level_code', 'cycle'] as $field) {
            $value = $row[$field] ?? '';
            if (!is_string($value) || strlen($value) > 32 || ($value !== '' && !preg_match('/^[A-Za-z0-9_-]+$/D', $value)))throw new CommonException('会员等级或周期标识格式无效');
            $values[] = $value;
        }
        return json_encode($values, JSON_UNESCAPED_UNICODE);
    }
    private static function assertUnique(array $row, array &$seen, array &$ids): void
    {
        if (!self::flag($row['enabled'] ?? false))return;
        self::assertId($row['product_id'] ?? null);
        $cents = self::centsOrZero($row['amount_cents'] ?? null);
        if ($cents <= 0)throw new CommonException('微信商品价格快照无效');
        $key = self::key($row).':'.$cents;
        $id = $row['product_id'];
        if (isset($seen[$key]))throw new CommonException('同一会员等级、周期和价格只能关联一个微信商品');
        if (isset($ids[$id]))throw new CommonException('同一微信商品 ID 不能重复关联多个会员套餐或价格');
        $seen[$key] = true;
        $ids[$id] = true;
    }
    private static function assertId($id): void
    {
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_]{1,20}$/D', $id))throw new CommonException('微信商品 ID 仅支持20位以内的字母、数字和下划线，不支持短横线');
    }
    private static function centsOrZero($value): int
    {
        return (is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value) ? (int) $value : 0;
    }
    private static function flag($value): bool
    {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true))throw new CommonException('微信商品关联开关无效');
        return in_array($value, [true, 1, '1'], true);
    }
}
