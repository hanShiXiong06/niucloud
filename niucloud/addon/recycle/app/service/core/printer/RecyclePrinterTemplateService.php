<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\printer;

use addon\recycle\app\model\RecyclePrinterTemplate;
use core\base\BaseService;

/**
 * 回收打印模板核心服务类
 */
class RecyclePrinterTemplateService extends BaseService
{
    /**
     * 获取模板类型列表
     * @return array
     */
    public function getTypeList(): array
    {
        return [
            'device_label' => '设备标签',
            'order_receipt' => '订单小票', 
            'return_label' => '退回标签',
            'custom' => '自定义'
        ];
    }

    /**
     * 获取模板状态列表
     * @return array
     */
    public function getStatusList(): array
    {
        return [
            0 => '禁用',
            1 => '启用'
        ];
    }

    /**
     * 获取可用变量列表
     * @param string $template_type
     * @return array
     */
    public function getAvailableVariables(string $template_type = ''): array
    {
        $variables = [
            // 订单相关变量
            'order' => [
                'name' => '订单信息',
                'variables' => [
                    'order_no' => ['name' => '订单号', 'example' => 'RC' . date('YmdHis')],
                    'customer_name' => ['name' => '客户姓名', 'example' => '张三'],
                    'customer_phone' => ['name' => '客户电话', 'example' => '13800138000'],
                    'order_status' => ['name' => '订单状态', 'example' => '已完成'],
                    'total_amount' => ['name' => '订单总额', 'example' => '5000.00'],
                    'device_count' => ['name' => '设备数量', 'example' => '3'],
                    'express_company' => ['name' => '快递公司', 'example' => '顺丰速运'],
                    'express_no' => ['name' => '快递单号', 'example' => 'SF1234567890'],
                    'delivery_type' => ['name' => '配送方式', 'example' => '快递'],
                    'create_time' => ['name' => '创建时间', 'example' => date('Y-m-d H:i:s')],
                    'sign_time' => ['name' => '签收时间', 'example' => date('Y-m-d H:i:s')],
                    'complete_time' => ['name' => '完成时间', 'example' => date('Y-m-d H:i:s')],
                    'remark' => ['name' => '订单备注', 'example' => '请小心包装'],
                ]
            ],
            // 设备相关变量
            'device' => [
                'name' => '设备信息',
                'variables' => [
                    'imei' => ['name' => 'IMEI号', 'example' => '867851234567890', 'barcode' => true],
                    'sn' => ['name' => '序列号', 'example' => 'ABCDEF123456'],
                    'model' => ['name' => '设备型号', 'example' => 'iPhone 14 Pro Max'],
                    'brand' => ['name' => '品牌', 'example' => 'Apple'],
                    'category_name' => ['name' => '设备分类', 'example' => '手机'],
                    'color' => ['name' => '颜色', 'example' => '深空黑'],
                    'memory' => ['name' => '内存', 'example' => '8GB'],
                    'capacity' => ['name' => '存储容量', 'example' => '256GB'],
                    'condition_level' => ['name' => '成色等级', 'example' => '9成新'],
                    'device_status' => ['name' => '设备状态', 'example' => '已质检'],
                    'initial_price' => ['name' => '预估价格', 'example' => '4800.00'],
                    'final_price' => ['name' => '最终价格', 'example' => '5000.00'],
                    'weight' => ['name' => '重量', 'example' => '0.5kg'],
                ]
            ],
            // 质检相关变量
            'inspection' => [
                'name' => '质检信息',
                'variables' => [
                    'check_status' => ['name' => '质检状态', 'example' => '已质检'],
                    'check_result' => ['name' => '质检结果', 'example' => '外观良好，功能正常，电池健康度85%，屏幕无划痕'],
                    'check_time' => ['name' => '质检时间', 'example' => date('Y-m-d H:i:s')],
                    'check_staff' => ['name' => '质检员', 'example' => '李四'],
                    'price_time' => ['name' => '定价时间', 'example' => date('Y-m-d H:i:s')],
                    'price_staff' => ['name' => '定价员', 'example' => '王五'],
                    'price_remark' => ['name' => '定价备注', 'example' => '市场价格良好'],
                ]
            ],
            // 系统变量
            'system' => [
                'name' => '系统信息',
                'variables' => [
                    'current_time' => ['name' => '当前时间', 'example' => date('Y-m-d H:i:s')],
                    'current_date' => ['name' => '当前日期', 'example' => date('Y-m-d')],
                    'print_time' => ['name' => '打印时间', 'example' => date('Y-m-d H:i:s')],
                    'site_name' => ['name' => '站点名称', 'example' => '回收中心'],
                    'operator' => ['name' => '操作员', 'example' => '管理员'],
                ]
            ],
            // 条码/二维码变量
            'barcode' => [
                'name' => '条码信息',
                'variables' => [
                    'imei_barcode' => ['name' => 'IMEI条形码', 'example' => '867851234567890', 'type' => 'barcode'],
                    'imei_qrcode' => ['name' => 'IMEI二维码', 'example' => '867851234567890', 'type' => 'qrcode'],
                    'order_qrcode' => ['name' => '订单二维码', 'example' => 'RC' . date('YmdHis'), 'type' => 'qrcode'],
                    'device_qrcode' => ['name' => '设备二维码', 'example' => '867851234567890', 'type' => 'qrcode'],
                ]
            ]
        ];

        // 根据模板类型过滤变量
        if ($template_type) {
            switch ($template_type) {
                case 'device_label':
                    // 设备标签主要包含设备和质检信息
                    return [
                        'device' => $variables['device'],
                        'inspection' => $variables['inspection'],
                        'barcode' => $variables['barcode'],
                        'system' => $variables['system']
                    ];
                case 'order_receipt':
                    // 订单小票包含订单和设备汇总信息
                    return [
                        'order' => $variables['order'],
                        'device' => $variables['device'],
                        'system' => $variables['system']
                    ];
                case 'return_label':
                    // 退回标签主要包含订单和设备基本信息
                    return [
                        'order' => $variables['order'],
                        'device' => array_merge($variables['device'], [
                            'variables' => array_intersect_key($variables['device']['variables'], array_flip([
                                'imei', 'model', 'brand', 'category_name'
                            ]))
                        ]),
                        'barcode' => $variables['barcode'],
                        'system' => $variables['system']
                    ];
                default:
                    return $variables;
            }
        }

        return $variables;
    }

