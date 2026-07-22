<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use core\base\BaseAdminService;

/**
 * ERP 财务事实来源契约。
 *
 * source_type/source_id/source_no 继续表示 ERP 内部关联单据；本服务负责保存独立的
 * 业务来源、业务场景、财务分类和渠道快照，避免插件卸载或字典改名后历史账目失真。
 */
class ErpFinanceSourceService extends BaseAdminService
{
    public function persistable(array $meta): array
    {
        return array_intersect_key($meta, array_flip([
            'origin_plugin', 'origin_plugin_name', 'origin_type', 'origin_name', 'origin_id', 'origin_no',
            'biz_scene', 'category_key', 'category_name', 'category_statement_group',
            'category_source_plugin', 'category_source_key', 'channel_code', 'channel_name', 'business_reason',
        ]));
    }

    public function purchase(array $context = []): array
    {
        $plugin = $this->normalizePlugin((string)($context['origin_plugin'] ?? $context['source_plugin'] ?? 'hsx_erp'));
        $sourceKey = trim((string)($context['origin_type'] ?? $context['source_type'] ?? ''));
        if ($sourceKey === '' || in_array($sourceKey, ['manual', 'erp_purchase', 'hsx_recycle_purchase'], true)) {
            $sourceKey = $plugin === 'hsx_recycle' ? 'hsx_recycle.recycle_purchase' : 'hsx_erp.manual_purchase';
        }
        return $this->build(
            $sourceKey,
            $plugin === 'hsx_recycle' ? '回收插件采购' : 'ERP采购',
            $plugin,
            'purchase',
            $this->category('inventory_purchase', '设备采购支出', 'expense', 'purchase'),
            $context,
            '供应商',
            '采购入库形成设备采购应付，财务按设备核对并付款。'
        );
    }

    public function sale(array $context = []): array
    {
        $plugin = $this->normalizePlugin((string)($context['origin_plugin'] ?? 'hsx_erp'));
        $sourceKey = trim((string)($context['origin_type'] ?? ''));
        if ($sourceKey === '' || in_array($sourceKey, ['manual', 'erp_sale', 'phone_shop_sale'], true)) {
            $sourceKey = $plugin === 'phone_shop' ? 'phone_shop.mini_program_sale' : 'hsx_erp.manual_sale';
        }
        return $this->build(
            $sourceKey,
            $plugin === 'phone_shop' ? '小程序销售' : 'ERP销售',
            $plugin,
            'sale',
            $this->category('sale_revenue', '销售收入', 'income', 'revenue'),
            $context,
            '客户',
            '设备销售出库形成销售应收，财务按实际到账确认收款。'
        );
    }

    public function purchaseReturn(array $context = []): array
    {
        return $this->build(
            trim((string)($context['origin_type'] ?? '')) ?: 'hsx_erp.manual_purchase_return',
            'ERP采购退货',
            $this->normalizePlugin((string)($context['origin_plugin'] ?? 'hsx_erp')),
            'purchase_return',
            $this->category('purchase_refund', '采购退货款收回', 'income', 'purchase_reversal'),
            $context,
            '供货商',
            '采购退货中已付款部分形成退款应收，财务需确认供货商实际退款到账。'
        );
    }

    public function saleReturn(bool $compensation, array $context = []): array
    {
        $category = $compensation
            ? $this->category('after_sale_compensation', '售后补差', 'expense', 'revenue_reversal')
            : $this->category('sale_refund', '销售退货退款', 'expense', 'revenue_reversal');
        return $this->build(
            trim((string)($context['origin_type'] ?? '')) ?: 'hsx_erp.manual_sale_return',
            $compensation ? 'ERP售后补差' : 'ERP销售退货',
            $this->normalizePlugin((string)($context['origin_plugin'] ?? 'hsx_erp')),
            $compensation ? 'after_sale_compensation' : 'sale_return',
            $category,
            $context,
            '客户',
            $compensation
                ? '售后协商补差形成公司应付，财务向客户支付后冲减该设备实际销售收入。'
                : '客户退回已售设备，已收款部分形成销售退款应付。'
        );
    }

    public function consignmentSale(array $context = []): array
    {
        return $this->build(
            trim((string)($context['origin_type'] ?? '')) ?: 'hsx_recycle.consignment_sale',
            '代卖成交结算',
            $this->normalizePlugin((string)($context['origin_plugin'] ?? 'hsx_recycle')),
            'consignment_sale',
            $this->category('consignment_settlement', '代卖货款结算', 'expense', 'cost_of_sales'),
            $context,
            '货主',
            '客户代卖设备已成交，财务按设备向货主支付约定结算金额。'
        );
    }

