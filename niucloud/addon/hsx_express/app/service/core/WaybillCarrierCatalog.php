<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use core\exception\CommonException;

/** 官方国内网点面单字典快照。业务不依赖文档网站在线，不把目录当作本站授权。 */
final class WaybillCarrierCatalog
{
    public const ACCOUNT_FIELDS = ['partner_id', 'partner_key', 'partner_secret', 'partner_name', 'net', 'code', 'check_man'];
    public const API_FIELDS = ['partner_id' => 'partnerId', 'partner_key' => 'partnerKey', 'partner_secret' => 'partnerSecret',
        'partner_name' => 'partnerName', 'net' => 'net', 'code' => 'code', 'check_man' => 'checkMan'];

    public static function snapshot(): array
    {
        static $data;
        if ($data === null) $data = json_decode(file_get_contents(__DIR__ . '/../../dict/waybill/carriers.json'), true, 512, JSON_THROW_ON_ERROR);
        return $data;
    }

    public static function find(string $code): ?array
    {
        foreach (self::snapshot()['carriers'] as $carrier) if ($carrier['code'] === $code) return $carrier;
        return null;
    }

    public static function unavailableReason(array $carrier): string
    {
        if (!$carrier['account_documented']) return '官方目录未列出该公司的完整账号参数，当前不能安全启用；请联系平台管理员核实并补充参数字典。';
        if (!$carrier['products']) return '官方目录未列出该公司的产品选项，当前不能安全启用；请联系平台管理员核实产品字典。';
        if (!$carrier['capabilities']['label']) return '该公司由快递方打印面单，接口不返回面单文件，不适用本页电脑／云打印方案。';
        return '';
    }

    public static function fields(array $carrier): array
    {
        $fields = [];
        foreach ($carrier['account_fields'] as $key => $label) {
            $fixed = preg_match('/^固定传[:：](.+)$/u', $label, $match) === 1 ? trim($match[1]) : null;
            $fields[] = ['key' => $key, 'api_key' => self::API_FIELDS[$key], 'label' => $fixed === null ? $label : '接口标识',
                'secret' => in_array($key, ['partner_key', 'partner_secret'], true), 'required' => $fixed === null, 'fixed' => $fixed,
                'help' => $fixed === null ? '由' . $carrier['name'] . '签约网点或客户经理提供，须开通快递100网点电子面单权限。' : '按官方字典自动传入，不需要向网点索取或手动填写。'];
        }
        return $fields;
    }

    public static function options(): array
    {
        $result = [];
        foreach (self::snapshot()['carriers'] as $carrier) {
            $reason = self::unavailableReason($carrier);
            $result[] = ['value' => $carrier['code'], 'label' => $carrier['name'], 'fields' => self::fields($carrier),
                'products' => array_map(static fn($value) => ['value' => $value, 'label' => $value], $carrier['products']),
                'pay_types' => self::payTypes($carrier), 'capabilities' => $carrier['capabilities'],
                'available' => $reason === '', 'unavailable_reason' => $reason];
        }
        return $result;
    }

    public static function payTypes(array $carrier): array
    {
        $types = [['value' => 'SHIPPER', 'label' => '寄方付'], ['value' => 'MONTHLY', 'label' => '月结']];
        if ($carrier['capabilities']['consignee'] === true) $types[] = ['value' => 'CONSIGNEE', 'label' => '收方付（到付）'];
        return $types;
    }

    /** 仅发送所选承运商的参数，固定值由字典产生，不能夹带另一家快递的旧凭据。 */
    public static function accountParams(array $config): array
    {
        $carrier = self::find((string)($config['carrier'] ?? ''));
        if (!$carrier) throw new CommonException('未识别电子面单快递公司，请先选择官方目录中的公司');
        $params = [];
        foreach (self::fields($carrier) as $field) {
            $value = $field['fixed'] ?? trim((string)($config[$field['key']] ?? ''));
            if ($value !== '') $params[$field['api_key']] = $value;
        }
        return $params;
    }
}
