<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\{Context,CatalogService};
use addon\hsx_lead\app\support\{Data,PhpRuntime};
use core\exception\CommonException;

/** Small declarative business definitions. Never evaluates templates, expressions or HTML. */
final class BusinessService
{
    private const MAX_AMOUNT_CENTS = 9999999999;

    public function __construct(private Context $context)
    {
    }

    public static function defaults(): array
    {
        $brands = [];
        foreach (['apple' => '苹果', 'huawei' => '华为', 'xiaomi' => '小米', 'honor' => '荣耀', 'oppo' => 'OPPO', 'vivo' => 'vivo', 'samsung' => '三星', 'realme' => '真我', 'oneplus' => '一加'] as $key => $title) $brands[] = ['id' => 'brand_'.$key, 'title' => $title, 'brand' => $title, 'category_name' => '手机品牌', 'path' => ['新机', '手机品牌', $title]];
        $operators = [];
        foreach (['mobile' => '移动', 'unicom' => '联通', 'telecom' => '电信'] as $key => $title) $operators[] = ['id' => 'operator_'.$key, 'title' => $title, 'brand' => '', 'category_name' => '运营商', 'path' => ['宽带', '运营商', $title]];
        $rows = [];
        foreach ([['purchase', '二手机', 'model', '100.00'], ['new_phone', '新机', 'brand', '100.00'], ['rent', '租机', 'model', '200.00'], ['exchange', '二手机置换', 'model', '100.00'], ['broadband', '宽带', 'operator', '200.00'], ['repair', '手机维修', 'model', '100.00'], ['home_recycle', '二手机上门回收', 'model', '100.00']] as [$key,$name,$dimension,$minimum]) {
            $fields = [];
            if ($key === 'repair') $fields[] = ['key' => 'fault_detail', 'label' => '故障描述', 'type' => 'text', 'required' => true, 'placeholder' => '例如无法充电、屏幕损坏，简单描述即可'];
            if ($key === 'home_recycle') $fields[] = ['key' => 'pickup_address', 'label' => '上门地址', 'type' => 'address', 'required' => true, 'placeholder' => '小区、街道或附近地标，无需重复填写省市区'];
            $rows[] = ['key' => $key, 'name' => $name, 'enabled' => !in_array($key, ['repair', 'home_recycle'], true), 'dimension' => $dimension, 'selection_mode' => 'multiple', 'selection_limit' => $dimension === 'model' ? 5 : 0, 'commission_minimum' => $minimum, 'fee_override' => false, 'fields' => $fields, 'options' => $dimension === 'brand' ? $brands : ($dimension === 'operator' ? $operators : [])];
        }
        return $rows;
    }

    public static function key($value, string $label = '标识'): string
    {
        if (!is_string($value) || !preg_match('/^[a-z][a-z0-9_]{0,15}$/D', $value) || in_array($value, ['__proto__', 'prototype', 'constructor'], true)) throw new CommonException($label.'须为1—16位小写字母、数字或下划线，并以字母开头');
        return $value;
    }

