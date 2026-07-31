<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

/**
 * 销售资料表单契约。
 *
 * 这里是 PC、移动端和后端校验共同消费的唯一字段定义。业务页面不再自行猜测
 * 当前岗位应该看到什么、修改什么、提交什么。
 */
final class ErpListingFormContract
{
    public const ACTION_ONE_STOP = 'one_stop';
    public const ACTION_PHOTO = 'photo';
    public const ACTION_PRICE = 'price';
    public const ACTION_MEDIA_PRICE = 'media_price';
    public const ACTION_MATERIAL = 'material';

    private const FIELD_DEFINITIONS = [
        'catalog_product_id' => ['label' => '商品目录型号', 'group' => 'material', 'default_enabled' => 1, 'default_required' => 1],
        'spec' => ['label' => '设备规格', 'group' => 'material', 'default_enabled' => 1, 'default_required' => 1],
        'image_urls' => ['label' => '商品图片', 'group' => 'media', 'default_enabled' => 1, 'default_required' => 1],
        'video_url' => ['label' => '展示视频', 'group' => 'media', 'default_enabled' => 1, 'default_required' => 0],
        'retail_price' => ['label' => '销售价格', 'group' => 'price', 'default_enabled' => 1, 'default_required' => 1],
        'quality_remark' => ['label' => '质检备注', 'group' => 'media', 'default_enabled' => 1, 'default_required' => 0],
        'remark_public' => ['label' => '对外说明', 'group' => 'material', 'default_enabled' => 1, 'default_required' => 0],
        'remark_internal' => ['label' => '对内备注', 'group' => 'internal', 'default_enabled' => 1, 'default_required' => 0],
    ];

    public static function normalizeRules(array $rules): array
    {
        $normalized = [];
        foreach (self::FIELD_DEFINITIONS as $field => $definition) {
            $stored = (array)($rules[$field] ?? []);
            $enabled = self::boolInt($stored['enabled'] ?? $definition['default_enabled']);
            $required = $enabled === 1
                ? self::boolInt($stored['required'] ?? $definition['default_required'])
                : 0;
            $normalized[$field] = [
                'enabled' => $enabled,
                'required' => $required,
            ];
        }
        return $normalized;
    }

    public static function defaults(): array
    {
        return self::normalizeRules([]);
    }

    public static function describe(array $workspace): array
    {
        $mode = in_array((string)($workspace['mode'] ?? 'one_stop'), ['one_stop', 'split', 'photo_price'], true)
            ? (string)$workspace['mode']
            : 'one_stop';
        $fieldRules = self::normalizeRules((array)($workspace['field_rules'] ?? []));

        $steps = match ($mode) {
            'split' => [
                self::step(self::ACTION_PHOTO, '商品拍摄', '拍摄人员', '上传图片、视频并记录必要质检说明'),
                self::step(self::ACTION_PRICE, '销售定价', '库位负责人', '填写对外销售价格'),
                self::step(self::ACTION_MATERIAL, '资料整理', '商城运营或库存人员', '核对目录型号、规格和对外说明'),
            ],
            'photo_price' => [
                self::step(self::ACTION_MEDIA_PRICE, '拍摄并定价', '拍摄定价人员', '在一个表单完成图片、视频和销售价格'),
                self::step(self::ACTION_MATERIAL, '资料整理', '商城运营或库存人员', '核对目录型号、规格和对外说明'),
            ],
            default => [
                self::step(self::ACTION_ONE_STOP, '一次完善', '库存人员', '在一个表单完成全部销售资料'),
            ],
        };

        $forms = [];
        foreach ([
            self::ACTION_ONE_STOP,
            self::ACTION_PHOTO,
            self::ACTION_PRICE,
            self::ACTION_MEDIA_PRICE,
            self::ACTION_MATERIAL,
        ] as $action) {
            $forms[$action] = self::form($action, $fieldRules);
        }

        return [
            'mode' => $mode,
            'entry_mode' => $mode === 'one_stop' ? 'complete' : 'collaborative',
            'field_rules' => $fieldRules,
            'steps' => $steps,
            'forms' => $forms,
        ];
    }