    public function refurbish(array $category, array $context = []): array
    {
        $financeCategory = $this->category(
            (string)($category['key'] ?? 'refurbish_labor'),
            (string)($category['name'] ?? '整备费用'),
            'expense',
            (string)($category['statement_group'] ?? 'operating_expense'),
            (string)($category['source_plugin'] ?? 'hsx_erp'),
            (string)($category['source_key'] ?? $category['key'] ?? 'refurbish_labor')
        );
        return $this->build(
            trim((string)($context['origin_type'] ?? '')) ?: 'hsx_erp.manual_refurbish',
            'ERP整备',
            $this->normalizePlugin((string)($context['origin_plugin'] ?? 'hsx_erp')),
            'refurbish',
            $financeCategory,
            $context,
            '整备服务商',
            '设备整备或维修产生支出并计入设备成本，财务向服务商付款。'
        );
    }

    /** 将数据库快照转换为 PC/移动端共用的展示契约。 */
    public function sourceMeta(array $row, string $side): array
    {
        $scene = trim((string)($row['biz_scene'] ?? ''));
        if ($scene === '') {
            $sourceType = (string)($row['source_type'] ?? '');
            $scene = match ($sourceType) {
                'sale' => 'sale',
                'purchase_return' => 'purchase_return',
                'sale_return' => ((string)($row['sale_return_business_type'] ?? '') === 'after_sale_compensation') ? 'after_sale_compensation' : 'sale_return',
                'refurbish' => 'refurbish',
                'consignment_sale' => 'consignment_sale',
                default => 'purchase',
            };
        }
        $knownScene = in_array($scene, ['sale', 'purchase_return', 'sale_return', 'after_sale_compensation', 'refurbish', 'purchase', 'consignment_sale'], true);

        $defaults = match ($scene) {
            'sale' => $this->sale($row),
            'purchase_return' => $this->purchaseReturn($row),
            'sale_return' => $this->saleReturn(false, $row),
            'after_sale_compensation' => $this->saleReturn(true, $row),
            'refurbish' => $this->refurbish([
                'key' => $row['category_key'] ?? 'refurbish_labor',
                'name' => $row['category_name'] ?? '整备费用',
                'statement_group' => $row['category_statement_group'] ?? 'operating_expense',
                'source_plugin' => $row['category_source_plugin'] ?? 'hsx_erp',
                'source_key' => $row['category_source_key'] ?? 'refurbish_labor',
            ], $row),
            'consignment_sale' => $this->consignmentSale($row),
            default => $this->purchase($row),
        };
        $snapshot = array_replace($defaults, array_filter([
            'origin_plugin' => $row['origin_plugin'] ?? null,
            'origin_plugin_name' => $row['origin_plugin_name'] ?? null,
            'origin_type' => $row['origin_type'] ?? null,
            'origin_name' => $row['origin_name'] ?? null,
            'origin_id' => $row['origin_id'] ?? null,
            'origin_no' => $row['origin_no'] ?? null,
            'biz_scene' => $row['biz_scene'] ?? null,
            'category_key' => $row['category_key'] ?? null,
            'category_name' => $row['category_name'] ?? null,
            'category_statement_group' => $row['category_statement_group'] ?? null,
            'category_source_plugin' => $row['category_source_plugin'] ?? null,
            'category_source_key' => $row['category_source_key'] ?? null,
            'channel_code' => $row['channel_code'] ?? null,
            'channel_name' => $row['channel_name'] ?? null,
            'business_reason' => $row['business_reason'] ?? null,
        ], static fn($value): bool => $value !== null && $value !== ''));

        $originNo = trim((string)($snapshot['origin_no'] ?? ''));
        $internalSourceNo = trim((string)($row['source_no'] ?? ''));
        $bizScene = (string)($snapshot['biz_scene'] ?? $scene);
        $businessSourceName = ($bizScene === 'operating' || str_starts_with($bizScene, 'operating_'))
            ? (string)($snapshot['category_name'] ?? '经营收支')
            : (string)($snapshot['origin_name'] ?? 'ERP业务');
        $channelCode = trim((string)($snapshot['channel_code'] ?? ''));
        $channelName = trim((string)($snapshot['channel_name'] ?? ''));
        // 兼容早期数据：曾把 purchase_return 等业务场景误存为“渠道”。
        // 场景已经单独展示，渠道只保留真实的同行、小程序等销售/采购入口。
        $internalScenes = ['purchase', 'sale', 'purchase_return', 'sale_return', 'after_sale_compensation', 'refurbish', 'consignment_sale'];
        if (in_array($channelName, $internalScenes, true)) $channelName = '';
        if (in_array($channelCode, $internalScenes, true)) $channelCode = '';

        return [
            // 应收/应付是财务方向的最终事实，避免未知插件场景被旧 source_type 推断反向。
            'direction' => $side === 'receivable' ? 'income' : 'expense',
            'finance_type_key' => (string)($snapshot['category_key'] ?? ''),
            'finance_type_name' => (string)($snapshot['category_name'] ?? ($side === 'receivable' ? '应收款' : '应付款')),
            'statement_group' => (string)($snapshot['category_statement_group'] ?? ''),
            'business_source_key' => (string)($snapshot['origin_type'] ?? ''),
            'business_source_name' => $businessSourceName,
            'source_plugin' => (string)($snapshot['origin_plugin'] ?? 'hsx_erp'),
            'source_plugin_name' => (string)($snapshot['origin_plugin_name'] ?? $this->pluginName((string)($snapshot['origin_plugin'] ?? 'hsx_erp'))),
            // “来源单号”优先展示插件原单；ERP 内部关联单号单独保留，避免回收单/
            // 小程序单已落快照却仍只看到 PO、SO、资产号等 ERP 内部编号。
            'source_no' => $originNo !== '' ? $originNo : $internalSourceNo,
            'origin_no' => $originNo,
            'internal_source_no' => $internalSourceNo,
            'channel_code' => $channelCode,
            'channel_name' => $channelName,
            'biz_scene' => $bizScene,
            'party_role_label' => $knownScene
                ? (string)($snapshot['party_role_label'] ?? ($side === 'receivable' ? '付款方' : '收款方'))
                : ($side === 'receivable' ? '付款方' : '收款方'),
            'business_reason' => (string)($snapshot['business_reason'] ?? ''),
        ];
    }