    public static function label($value, int $limit = 100): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || ($text = trim($value)) === '' || mb_strlen($text) > $limit || preg_match('/[<>\x00-\x1F\x7F]/u', $value)) throw new CommonException('业务配置只支持指定长度的纯文本，不允许HTML或控制字符');
        return $text;
    }

    private static function flag($value): bool
    {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) throw new CommonException('业务开关和必填设置必须明确为是或否');
        return in_array($value, [true, 1, '1'], true);
    }

    private static function money($value, string $label, bool $positive = false): string
    {
        $cents = Rules::cents($value);
        if (($positive && $cents < 1) || $cents > self::MAX_AMOUNT_CENTS) throw new CommonException($label.'须'.($positive ? '大于0，且' : '').'不超过99999999.99元');
        return Rules::amount($cents);
    }

    private static function placeholder($value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen(trim($value)) > 120 || preg_match('/[<>\x00-\x1F\x7F]/u', $value)) throw new CommonException('字段提示最多120字，只支持纯文本，不允许HTML或控制字符');
        return trim($value);
    }

    public static function validate($rows): array
    {
        if (!is_array($rows) || !PhpRuntime::isList($rows) || !$rows || count($rows) > 30) throw new CommonException('请设置1—30项业务');
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new CommonException('业务配置格式错误');
            $key = self::key($row['key'] ?? null, '业务标识');
            if (isset($seen[$key])) throw new CommonException('业务标识不能重复');
            $seen[$key] = true;
            $dimension = $row['dimension'] ?? null;
            $mode = $row['selection_mode'] ?? null;
            if (!in_array($dimension, ['model', 'brand', 'operator'], true) || !in_array($mode, ['multiple', 'single'], true)) throw new CommonException('请选择有效的能力维度及单选/多选模式');
            $fields = $row['fields'] ?? [];
            if (!is_array($fields) || !PhpRuntime::isList($fields) || count($fields) > 20) throw new CommonException('每项业务最多配置20个简单字段');
            $cleanFields = [];
            $fieldKeys = [];
            foreach ($fields as $field) {
                if (!is_array($field)) throw new CommonException('业务字段格式错误');
                $fieldKey = self::key($field['key'] ?? null, '字段标识');
                if (isset($fieldKeys[$fieldKey])) throw new CommonException('同一业务字段标识不能重复');
                $fieldKeys[$fieldKey] = true;
                $type = $field['type'] ?? null;
                if (!in_array($type, ['text', 'number', 'select', 'address'], true)) throw new CommonException('业务字段仅支持文本、数字、下拉选择和地址');
                $clean = ['key' => $fieldKey, 'label' => self::label($field['label'] ?? null), 'type' => $type, 'required' => self::flag($field['required'] ?? false)];
                if (array_key_exists('placeholder', $field)) $clean['placeholder'] = self::placeholder($field['placeholder']);
                if ($type === 'select') {
                    $choices = $field['options'] ?? null;
                    if (!is_array($choices) || !PhpRuntime::isList($choices) || !$choices || count($choices) > 100) throw new CommonException('下拉字段请设置1—100个选项');
                    $values = [];
                    $clean['options'] = [];
                    foreach ($choices as $choice) {
                        if (!is_array($choice)) throw new CommonException('下拉选项格式错误');
                        $value = self::label($choice['value'] ?? null);
                        if (isset($values[$value])) throw new CommonException('下拉选项值不能重复');
                        $values[$value] = true;
                        $clean['options'][] = ['value' => $value, 'label' => self::label($choice['label'] ?? null)];
                    }
                }
                $cleanFields[] = $clean;
            }
            $options = $row['options'] ?? [];
            if (!is_array($options) || !PhpRuntime::isList($options) || count($options) > 5000) throw new CommonException('每项业务最多设置5000个能力选项');
            $cleanOptions = [];
            $ids = [];
            foreach ($options as $option) {
                if (!is_array($option)) throw new CommonException('业务能力选项格式错误');
                $id = $option['id'] ?? null;
                if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $id) || isset($ids[$id])) throw new CommonException('能力选项ID必须唯一，且只含字母数字下划线或横线');
                $ids[$id] = true;
                $title = self::label($option['title'] ?? null, 200);
                $path = $option['path'] ?? [];
                if (!is_array($path) || !PhpRuntime::isList($path) || count($path) > 3) throw new CommonException('目录分类最多三级');
                $path = array_map(static fn ($part) => self::label($part), $path);
                $aliases = $option['aliases'] ?? [];
                if (!is_array($aliases) || !PhpRuntime::isList($aliases) || count($aliases) > 20) throw new CommonException('每个能力选项最多20个纯文本别名');
                $aliases = array_values(array_unique(array_map(static fn ($alias) => self::label($alias, 200), $aliases)));
                $cleanOptions[] = ['id' => $id, 'title' => $title, 'brand' => isset($option['brand']) && $option['brand'] !== '' ? self::label($option['brand']) : '', 'category_name' => isset($option['category_name']) && $option['category_name'] !== '' ? self::label($option['category_name']) : '', 'path' => $path, 'aliases' => $aliases];
            }
            $clean = ['key' => $key, 'name' => self::label($row['name'] ?? null), 'enabled' => self::flag($row['enabled'] ?? false), 'dimension' => $dimension, 'selection_mode' => $mode, 'selection_limit' => Rules::demandLimit($row['selection_limit'] ?? 0), 'commission_minimum' => self::money($row['commission_minimum'] ?? '0.00', '成交最低协作费用', true), 'fee_override' => self::flag($row['fee_override'] ?? false), 'fields' => $cleanFields, 'options' => $cleanOptions];
            // An explicitly maintained empty directory must not borrow another
            // business's models after the administrator removes its last item.
            if (array_key_exists('catalog_configured', $row)) $clean['catalog_configured'] = self::flag($row['catalog_configured']);
            if ($clean['fee_override']) {
                // Validate even a currently disabled preset, without activating charging.
                $clean['fee_percent'] = Rules::amount(Rules::cents($row['fee_percent'] ?? null));
                $clean['fee_minimum'] = self::money($row['fee_minimum'] ?? null, '平台保底维护费');
                self::feeRules($clean, ['fee_confirmed' => false]);
            }
            $out[] = $clean;
        }
        return $out;
    }

    public function definition(string $key, bool $requireEnabled = true, ?array $config = null): array
    {
        self::key($key, '业务标识');
        $config = $config ?? (new ConfigService($this->context))->get();
        foreach ($config['businesses'] as $row) if ($row['key'] === $key) {
            if ($requireEnabled && !$row['enabled']) throw new CommonException('该业务已关闭，暂不能新增发单或配置能力');
            return $row;
        }
        throw new CommonException('业务不存在，请刷新后重新选择');
    }

    public function options(array $business): array
    {
        $rows = $business['options'];
        if (!$rows && $business['dimension'] === 'model' && (($business['key'] ?? '') === 'purchase' || empty($business['catalog_configured']))) $rows = (new CatalogService($this->context))->directory()['items'];
        return array_map(static function (array $item) use ($business): array {
            $path = $item['path'] ?? array_values(array_filter([$item['category_name'] ?? '', $item['brand'] ?? '', $item['title']], static fn ($v) => $v !== ''));
            return ['id' => $item['id'], 'title' => $item['title'], 'brand' => $item['brand'] ?? '', 'category_name' => $item['category_name'] ?? '', 'dimension' => $business['dimension'], 'path' => $path, 'aliases' => $item['aliases'] ?? []];
        }, $rows);
    }

    public function resolve(array $business, $ids, bool $capability = false): array
    {
        if (!is_array($ids) || !PhpRuntime::isList($ids) || (!$capability && !$ids) || count($ids) > 5000) throw new CommonException('请从当前业务目录选择需求或能力选项，最多5000项');
        $unique = [];
        foreach ($ids as $id) {
            if (!is_string($id) || strlen($id) > 100) throw new CommonException('能力选项ID格式错误');
            $unique[$id] = $id;
        }
        if (!$capability) {
            $limit = $business['selection_mode'] === 'single' ? 1 : (int) $business['selection_limit'];
            if ($limit > 0 && count($unique) > $limit) throw new CommonException('本次'.$business['name'].'最多选择'.$limit.'项需求');
        }
        $catalog = array_column($this->options($business), null, 'id');
        $items = [];
        foreach ($unique as $id) {
            if (!isset($catalog[$id])) throw new CommonException('所选选项不属于当前业务或已变化，请重新选择');
            $items[] = $catalog[$id];
        }
        return $items;
    }

    public static function formValues(array $business, $input): array
    {
        if (is_object($input)) $input = (array) $input;
        if (!is_array($input) || count($input) > 20) throw new CommonException('业务表单应为字段对象');
        $fields = array_column($business['fields'], null, 'key');
        foreach ($input as $key => $value) if (!is_string($key) || !isset($fields[$key])) throw new CommonException('包含未配置的业务字段，请刷新表单');
        $out = [];
        foreach ($fields as $key => $field) {
            $value = $input[$key] ?? '';
            if ($value === null || $value === '') {
                if ($field['required']) throw new CommonException('请填写'.$field['label']);
                continue;
            }
            if (!is_string($value) && !is_int($value)) throw new CommonException($field['label'].'格式错误');
            $value = self::label((string) $value, $field['type'] === 'address' ? 300 : 1000);
            if ($field['type'] === 'number' && !preg_match('/^-?(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,4})?$/D', $value)) throw new CommonException($field['label'].'请填写有效数字');
            if ($field['type'] === 'select' && !in_array($value, array_column($field['options'], 'value'), true)) throw new CommonException($field['label'].'请选择配置中的选项');
            $out[$key] = $value;
        }
        return $out;
    }

    public static function snapshot(array $business, array $config, array $form): array
    {
        $definition = $business;
        unset($definition['options']);
        return ['business_type' => $business['key'], 'business_name' => $business['name'], 'dimension' => $business['dimension'], 'business_snapshot' => $definition, 'form_values' => $form] + self::feeRules($business, $config);
    }

    /** Resolve once for a new lead; existing leads/orders continue using their stored rule_json. */
    public static function feeRules(array $business, array $config): array
    {
        $override = self::flag($business['fee_override'] ?? false);
        $source = $override ? $business : $config;
        $rules = ['commission_minimum' => self::money($business['commission_minimum'] ?? null, '成交最低协作费用', true),
            'fee_percent' => Rules::amount(Rules::cents($source['fee_percent'] ?? null)),
            'fee_minimum' => self::money($source['fee_minimum'] ?? null, '平台保底维护费'),
            'fee_confirmed' => (bool) ($config['fee_confirmed'] ?? false)];
        if (Rules::cents($rules['fee_percent']) >= 10000) throw new CommonException('平台维护费比例须小于100%');
        // Check the actual rounded minimum split even before confirmation; no money is moved.
        Rules::split(Rules::cents($rules['commission_minimum']), array_replace($rules, ['fee_confirmed' => true]));
        return $rules;
    }
}