    public static function editableFields(string $action, array $workspace): array
    {
        $contract = self::describe($workspace);
        return array_values((array)($contract['forms'][$action]['editable_fields'] ?? []));
    }

    public static function requiredFields(string $action, array $workspace): array
    {
        $contract = self::describe($workspace);
        return array_values((array)($contract['forms'][$action]['required_fields'] ?? []));
    }

    public static function fieldLabel(string $field): string
    {
        return (string)(self::FIELD_DEFINITIONS[$field]['label'] ?? $field);
    }

    public static function hasValue(string $field, mixed $value): bool
    {
        if ($field === 'catalog_product_id') return (int)$value > 0;
        if ($field === 'retail_price') return (float)$value > 0;
        if ($field === 'image_urls') {
            if (is_array($value)) {
                return count(array_filter($value, static fn($item): bool => trim((string)$item) !== '')) > 0;
            }
            $text = trim((string)$value);
            if ($text === '') return false;
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return count(array_filter($decoded, static fn($item): bool => trim((string)$item) !== '')) > 0;
            }
            return count(array_filter(array_map('trim', explode(',', $text)))) > 0;
        }
        return trim((string)$value) !== '';
    }

    private static function form(string $action, array $fieldRules): array
    {
        $groups = match ($action) {
            self::ACTION_PHOTO => ['media'],
            self::ACTION_PRICE => ['price'],
            self::ACTION_MEDIA_PRICE => ['media', 'price'],
            self::ACTION_MATERIAL => ['material'],
            default => ['material', 'media', 'price', 'internal'],
        };
        $visible = [];
        $required = [];
        foreach (self::FIELD_DEFINITIONS as $field => $definition) {
            if (!in_array((string)$definition['group'], $groups, true)) continue;
            $stageCoreField = match ($action) {
                self::ACTION_PHOTO => $field === 'image_urls',
                self::ACTION_PRICE => $field === 'retail_price',
                self::ACTION_MEDIA_PRICE => in_array($field, ['image_urls', 'retail_price'], true),
                self::ACTION_MATERIAL => in_array($field, ['catalog_product_id', 'spec'], true),
                default => false,
            };
            // 分岗模式中的核心交付物不能被误关；字段开关主要控制一站式表单的
            // 信息密度，技术上必须完成的岗位交付仍然展示。
            if ((int)($fieldRules[$field]['enabled'] ?? 0) !== 1 && !$stageCoreField) continue;
            $visible[] = $field;
            $stageRequired = match ($action) {
                self::ACTION_PHOTO => $field === 'image_urls',
                self::ACTION_PRICE => $field === 'retail_price',
                self::ACTION_MEDIA_PRICE => in_array($field, ['image_urls', 'retail_price'], true),
                self::ACTION_MATERIAL => (int)($fieldRules[$field]['required'] ?? 0) === 1,
                default => (int)($fieldRules[$field]['required'] ?? 0) === 1,
            };
            if ($stageRequired) $required[] = $field;
        }

        $meta = match ($action) {
            self::ACTION_PHOTO => ['title' => '完成商品拍摄', 'description' => '只处理图片、视频与必要的质检说明', 'submit_label' => '完成拍摄'],
            self::ACTION_PRICE => ['title' => '完成销售定价', 'description' => '只填写销售价格，不修改采购成本', 'submit_label' => '完成销售定价'],
            self::ACTION_MEDIA_PRICE => ['title' => '拍摄并销售定价', 'description' => '连续完成商品拍摄和销售价格', 'submit_label' => '完成拍摄与定价'],
            self::ACTION_MATERIAL => ['title' => '整理商城资料', 'description' => '核对目录型号、规格和对外展示资料', 'submit_label' => '完成资料整理'],
            default => ['title' => '一次完善商品资料', 'description' => '在当前表单完成销售所需资料', 'submit_label' => '保存商品资料'],
        };

        return $meta + [
            'action' => $action,
            'visible_fields' => $visible,
            'editable_fields' => $visible,
            'required_fields' => $required,
        ];
    }

    private static function step(string $key, string $name, string $role, string $description): array
    {
        return compact('key', 'name', 'role', 'description');
    }

    private static function boolInt(mixed $value): int
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true) ? 1 : 0;
    }
}