    private function build(
        string $sourceKey,
        string $fallbackName,
        string $plugin,
        string $scene,
        array $category,
        array $context,
        string $partyRole,
        string $reason
    ): array {
        $option = null;
        $config = new ErpConfigService();
        if (method_exists($config, 'findBusinessSource')) {
            $option = $config->findBusinessSource($sourceKey);
        }
        $contextOriginName = trim((string)($context['origin_name'] ?? ''));
        $originName = $contextOriginName !== ''
            ? $contextOriginName
            : (trim((string)($option['name'] ?? '')) ?: $fallbackName);
        $contextOriginPlugin = trim((string)($context['origin_plugin'] ?? ''));
        $originPlugin = $this->normalizePlugin($contextOriginPlugin !== ''
            ? $contextOriginPlugin
            : (string)($option['source_plugin'] ?? $plugin));
        $contextPluginName = trim((string)($context['origin_plugin_name'] ?? ''));
        return [
            'origin_plugin' => $originPlugin,
            'origin_plugin_name' => $contextPluginName !== '' ? $contextPluginName : $this->pluginName($originPlugin),
            'origin_type' => $sourceKey,
            'origin_name' => $originName,
            'origin_id' => mb_substr(trim((string)($context['origin_id'] ?? $context['source_id'] ?? '')), 0, 80),
            'origin_no' => mb_substr(trim((string)($context['origin_no'] ?? $context['source_order_no'] ?? '')), 0, 80),
            'biz_scene' => $scene,
            'category_key' => (string)$category['key'],
            'category_name' => (string)$category['name'],
            'category_statement_group' => (string)$category['statement_group'],
            'category_source_plugin' => (string)$category['source_plugin'],
            'category_source_key' => (string)$category['source_key'],
            'direction' => (string)$category['direction'],
            'channel_code' => mb_substr(trim((string)($context['channel_code'] ?? $context['sale_channel_key'] ?? $context['purchase_channel_key'] ?? '')), 0, 80),
            'channel_name' => mb_substr(trim((string)($context['channel_name'] ?? $context['sale_channel'] ?? $context['purchase_channel'] ?? '')), 0, 60),
            'party_role_label' => $partyRole,
            'business_reason' => mb_substr(trim((string)($context['business_reason'] ?? $reason)), 0, 255),
        ];
    }

    private function category(
        string $key,
        string $name,
        string $direction,
        string $statementGroup,
        string $sourcePlugin = 'hsx_erp',
        string $sourceKey = ''
    ): array {
        // 标准场景也从动态分类 Hook 取快照：插件可扩展维修、回收、平台服务等收支类型，
        // 但单据落库后只读快照，不因插件卸载或改名而改变历史账目。
        $configured = (new ErpConfigService())->findFinanceCategory($key);
        if (is_array($configured)) {
            $name = trim((string)($configured['name'] ?? $name)) ?: $name;
            $direction = (string)($configured['direction'] ?? $direction);
            $statementGroup = trim((string)($configured['statement_group'] ?? $statementGroup)) ?: $statementGroup;
            $sourcePlugin = trim((string)($configured['source_plugin'] ?? $sourcePlugin)) ?: $sourcePlugin;
            $sourceKey = trim((string)($configured['source_key'] ?? $sourceKey)) ?: $sourceKey;
        }
        return [
            'key' => $key,
            'name' => $name,
            'direction' => $direction,
            'statement_group' => $statementGroup,
            'source_plugin' => $this->normalizePlugin($sourcePlugin),
            'source_key' => $sourceKey !== '' ? $sourceKey : $key,
        ];
    }

    private function normalizePlugin(string $plugin): string
    {
        $plugin = trim($plugin);
        return $plugin === '' || $plugin === 'erp' ? 'hsx_erp' : mb_substr($plugin, 0, 40);
    }

    private function pluginName(string $plugin): string
    {
        return match ($this->normalizePlugin($plugin)) {
            'hsx_recycle' => '手机回收',
            'phone_shop' => '小程序商城',
            'hsx_erp' => '二手机ERP',
            default => $plugin !== '' ? $plugin : '二手机ERP',
        };
    }
}
