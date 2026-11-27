<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use addon\hsx_express\app\support\Cipher;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

final class ConfigService
{
    public const KEY = 'HSX_EXPRESS_CONFIG';
    public const SECRETS = ['key', 'secret', 'partner_key', 'partner_secret'];
    public const MASK = '******';
    public static function defaults(): array
    {
        return ['provider' => 'kuaidi100', 'enabled' => 0, 'scene' => 'waybill_web', 'key' => '', 'secret' => '',
            'carrier' => '', 'exp_type' => '', 'partner_id' => '', 'partner_key' => '',
            'partner_secret' => '', 'partner_name' => '', 'net' => '', 'code' => '', 'check_man' => '', 'pay_type' => 'MONTHLY', 'template_id' => '',
            'device_id' => '', 'callback_base_url' => '', 'use_ack' => 0];
    }

    public function get(int $siteId, bool $masked = false): array
    {
        $raw = (new CoreConfigService())->getConfigValue($siteId, self::KEY);
        $data = array_replace(self::defaults(), is_array($raw) ? $raw : []);
        foreach (self::SECRETS as $field) {
            $data[$field] = Cipher::decrypt((string)($data[$field . '_cipher'] ?? ''));
            unset($data[$field . '_cipher']);
        }
        if (!$masked) return $data;
        $data['readiness'] = self::readiness($data);
        foreach (self::SECRETS as $field) {
            $data['has_' . $field] = $data[$field] !== '';
            $data[$field] = $data[$field] !== '' ? self::MASK : '';
        }
        $data['options'] = self::options();
        $data['callback_url'] = $data['callback_base_url'] !== '' ? $data['callback_base_url'] . '/api/hsx_express/callback/' . $siteId . '/{task_no}' : '';
        return $data;
    }

    public function save(int $siteId, array $input): array
    {
        if ($siteId <= 0) throw new CommonException('请在客户站点配置本店的物流账号');
        $stored = $this->get($siteId);
        foreach (array_intersect_key($input, self::defaults()) as $value) {
            if (!is_scalar($value) && $value !== null) throw new CommonException('配置字段必须为文本或开关值');
            if (is_string($value) && mb_strlen($value) > 500) throw new CommonException('物流配置字段过长，请核对填写内容');
        }
        $carrierChanged = array_key_exists('carrier', $input) && trim((string)$input['carrier']) !== $stored['carrier'];
        $base = $stored;
        if ($carrierChanged) {
            foreach (array_merge(WaybillCarrierCatalog::ACCOUNT_FIELDS, ['exp_type', 'template_id', 'pay_type', 'use_ack']) as $field) $base[$field] = self::defaults()[$field];
        }
        $data = array_replace($base, array_intersect_key($input, self::defaults()));
        foreach (self::SECRETS as $field) {
            $value = trim((string)($input[$field] ?? ''));
            $data[$field] = in_array($field, (array)($input['clear_secrets'] ?? []), true) ? '' : (($value === '' || $value === self::MASK) ? $base[$field] : $value);
        }
        foreach ($data as $key => $value) if (is_string($value)) $data[$key] = trim($value);
        $data['provider'] = 'kuaidi100';
        $data['enabled'] = (int)!empty($data['enabled']);
        $data['use_ack'] = (int)!empty($data['use_ack']);
        $data['callback_base_url'] = rtrim($data['callback_base_url'], '/');
        if (!in_array($data['scene'], ['waybill_web', 'waybill_cloud'], true)) throw new CommonException('请选择网页打印或云打印方案');
        $carrier = WaybillCarrierCatalog::find($data['carrier']);
        if ($data['carrier'] !== '' && !$carrier) throw new CommonException('请选择官方电子面单目录中的快递公司');
        // 固定接口标识由字典维护；清理该公司不使用的旧参数。
        foreach (WaybillCarrierCatalog::ACCOUNT_FIELDS as $field) {
            if (!isset($carrier['account_fields'][$field])) $data[$field] = '';
        }
        if ($carrier) foreach (WaybillCarrierCatalog::fields($carrier) as $field) if ($field['fixed'] !== null) $data[$field['key']] = $field['fixed'];
        if (!in_array($data['pay_type'], ['SHIPPER', 'CONSIGNEE', 'MONTHLY'], true)) throw new CommonException('请选择寄付、到付或月结');
        if ($data['callback_base_url'] !== '' && !self::validBaseUrl($data['callback_base_url'])) throw new CommonException('回调根地址请填写公网 HTTPS 域名，不带路径、参数、账号或端口');
        if ($data['enabled'] && !self::readiness($data)['ready']) throw new CommonException('暂不能启用：' . implode('；', array_column(array_filter(self::readiness($data)['checks'], static fn($row) => !$row['passed']), 'message')));
        foreach (self::SECRETS as $field) {
            $data[$field . '_cipher'] = Cipher::encrypt($data[$field]);
            unset($data[$field]);
        }
        (new CoreConfigService())->setConfig($siteId, self::KEY, $data);
        return $this->get($siteId, true);
    }

