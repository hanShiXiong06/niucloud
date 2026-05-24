<?php

namespace addon\hsx_phone_query\app\dict;

/**
 * 第三方查询结果展示字典。
 *
 * 原始 info 保持不变，本字典只负责把字段和值转换成用户可读的中文报告。
 */
class HsxPhoneQueryResultDict
{
    public static function fieldLabels(): array
    {
        return [
            'imei' => 'IMEI',
            'imei2' => 'IMEI2',
            'sn' => '序列号',
            'type' => '查询类型',
            'model' => '设备型号',
            'identifier' => '设备标识',
            'description' => '型号代码',
            'configuration' => '配置信息',
            'capacity' => '容量',
            'color' => '颜色',
            'color-en' => '颜色英文',
            'activated' => '激活状态',
            'activation' => '激活信息',
            'activation.date' => '激活日期',
            'purchase' => '购买信息',
            'purchase.date' => '购买日期',
            'purchase.country' => '购买地区',
            'purchase.validated' => '购买日期验证',
            'coverage' => '保修信息',
            'coverage.status' => '保修状态',
            'coverage.description' => '保修说明',
            'coverage.date' => '保修到期',
            'coverage.days-remaining' => '剩余保修天数',
            'coverage.unable' => '保修查询状态',
            'warranty' => '保修状态',
            'support' => '技术支持',
            'applecare' => 'AppleCare+',
            'applecare-eligible' => '是否可购买 AppleCare+',
            'brightstar' => 'Brightstar 设备',
            'replaced' => '是否已更换',
            'pre-activated' => '预激活状态',
            'loaner' => '是否借用机',
            'repair' => '维修状态',
            'manufacture' => '生产信息',
            'manufacture.date' => '生产日期',
            'manufacturer' => '制造商',
            'refurbished' => '是否翻新',
            'demo' => '是否演示机',
            'activationlock' => '查找设备锁',
            'activationlock.locked' => '查找设备锁',
            'activationlock.lost' => '丢失模式',
            'activationlock.phone' => '绑定手机',
            'activationlock.email' => '绑定邮箱',
            'activationlock.message.phone' => '锁定联系电话',
            'activationlock.message.content' => '锁定留言',
            'fmi' => '查找我的 iPhone',
            'locked' => '激活锁状态',
            'state' => '状态',
            'status' => '状态',
            'icloud' => 'ID状态',
            'simlock' => '网络锁',
            'carrier' => '运营商',
            'country' => '销售地',
            'mdm' => '监管锁',
            'blacklist' => '黑名单',
            'image' => '设备图片',
            'img' => '设备图片',
            'picture' => '设备图片',
            'pic' => '设备图片',
            'product_image' => '设备图片',
        ];
    }

    public static function titleKeys(): array
    {
        return ['model', '机型', '型号', 'device_model', 'product', 'product_name', 'configuration'];
    }

    public static function capacityKeys(): array
    {
        return ['capacity', '容量', 'storage'];
    }

    public static function colorKeys(): array
    {
        return ['color', '颜色'];
    }

    public static function imageKeys(): array
    {
        return ['image', 'img', 'picture', 'pic', 'product_image'];
    }

    public static function summaryPaths(): array
    {
        return [
            'capacity',
            'color',
            'activated',
            'activation.date',
            'purchase.date',
            'coverage.status',
            'coverage.date',
            'activationlock.locked',
        ];
    }

    public static function hiddenDetailPaths(): array
    {
        return [
            'image',
            'img',
            'picture',
            'pic',
            'product_image',
        ];
    }

    public static function countryLabels(): array
    {
        return [
            'China' => '中国',
            'CN' => '中国',
            'Hong Kong' => '中国香港',
            'HK' => '中国香港',
            'Taiwan' => '中国台湾',
            'TW' => '中国台湾',
            'Japan' => '日本',
            'JP' => '日本',
            'United States' => '美国',
            'USA' => '美国',
            'US' => '美国',
            'Korea' => '韩国',
            'KR' => '韩国',
            'India' => '印度',
            'IN' => '印度',
        ];
    }

    public static function valueLabels(): array
    {
        return [
            'Yes' => '是',
            'No' => '否',
            'YES' => '是',
            'NO' => '否',
            'Y' => '是',
            'N' => '否',
            'Clean' => '正常',
            'Lost' => '丢失',
            'Blacklisted' => '黑名单',
            'Out Of Warranty' => '已过保',
            'Limited Warranty' => '保修中',
            'Coverage Expired' => '保修已过期',
            'Expired' => '已过期',
            'Active' => '有效',
            'Inactive' => '未激活',
            'ON' => '开启',
            'OFF' => '关闭',
            'On' => '开启',
            'Off' => '关闭',
        ];
    }

    public static function formatValue(string $path, $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return self::formatBooleanValue($path, $value);
        }

        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            return '';
        }

        if (in_array($path, ['activated'], true)) {
            return in_array(strtolower($stringValue), ['1', 'true', 'yes', 'active'], true) ? '已激活' : '未激活';
        }

        if (in_array($path, ['coverage.unable'], true)) {
            return in_array(strtolower($stringValue), ['1', 'true', 'yes'], true) ? '暂不可查' : '可查询';
        }

        if (in_array($path, ['activationlock.locked'], true)) {
            return in_array(strtolower($stringValue), ['1', 'true', 'yes', 'on'], true) ? '已开启' : '未开启';
        }

        if (in_array($path, ['fmi', 'locked'], true)) {
            return in_array(strtolower($stringValue), ['1', 'true', 'yes', 'on', 'off', '0', 'false', 'no'], true)
                ? (in_array(strtolower($stringValue), ['1', 'true', 'yes', 'on'], true) ? '已开启' : '未开启')
                : self::valueLabels()[$stringValue] ?? $stringValue;
        }

        if (in_array($path, ['state', 'status'], true)) {
            if (in_array(strtolower($stringValue), ['none', 'null', 'n/a', '-'], true)) {
                return '无状态';
            }
        }

        if (in_array($path, ['activationlock.lost'], true)) {
            return in_array(strtolower($stringValue), ['1', 'true', 'yes', 'lost'], true) ? '丢失模式' : '正常';
        }

        if (in_array($path, ['refurbished', 'demo', 'applecare', 'replaced', 'purchase.validated'], true)) {
            return in_array(strtolower($stringValue), ['1', 'true', 'yes'], true) ? '是' : '否';
        }

        if (in_array($path, ['purchase.country', 'country'], true)) {
            return self::countryLabels()[$stringValue] ?? $stringValue;
        }

        return self::valueLabels()[$stringValue] ?? $stringValue;
    }

    private static function formatBooleanValue(string $path, bool $value): string
    {
        return match ($path) {
            'activated' => $value ? '已激活' : '未激活',
            'coverage.unable' => $value ? '暂不可查' : '可查询',
            'activationlock.locked' => $value ? '已开启' : '未开启',
            'activationlock.lost' => $value ? '丢失模式' : '正常',
            'fmi', 'locked' => $value ? '已开启' : '未开启',
            'state', 'status' => '无状态',
            'refurbished', 'demo', 'applecare', 'replaced', 'purchase.validated' => $value ? '是' : '否',
            default => $value ? '是' : '否',
        };
    }
}
