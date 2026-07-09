<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;

class ErpConfigService extends BaseAdminService
{
    public const CONFIG_KEY = 'HSX_ERP_RULES';
    public const SALE_CHANNEL_KEY = 'HSX_ERP_SALE_CHANNELS';

    public function getRules(): array
    {
        $value = (new CoreConfigService())->getConfigValue($this->site_id, self::CONFIG_KEY);
        if (!is_array($value)) {
            $value = [];
        }
        return $this->normalize($value);
    }

    public function saveRules(array $data): array
    {
        $rules = $this->normalize($data);
        (new CoreConfigService())->setConfig($this->site_id, self::CONFIG_KEY, $rules);
        return $rules;
    }

    public function getSaleChannels(): array
    {
        $value = (new CoreConfigService())->getConfigValue($this->site_id, self::SALE_CHANNEL_KEY);
        if (!is_array($value)) {
            $value = [];
        }
        return $this->normalizeSaleChannels($value);
    }

    public function saveSaleChannels(array $channels): array
    {
        $rows = $this->normalizeSaleChannels($channels);
        (new CoreConfigService())->setConfig($this->site_id, self::SALE_CHANNEL_KEY, $rows);
        return $rows;
    }

    private function normalize(array $data): array
    {
        $defaults = self::defaults();
        $rules = array_replace_recursive($defaults, $data);
        $rules['finance']['enable_offset'] = $this->boolInt($rules['finance']['enable_offset'] ?? 1);
        $rules['finance']['finance_fact_lock'] = 1;
        $rules['finance']['settlement_requires_account'] = $this->boolInt($rules['finance']['settlement_requires_account'] ?? 1);

        $rules['purchase']['create_payable_on_inbound'] = 1;
        $rules['purchase']['allow_cancel_before_finance_fact'] = $this->boolInt($rules['purchase']['allow_cancel_before_finance_fact'] ?? 1);

        $rules['product_title']['category_mode'] = in_array(($rules['product_title']['category_mode'] ?? 'auto'), ['auto', 'level_1_2', 'level_2_3', 'level_3', 'full'], true)
            ? $rules['product_title']['category_mode']
            : 'auto';
        $rules['product_title']['spec_in_title'] = $this->boolInt($rules['product_title']['spec_in_title'] ?? 1);
        $rules['product_title']['grade_in_title'] = $this->boolInt($rules['product_title']['grade_in_title'] ?? 0);
        $rules['product_title']['separator'] = trim((string)($rules['product_title']['separator'] ?? ' '));
        if ($rules['product_title']['separator'] === '') {
            $rules['product_title']['separator'] = ' ';
        }

        $rules['sale']['create_receivable_on_outbound'] = 1;
        $rules['sale']['allow_cancel_before_finance_fact'] = $this->boolInt($rules['sale']['allow_cancel_before_finance_fact'] ?? 1);
        $rules['sale']['return_to_original_location_on_cancel'] = $this->boolInt($rules['sale']['return_to_original_location_on_cancel'] ?? 1);
        $rules['sale']['enable_peer_pending'] = $this->boolInt($rules['sale']['enable_peer_pending'] ?? 1);
        $rules['sale']['enable_trial_sale'] = $this->boolInt($rules['sale']['enable_trial_sale'] ?? 0);
        $rules['sale']['profit_confirm_mode'] = in_array(($rules['sale']['profit_confirm_mode'] ?? 'settlement'), ['outbound', 'settlement'], true)
            ? $rules['sale']['profit_confirm_mode']
            : 'settlement';

        $rules['refurbish']['enabled'] = $this->boolInt($rules['refurbish']['enabled'] ?? 0);
        $rules['refurbish']['default_required'] = $this->boolInt($rules['refurbish']['default_required'] ?? 0);

        $rules['consignment']['enabled'] = $this->boolInt($rules['consignment']['enabled'] ?? 0);
        $rules['consignment']['settle_payable_after_receipt'] = $this->boolInt($rules['consignment']['settle_payable_after_receipt'] ?? 1);
        $rules['consignment']['transfer_to_owned_requires_repurchase'] = 1;

        return $rules;
    }

    private function boolInt(mixed $value): int
    {
        return (int)$value === 1 || $value === true || $value === '1' ? 1 : 0;
    }

    private function normalizeSaleChannels(array $channels): array
    {
        $defaults = ['门店', '同行', '小程序'];
        $rows = empty($channels) ? $defaults : $channels;
        $normalized = [];
        foreach ($rows as $row) {
            $name = is_array($row) ? (string)($row['name'] ?? $row['label'] ?? $row['value'] ?? '') : (string)$row;
            $name = trim($name);
            if ($name === '' || in_array($name, $normalized, true)) {
                continue;
            }
            $normalized[] = mb_substr($name, 0, 30);
        }
        return array_values($normalized);
    }

    public static function defaults(): array
    {
        return [
            'finance' => [
                'enable_offset' => 1,
                'finance_fact_lock' => 1,
                'settlement_requires_account' => 1,
            ],
            'purchase' => [
                'create_payable_on_inbound' => 1,
                'allow_cancel_before_finance_fact' => 1,
            ],
            'product_title' => [
                'category_mode' => 'auto',
                'spec_in_title' => 1,
                'grade_in_title' => 0,
                'separator' => ' ',
            ],
            'sale' => [
                'create_receivable_on_outbound' => 1,
                'allow_cancel_before_finance_fact' => 1,
                'return_to_original_location_on_cancel' => 1,
                'enable_peer_pending' => 1,
                'enable_trial_sale' => 0,
                'profit_confirm_mode' => 'settlement',
            ],
            'refurbish' => [
                'enabled' => 0,
                'default_required' => 0,
            ],
            'consignment' => [
                'enabled' => 0,
                'settle_payable_after_receipt' => 1,
                'transfer_to_owned_requires_repurchase' => 1,
            ],
        ];
    }
}