    /**
     * 变量替换
     * @param string $content
     * @param array $data
     * @return string
     */
    public function replaceVariables(string $content, array $data): string
    {
        // 替换变量占位符
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        return $content;
    }

    /**
     * 生成HTML预览
     * @param array $templateData
     * @param array $data
     * @return string
     */
    public function generateHtmlPreview(array $templateData, array $data = []): string
    {
        if (empty($data)) {
            $data = $this->getExampleData();
        }

        $html = '<div style="width: 384px; height: 360px; border: 1px solid #ddd; background: #fff; position: relative; margin: 20px auto;">';
        
        if (!empty($templateData['content'])) {
            $elements = json_decode($templateData['content'], true);
            if ($elements) {
                foreach ($elements as $element) {
                    $html .= $this->renderElementToHtml($element, $data);
                }
            }
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * 渲染元素为HTML
     * @param array $element
     * @param array $data
     * @return string
     */
    private function renderElementToHtml(array $element, array $data): string
    {
        $style = sprintf(
            'position: absolute; left: %spx; top: %spx; width: %spx; height: %spx;',
            $element['x'] * 1.6,
            $element['y'] * 1.6,
            $element['width'] * 1.6,
            $element['height'] * 1.6
        );

        $html = '';
        
        switch ($element['type']) {
            case 'text':
                $content = $this->replaceVariables($element['content'] ?? '', $data);
                $html = sprintf(
                    '<div style="%s font-size: %spx; text-align: %s; font-weight: %s;">%s</div>',
                    $style,
                    $element['fontSize'] ?? 12,
                    $element['align'] ?? 'left',
                    $element['bold'] ? 'bold' : 'normal',
                    htmlspecialchars($content)
                );
                break;
                
            case 'variable':
                $content = $data[$element['variable']] ?? '{{' . $element['variable'] . '}}';
                $html = sprintf(
                    '<div style="%s font-size: %spx; text-align: %s;">%s</div>',
                    $style,
                    $element['fontSize'] ?? 12,
                    $element['align'] ?? 'left',
                    htmlspecialchars($content)
                );
                break;
                
            case 'qrcode':
                $content = $this->replaceVariables($element['content'] ?? '', $data);
                $html = sprintf(
                    '<div style="%s background: #f0f0f0; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; font-size: 10px;">QR: %s</div>',
                    $style,
                    htmlspecialchars($content)
                );
                break;
                
            case 'barcode':
                $content = $this->replaceVariables($element['content'] ?? '', $data);
                $html = sprintf(
                    '<div style="%s background: #f0f0f0; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; font-size: 10px;">BAR: %s</div>',
                    $style,
                    htmlspecialchars($content)
                );
                break;
                
            case 'line':
                $html = sprintf(
                    '<div style="%s"><svg width="100%%" height="100%%"><line x1="0" y1="50%%" x2="100%%" y2="50%%" stroke="%s" stroke-width="%s"/></svg></div>',
                    $style,
                    $element['color'] ?? '#000',
                    $element['thickness'] ?? 1
                );
                break;
                
            case 'rect':
                $html = sprintf(
                    '<div style="%s border: %spx solid %s; background: %s;"></div>',
                    $style,
                    $element['thickness'] ?? 1,
                    $element['color'] ?? '#000',
                    $element['filled'] ? ($element['fillColor'] ?? '#f0f0f0') : 'transparent'
                );
                break;
        }
        
        return $html;
    }

    /**
     * 获取示例数据
     * @return array
     */
    private function getExampleData(): array
    {
        return [
            'order_no' => 'RC' . date('YmdHis'),
            'customer_name' => '张三',
            'customer_phone' => '13800138000',
            'imei' => '867851234567890',
            'model' => 'iPhone 14 Pro Max',
            'brand' => 'Apple',
            'color' => '深空黑',
            'memory' => '8GB',
            'capacity' => '256GB',
            'check_result' => '外观良好，功能正常',
            'check_staff' => '李四',
            'final_price' => '5000.00',
            'current_time' => date('Y-m-d H:i:s'),
            'site_name' => '回收中心'
        ];
    }
} 