    public static function readiness(array $data): array
    {
        $checks = [];
        $add = static function ($key, $label, $passed, $message) use (&$checks) { $checks[] = compact('key', 'label', 'passed', 'message'); };
        $add('cipher', '部署密钥', Cipher::available(), '平台管理员需先配置 app.auth_key，保护物流凭据');
        $add('credentials', '快递100账号', !empty($data['key']) && !empty($data['secret']), '到快递100企业管理后台复制授权 Key 和 Secret');
        $carrier = WaybillCarrierCatalog::find((string)($data['carrier'] ?? ''));
        $reason = $carrier ? WaybillCarrierCatalog::unavailableReason($carrier) : '请先选择本站已经开通电子面单的快递公司';
        $add('carrier', '快递公司', $reason === '', $reason ?: '按所选公司的官方字典检查，不要求提供其他公司的账号');
        if ($carrier) {
            foreach (WaybillCarrierCatalog::fields($carrier) as $field) {
                if ($field['fixed'] !== null) continue;
                $add('account_' . $field['key'], $carrier['name'] . ' · ' . $field['label'], trim((string)($data[$field['key']] ?? '')) !== '', '请填写' . $carrier['name'] . '的' . $field['label'] . '，由签约网点或客户经理提供');
            }
        }
        $add('template', 'V2面单模板', !empty($data['template_id']), '到快递100快递公司模板 V2 页面选择对应公司模板并复制模板 ID');
        $products = $carrier['products'] ?? [];
        $add('product', '快递产品', in_array($data['exp_type'] ?? '', $products, true), '请选择本页面提供的产品预设，并确认账号已开通');
        $add('pay_type', '运费付款方式', $carrier && in_array($data['pay_type'] ?? '', array_column(WaybillCarrierCatalog::payTypes($carrier), 'value'), true), '请选择当前快递支持且本站账号已开通的运费付款方式');
        $add('confirmation', '开通确认', !empty($data['use_ack']), '请确认账号、所选快递产品和付款方式已获服务商开通');
        if (($data['scene'] ?? '') === 'waybill_cloud') {
            $add('device', '云打印设备', !empty($data['device_id']), '填写已绑定该快递100账号的云打印设备码');
            $add('callback', '公网打印回调', self::validBaseUrl((string)($data['callback_base_url'] ?? '')), '云打印必须填写可从公网访问的 HTTPS 站点根地址');
        }
        $ready = count(array_filter($checks, static fn($row) => !$row['passed'])) === 0;
        return ['ready' => $ready, 'checks' => $checks, 'external_verified' => false,
            'message' => $ready ? '本地配置项齐全；尚未验证额度、产品权限、网点范围或真实打印，需首单验收。' : '先补齐下列配置。本检查不会下单、扣费或打印。'];
    }

    public static function validBaseUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host']) && strpos($parts['host'], '.') !== false
            && !filter_var($parts['host'], FILTER_VALIDATE_IP) && !preg_match('/(?:^localhost$|\.local$|\.internal$)/i', $parts['host'])
            && empty($parts['user']) && empty($parts['pass']) && empty($parts['port']) && empty($parts['query']) && empty($parts['fragment'])
            && empty($parts['path']) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function options(): array
    {
        return [
            'scenes' => [['value' => 'waybill_web', 'label' => '先跑通：网页面单 + 本地打印机'], ['value' => 'waybill_cloud', 'label' => '门店提效：快递100云打印机']],
            'carriers' => WaybillCarrierCatalog::options(),
            'catalog' => ['source' => WaybillCarrierCatalog::snapshot()['source'], 'checked_at' => WaybillCarrierCatalog::snapshot()['checked_at'], 'scope' => WaybillCarrierCatalog::snapshot()['scope']],
            'pay_types' => [['value' => 'MONTHLY', 'label' => '月结'], ['value' => 'SHIPPER', 'label' => '寄方付'], ['value' => 'CONSIGNEE', 'label' => '收方付（到付）']],
            'product_notice' => '公司、产品及账号字段来自快递100官方国内网点面单字典，不是本站已开通清单；参数不全或不返回面单的公司会明确提示，不能冒充已支持。',
            'docs' => ['api' => 'https://api.kuaidi100.com/document/dianzimiandanV2', 'dictionary' => 'https://api.kuaidi100.com/document/5f0ff6e82977d50a94e10237', 'console' => 'https://api.kuaidi100.com/'],
        ];
    }
}
