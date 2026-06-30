<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\delivery;

/**
 * 常用快递公司预置字典（按服务商分别绑定）
 *
 * 设计：一家逻辑公司可对接多个服务商，每个服务商有各自的编码与"是否出面单"能力。
 *  - 快递100：编码为 kuaidicom（《快递公司编码字典》），所列均支持电子面单。
 *  - 易速：编码为 productCode（数字，见易速《快递产品列表》）；**仅顺丰(5)、京东(13) 出 PDF 面单**，其余仅可下单不出面单。
 *
 * bindings 结构：provider => ['code' => 服务商编码, 'electronic_sheet' => 是否支持面单(0/1)]
 *
 * @package addon\hsx_recycle\app\dict\delivery
 */
class ExpressCompanyDict
{
    public static function presets(): array
    {
        return [
            ['company_name' => '顺丰速运', 'sort' => 100, 'bindings' => [
                'kuaidi100' => ['code' => 'shunfeng', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '5', 'electronic_sheet' => 1],
            ]],
            ['company_name' => '京东快递', 'sort' => 95, 'bindings' => [
                'kuaidi100' => ['code' => 'jd', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '13', 'electronic_sheet' => 1],
            ]],
            ['company_name' => '圆通速递', 'sort' => 90, 'bindings' => [
                'kuaidi100' => ['code' => 'yuantong', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '2', 'electronic_sheet' => 0],
            ]],
            ['company_name' => '中通快递', 'sort' => 85, 'bindings' => [
                'kuaidi100' => ['code' => 'zhongtong', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '11', 'electronic_sheet' => 0],
            ]],
            ['company_name' => '申通快递', 'sort' => 80, 'bindings' => [
                'kuaidi100' => ['code' => 'shentong', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '1', 'electronic_sheet' => 0],
            ]],
            ['company_name' => '韵达速递', 'sort' => 75, 'bindings' => [
                'kuaidi100' => ['code' => 'yunda', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '12', 'electronic_sheet' => 0],
            ]],
            ['company_name' => '极兔速递', 'sort' => 70, 'bindings' => [
                'kuaidi100' => ['code' => 'jtexpress', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '10', 'electronic_sheet' => 0],
            ]],
            ['company_name' => 'EMS', 'sort' => 65, 'bindings' => [
                'kuaidi100' => ['code' => 'ems', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '47', 'electronic_sheet' => 0],
            ]],
            ['company_name' => '邮政快递包裹', 'sort' => 60, 'bindings' => [
                'kuaidi100' => ['code' => 'youzhengguonei', 'electronic_sheet' => 1],
            ]],
            ['company_name' => '德邦快递', 'sort' => 55, 'bindings' => [
                'kuaidi100' => ['code' => 'debangkuaidi', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '3', 'electronic_sheet' => 0],
            ]],
            ['company_name' => '德邦物流', 'sort' => 50, 'bindings' => [
                'kuaidi100' => ['code' => 'debangwuliu', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '21', 'electronic_sheet' => 0],
            ]],
            ['company_name' => '顺丰快运', 'sort' => 45, 'bindings' => [
                'kuaidi100' => ['code' => 'shunfengkuaiyun', 'electronic_sheet' => 1],
                'yisu'      => ['code' => '24', 'electronic_sheet' => 0],
            ]],
            ['company_name' => '菜鸟裹裹', 'sort' => 40, 'bindings' => [
                'yisu'      => ['code' => '36', 'electronic_sheet' => 0],
            ]],
        ];
    }

    /**
     * 易速面单模板编号（temCode）可选项，仅顺丰/京东出 PDF 面单。
     * 顺丰默认 150；京东默认 113。
     */
    public static function yisuWaybillTemplates(): array
    {
        return [
            '5'  => [ // 顺丰
                ['label' => '100×150（默认）', 'value' => '150'],
                ['label' => '100×180', 'value' => '180'],
                ['label' => '100×210', 'value' => '210'],
            ],
            '13' => [ // 京东
                ['label' => '76×105', 'value' => '105'],
                ['label' => '76×130', 'value' => '130'],
                ['label' => '100×113（默认）', 'value' => '113'],
                ['label' => '100×150', 'value' => '150'],
            ],
        ];
    }
}
