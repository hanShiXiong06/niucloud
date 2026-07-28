<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\support;

use core\exception\CommonException;

/**
 * 会员卡权益绑定规则。
 *
 * 卡种仅声明绑定方式；真正的 IMEI/型号在开卡时形成快照，
 * 核销时始终以持卡权益快照校验，避免后续修改卡种影响历史会员卡。
 */
final class MemberCardBinding
{
    public const MEMBER = 'member';
    public const IMEI = 'imei';
    public const MODEL = 'model';

    public static function normalizeMode(mixed $mode): string
    {
        $value = trim((string)$mode);
        return in_array($value, [self::MEMBER, self::IMEI, self::MODEL], true) ? $value : self::MEMBER;
    }

    /** @return array{binding_mode:string,bound_imei:string,bound_model:string} */
    public static function issue(array $item, array $data): array
    {
        $mode = self::normalizeMode($item['binding_mode'] ?? self::MEMBER);
        $imei = self::normalizeImei($data['bind_imei'] ?? '');
        $model = self::normalizeModel($data['bind_model'] ?? '');
        if ($mode === self::IMEI && $imei === '') {
            throw new CommonException('该卡种需要绑定设备，请填写或扫描 IMEI');
        }
        if ($mode === self::MODEL && $model === '') {
            throw new CommonException('该卡种限定产品型号，请填写设备型号');
        }
        return [
            'binding_mode' => $mode,
            'bound_imei' => $mode === self::IMEI ? $imei : '',
            'bound_model' => $mode === self::MEMBER ? '' : $model,
        ];
    }

    /** @return array{binding_mode:string,service_imei:string,service_model:string} */
    public static function redeem(array $item, array $data): array
    {
        $mode = self::normalizeMode($item['binding_mode'] ?? self::MEMBER);
        $imei = self::normalizeImei($data['service_imei'] ?? '');
        $model = self::normalizeModel($data['service_model'] ?? '');
        if ($mode === self::IMEI) {
            if ($imei === '') throw new CommonException('请填写或扫描本次服务设备的 IMEI');
            if (!hash_equals(self::normalizeImei($item['bound_imei'] ?? ''), $imei)) {
                throw new CommonException('当前设备与会员卡绑定的 IMEI 不一致');
            }
        }
        if ($mode === self::MODEL) {
            if ($model === '') throw new CommonException('请填写本次服务设备的型号');
            if (self::comparableModel($item['bound_model'] ?? '') !== self::comparableModel($model)) {
                throw new CommonException('当前设备型号不在该会员卡适用范围内');
            }
        }
        return [
            'binding_mode' => $mode,
            'service_imei' => $mode === self::IMEI ? $imei : '',
            'service_model' => $mode === self::IMEI
                ? self::normalizeModel($item['bound_model'] ?? '')
                : ($mode === self::MODEL ? $model : ''),
        ];
    }

    public static function normalizeImei(mixed $value): string
    {
        $value = strtoupper((string)preg_replace('/\s+/', '', trim((string)$value)));
        if ($value === '') return '';
        if (!preg_match('/^[A-Z0-9\-]{8,40}$/', $value)) {
            throw new CommonException('IMEI/设备编号应为 8 至 40 位字母、数字或短横线');
        }
        return $value;
    }

    public static function normalizeModel(mixed $value): string
    {
        $value = trim((string)preg_replace('/\s+/u', ' ', trim((string)$value)));
        if (mb_strlen($value) > 100) throw new CommonException('设备型号不能超过 100 个字符');
        return $value;
    }

    public static function label(mixed $mode): string
    {
        return match (self::normalizeMode($mode)) {
            self::IMEI => '绑定指定设备',
            self::MODEL => '限定产品型号',
            default => '仅限购卡会员',
        };
    }

    private static function comparableModel(mixed $value): string
    {
        return mb_strtolower(self::normalizeModel($value));
    }
}
