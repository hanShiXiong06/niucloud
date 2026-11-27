<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

use core\exception\CommonException;

/** 标准图只匹配完整 SKU 事实；不猜颜色同义词，也不消费某台机器的普通实拍图。 */
final class ListingMediaContract
{
    public static function brand($value, array $options): array
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$value)) {
            throw new CommonException('商城品牌格式不正确，请重新选择');
        }
        $id = (int)$value;
        if ($id === 0) return ['id' => 0, 'name' => ''];
        foreach ($options as $brand) {
            if ((int)$brand['brand_id'] === $id) return ['id' => $id, 'name' => (string)$brand['brand_name']];
        }
        throw new CommonException('所选商城品牌已删除或不属于本站，请刷新后重新选择');
    }

    /** 只接受本站当前有效标签；不根据名称猜测或自动创建，空数组是明确清空。 */
    public static function labels($values, array $options): array
    {
        if (!is_array($values)) throw new CommonException('商城标签格式不正确，请重新选择');
        $allowed = array_fill_keys(array_map('intval', array_column($options, 'label_id')), true);
        $ids = [];
        foreach ($values as $value) {
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) {
                throw new CommonException('商城标签格式不正确，请重新选择');
            }
            $id = (int)$value;
            if (!isset($allowed[$id])) throw new CommonException('所选商城标签已停用、删除或不属于本站，请刷新后重新选择');
            $ids[] = $id;
        }
        return array_values(array_unique($ids));
    }

    public static function text($value, string $label, int $limit = 50): string
    {
        if (!is_scalar($value) && $value !== null) throw new CommonException($label . '格式不正确');
        $text = trim((string)$value);
        if (mb_strlen($text) > $limit || preg_match('/[\x00-\x1F\x7F]/u', $text)) {
            throw new CommonException($label . '格式不正确或内容过长');
        }
        return $text;
    }

    public static function normalized(string $value, bool $memory = false): string
    {
        $value = mb_strtolower((string)preg_replace('/\s+/u', '', trim($value)));
        return $memory ? str_replace(['gb', 'tb'], ['g', 't'], $value) : $value;
    }

    public static function identity(int $siteId, int $categoryId, string $model, string $memory, string $color): array
    {
        $identity = [
            'site_id' => $siteId, 'category_id' => $categoryId,
            'model' => self::normalized(self::text($model, '设备型号', 255)),
            'memory' => self::normalized(self::text($memory, '容量 / 规格'), true),
            'color' => self::normalized(self::text($color, '颜色')),
        ];
        $missing = [];
        if ($siteId <= 0 || $categoryId <= 0) $missing[] = '商城分类';
        foreach (['model' => '设备型号', 'memory' => '容量 / 规格', 'color' => '颜色'] as $field => $label) {
            if ($identity[$field] === '') $missing[] = $label;
        }
        $key = $missing === [] ? hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : '';
        return ['eligible' => $missing === [], 'key' => $key, 'identity' => $identity, 'missing' => $missing];
    }

    /** 字典存在时唯一匹配字典；空字典允许管理员明确填写，不自动创造字典项。 */
    public static function choice($value, array $options, string $label, bool $required = false): string
    {
        $text = self::text($value, $label);
        if ($text === '') {
            if ($required) throw new CommonException('请先填写或选择' . $label);
            return '';
        }
        $options = array_values(array_unique(array_filter(array_map('trim', $options), static fn(string $item): bool => $item !== '')));
        if ($options === [] || in_array($text, $options, true)) return $text;
        $memory = $label === '容量 / 规格';
        $matches = array_values(array_filter($options, static fn(string $option): bool => self::normalized($option, $memory) === self::normalized($text, $memory)));
        if (count($matches) === 1) return $matches[0];
        throw new CommonException('【' . $label . '】请从当前商城分类的选项中选择，原选项可能已变更，请刷新后重试');
    }

    public static function grade($id, array $grades): array
    {
        if ($id === null || $id === '' || $id === 0 || $id === '0') return [];
        if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string)$id)) {
            throw new CommonException('商品等级格式不正确，请重新选择');
        }
        foreach ($grades as $grade) {
            if ((int)($grade['grade_id'] ?? 0) === (int)$id && (int)($grade['status'] ?? 1) === 1) {
                $name = self::text($grade['grade_name'] ?? '', '商品等级');
                if ($name === '') throw new CommonException('当前商品等级名称为空，请联系管理员核对');
                return ['id' => (int)$id, 'name' => $name];
            }
        }
        throw new CommonException('所选商品等级已停用、删除或不属于本站，请重新选择');
    }

    public static function images($value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            $json = $value !== '' ? json_decode($value, true) : [];
            $value = is_array($json) ? $json : ($value === '' ? [] : explode(',', $value));
        }
        if (!is_array($value)) throw new CommonException('商品图片格式不正确，请重新上传');
        if (count($value) > 9) throw new CommonException('商品图片最多上传 9 张');
        $images = [];
        foreach ($value as $item) {
            if (!is_string($item)) throw new CommonException('商品图片格式不正确，请重新上传');
            $image = trim($item);
            if ($image === '') continue;
            if (strlen($image) > 2000 || preg_match('/[\s\x00-\x1F\x7F\\\\]/u', $image)
                || str_starts_with($image, '//') || preg_match('#(?:^|/)\.\.(?:/|$)#', $image)) {
                throw new CommonException('商品图片地址不正确或过长，请通过上传组件重新选择');
            }
            if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $image)) {
                $url = parse_url($image);
                if (!is_array($url) || !in_array(strtolower((string)($url['scheme'] ?? '')), ['http', 'https'], true)
                    || empty($url['host']) || isset($url['user']) || isset($url['pass'])) {
                    throw new CommonException('商品图片地址不正确，请通过上传组件重新选择');
                }
            } elseif (!preg_match('#^/?[^/?\#][^\x00-\x20]*$#u', $image)) {
                throw new CommonException('商品图片地址不正确，请通过上传组件重新选择');
            }
            $images[] = $image;
        }
        return array_values(array_unique($images));
    }
}
