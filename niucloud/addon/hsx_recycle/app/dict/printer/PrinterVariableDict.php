<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\printer;

/**
 * 打印模板变量字典
 */
class PrinterVariableDict
{
    /**
     * 获取所有变量分组
     * @return array
     */
    public static function getVariableGroups(): array
    {
        return [
            [
                'label' => '订单信息',
                'items' => [
                    ['key' => 'order_no', 'label' => '订单号', 'sample_value' => 'RC20260101120000'],
                    ['key' => 'order_id', 'label' => '订单ID', 'sample_value' => '12345'],
                    ['key' => 'customer_name', 'label' => '客户姓名', 'sample_value' => '张三'],
                    ['key' => 'member_nickname', 'label' => '会员昵称', 'sample_value' => '小明'],
                    ['key' => 'customer_phone', 'label' => '客户电话', 'sample_value' => '138****8000'],
                    ['key' => 'pay_type', 'label' => '支付方式', 'sample_value' => '支付宝'],
                    ['key' => 'pay_account', 'label' => '支付账号', 'sample_value' => '138****8000'],
                    ['key' => 'total_amount', 'label' => '订单总金额', 'sample_value' => '5000.00'],
                    ['key' => 'device_count', 'label' => '设备数量', 'sample_value' => '1'],
                    ['key' => 'order_status', 'label' => '订单状态', 'sample_value' => '已完成'],
                    ['key' => 'order_remark', 'label' => '订单备注', 'sample_value' => '无'],
                ]
            ],
            [
                'label' => '设备基本信息',
                'items' => [
                    ['key' => 'device_id', 'label' => '设备ID', 'sample_value' => '12345'],
                    ['key' => 'imei', 'label' => 'IMEI', 'sample_value' => '867851234567890'],
                    ['key' => 'imei2', 'label' => 'IMEI2', 'sample_value' => '867851234567891'],
                    ['key' => 'sn', 'label' => 'SN序列号', 'sample_value' => 'C02XG0FHJHD5'],
                    ['key' => 'model', 'label' => '型号', 'sample_value' => 'iPhone 14 Pro Max'],
                    ['key' => 'category_name', 'label' => '分类', 'sample_value' => '手机'],
                    ['key' => 'system_version', 'label' => '系统版本', 'sample_value' => 'iOS 17.3.1'],
                    ['key' => 'warranty_info', 'label' => '保修信息', 'sample_value' => '2025-12-31'],
                    ['key' => 'capacity', 'label' => '内存/规格', 'sample_value' => '256GB'],
                    ['key' => 'color', 'label' => '颜色', 'sample_value' => '深空黑色'],
                    ['key' => 'device_index', 'label' => '设备序号', 'sample_value' => '1'],
                    ['key' => 'device_total', 'label' => '设备总数', 'sample_value' => '4'],
                    ['key' => 'device_number', 'label' => '设备编号', 'sample_value' => '1/4'],
                    ['key' => 'battery', 'label' => '电池健康度', 'sample_value' => '100'],
                    ['key' => 'battery_num', 'label' => '循环次数', 'sample_value' => '10'],
                ]
            ],
            [
                'label' => '质检信息',
                'items' => [
                    ['key' => 'check_result', 'label' => '质检结果', 'sample_value' => '外观良好功能正常'],
                    ['key' => 'check_result_seller', 'label' => '卖家可见质检', 'sample_value' => '外观良好'],
                    ['key' => 'check_result_buyer', 'label' => '买家可见质检', 'sample_value' => '功能正常'],
                    ['key' => 'check_info', 'label' => '验机信息', 'sample_value' => '外观良好功能正常'],
                    ['key' => 'inspection_info', 'label' => '验机信息别名', 'sample_value' => '外观良好功能正常'],
                    ['key' => 'check_staff', 'label' => '质检员', 'sample_value' => '李四'],
                    ['key' => 'check_date', 'label' => '质检日期', 'sample_value' => '2026-01-01'],
                    ['key' => 'check_time', 'label' => '质检时间', 'sample_value' => '2026-01-01 12:00'],
                    ['key' => 'check_status', 'label' => '质检状态', 'sample_value' => '已质检'],
                ]
            ],
            [
                'label' => '价格信息',
                'items' => [
                    ['key' => 'initial_price', 'label' => '预估价格', 'sample_value' => '5000.00'],
                    ['key' => 'final_price', 'label' => '最终价格', 'sample_value' => '4800.00'],
                    ['key' => 'sell_price', 'label' => '卖货价格', 'sample_value' => '5200.00'],
                    ['key' => 'price', 'label' => '价格', 'sample_value' => '4800.00'],
                    ['key' => 'before_price', 'label' => '之前定价', 'sample_value' => '4500.00'],
                    ['key' => 'price_remark', 'label' => '价格备注', 'sample_value' => '市场行情调整'],
                    ['key' => 'price_staff', 'label' => '定价员', 'sample_value' => '王五'],
                    ['key' => 'price_time', 'label' => '定价时间', 'sample_value' => '2026-01-02 10:00'],
                ]
            ],
            [
                'label' => '快递信息',
                'items' => [
                    ['key' => 'express_company', 'label' => '快递公司', 'sample_value' => '顺丰速运'],
                    ['key' => 'express_no', 'label' => '快递单号', 'sample_value' => 'SF1234567890'],
                    ['key' => 'delivery_type', 'label' => '发货方式', 'sample_value' => '快递'],
                    ['key' => 'delivery_fee', 'label' => '快递费用', 'sample_value' => '15.00'],
                    ['key' => 'delivery_status', 'label' => '快递状态', 'sample_value' => '已签收'],
                    ['key' => 'delivery_operator', 'label' => '快递操作人', 'sample_value' => '张三'],
                ]
            ],
            [
                'label' => '状态信息',
                'items' => [
                    ['key' => 'status', 'label' => '设备状态', 'sample_value' => '已回收'],
                    ['key' => 'status_name', 'label' => '状态名称', 'sample_value' => '已回收'],
                    ['key' => 'final_status', 'label' => '最终状态', 'sample_value' => '已确认'],
                    ['key' => 'pay_status', 'label' => '打款状态', 'sample_value' => '已打款'],
                    ['key' => 'confirm_status', 'label' => '确认状态', 'sample_value' => '已确认'],
                    ['key' => 'dispose_type', 'label' => '处置方式', 'sample_value' => '回收'],
                ]
            ],
            [
                'label' => '退货信息',
                'items' => [
                    ['key' => 'return_order_no', 'label' => '退货单号', 'sample_value' => 'RT20260101120000'],
                    ['key' => 'return_status', 'label' => '退货状态', 'sample_value' => '待退回'],
                    ['key' => 'return_reason', 'label' => '退货原因', 'sample_value' => '客户不同意报价'],
                    ['key' => 'return_remark', 'label' => '退货备注', 'sample_value' => '设备原路退回'],
                    ['key' => 'return_express_company', 'label' => '退货快递公司', 'sample_value' => '顺丰速运'],
                    ['key' => 'return_express_no', 'label' => '退货快递单号', 'sample_value' => 'SF9876543210'],
                    ['key' => 'return_time', 'label' => '退货时间', 'sample_value' => '2026-01-05 10:00'],
                    ['key' => 'return_address', 'label' => '退货地址', 'sample_value' => '广东省深圳市南山区xx路xx号'],
                    ['key' => 'return_receiver', 'label' => '退货收件人', 'sample_value' => '张三'],
                    ['key' => 'return_phone', 'label' => '退货联系电话', 'sample_value' => '138****8000'],
                ]
            ],
            [
                'label' => '代卖信息',
                'items' => [
                    ['key' => 'consignment_no', 'label' => '代卖单号', 'sample_value' => 'CS20260101120000'],
                    ['key' => 'consignment_status', 'label' => '代卖状态', 'sample_value' => '挂牌中'],
                    ['key' => 'listing_price', 'label' => '挂牌价', 'sample_value' => '5500.00'],
                    ['key' => 'sold_price', 'label' => '成交价', 'sample_value' => '5200.00'],
                    ['key' => 'expected_price', 'label' => '客户期望价', 'sample_value' => '5000.00'],
                    ['key' => 'min_settlement_price', 'label' => '最低结算价', 'sample_value' => '4800.00'],
                    ['key' => 'settlement_amount', 'label' => '客户结算金额', 'sample_value' => '5000.00'],
                    ['key' => 'service_fee', 'label' => '服务收益', 'sample_value' => '200.00'],
                    ['key' => 'consignment_remark', 'label' => '代卖备注', 'sample_value' => '客户要求5000以上出'],
                    ['key' => 'consignment_create_time', 'label' => '代卖创建时间', 'sample_value' => '2026-01-03 10:00'],
                    ['key' => 'consignment_sold_time', 'label' => '成交时间', 'sample_value' => '2026-01-10 14:00'],
                    ['key' => 'consignment_settle_time', 'label' => '结算时间', 'sample_value' => '2026-01-11 10:00'],
                ]
            ],
            [
                'label' => '时间信息',
                'items' => [
                    ['key' => 'create_time', 'label' => '创建时间', 'sample_value' => '2026-01-01 12:00'],
                    ['key' => 'update_time', 'label' => '更新时间', 'sample_value' => '2026-01-02 15:30'],
                    ['key' => 'sign_time', 'label' => '签收时间', 'sample_value' => '2026-01-01 18:00'],
                    ['key' => 'complete_time', 'label' => '完成时间', 'sample_value' => '2026-01-03 10:00'],
                    ['key' => 'pay_time', 'label' => '打款时间', 'sample_value' => '2026-01-03 14:00'],
                    ['key' => 'current_time', 'label' => '当前时间', 'sample_value' => '2026-01-01 12:00:00'],
                    ['key' => 'current_date', 'label' => '当前日期', 'sample_value' => '2026-01-01'],
                    ['key' => 'date', 'label' => '日期', 'sample_value' => '2026-01-01'],
                    ['key' => 'time', 'label' => '时间', 'sample_value' => '12:00:00'],
                ]
            ],
            [
                'label' => '其他信息',
                'items' => [
                    ['key' => 'remark', 'label' => '备注', 'sample_value' => '无'],
                    ['key' => 'member_id', 'label' => '会员ID', 'sample_value' => '10001'],
                    ['key' => 'site_name', 'label' => '站点名称', 'sample_value' => '回收中心'],
                    ['key' => 'staff_name', 'label' => '员工姓名', 'sample_value' => '李四'],
                ]
            ],
        ];
    }

    /**
     * 获取示例数据映射（key => sample_value）
     * @return array
     */
    public static function getSampleData(): array
    {
        $sampleData = [];
        foreach (self::getVariableGroups() as $group) {
            foreach ($group['items'] as $item) {
                $sampleData[$item['key']] = $item['sample_value'];
            }
        }
        return $sampleData;
    }
}
