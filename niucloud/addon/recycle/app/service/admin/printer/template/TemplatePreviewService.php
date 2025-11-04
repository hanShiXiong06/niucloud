<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\printer\template;

use core\base\BaseAdminService;

/**
 * 模板预览服务类
 * 负责生成预览数据和处理预览相关逻辑
 * Class TemplatePreviewService
 * @package addon\recycle\app\service\admin\printer\template
 */
class TemplatePreviewService extends BaseAdminService
{
    /**
     * 渲染服务
     * @var TemplateRenderService
     */
    protected $renderService;

    /**
     * 变量替换服务
     * @var VariableReplaceService
     */
    protected $variableReplaceService;

    public function __construct()
    {
        parent::__construct();
        $this->renderService = new TemplateRenderService();
        $this->variableReplaceService = new VariableReplaceService();
    }

    /**
     * 生成模板预览HTML
     * @param array $templateData JSON格式的模板数据
     * @param array $customVariables 自定义变量数据（可选）
     * @param float $scale 缩放比例
     * @return array ['html' => string, 'variables' => array]
     */
    public function generatePreview(array $templateData, array $customVariables = [], float $scale = 1.0): array
    {
        // 提取模板中的变量
        $templateVariables = $this->extractVariables($templateData);
        
        // 生成预览数据
        $previewVariables = $this->generatePreviewVariables($templateVariables, $customVariables);
        
        // 渲染HTML
        $html = $this->renderService->renderToHtml($templateData, $previewVariables, $scale);
        
        return [
            'html' => $html,
            'variables' => $previewVariables,
            'template_variables' => $templateVariables
        ];
    }

    /**
     * 从模板数据中提取变量
     * @param array $templateData JSON格式的模板数据
     * @return array 变量列表
     */
    public function extractVariables(array $templateData): array
    {
        $variables = [];
        $elements = $templateData['elements'] ?? [];
        
        foreach ($elements as $element) {
            $content = $element['content'] ?? '';
            
            // 匹配 {{variable}} 格式的变量
            preg_match_all('/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/', $content, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $variable) {
                    if (!in_array($variable, $variables)) {
                        $variables[] = $variable;
                    }
                }
            }
        }
        
        return $variables;
    }

    /**
     * 生成预览用的变量数据
     * @param array $variableNames 变量名列表
     * @param array $customVariables 自定义变量数据
     * @return array 预览变量数据
     */
    public function generatePreviewVariables(array $variableNames, array $customVariables = []): array
    {
        $defaultData = [
            // 设备相关
            'device_id' => 'DEV001',
            'device_name' => '设备名称',
            'model' => 'iPhone 14 Pro Max',
            'brand' => 'Apple',
            'color' => '深空黑',
            'memory' => '256GB',
            'imei' => '123456789012345',
            'sn' => 'SN123456789',
            
            // 订单相关
            'order_no' => 'ORD20250103001',
            'order_id' => '100001',
            
            // 人员相关
            'price_staff_name' => '张三',
            'check_staff' => '李四',
            'operator_name' => '王五',
            
            // 时间相关
            'check_date' => date('Y-m-d H:i:s'),
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            
            // 质检相关
            'check_result' => '质检通过',
            'check_status' => '正常',
            
            // 价格相关
            'total_price' => '5000.00',
            'price' => '5000.00',
            'final_price' => '4800.00',
            
            // 其他
            'remark' => '备注信息',
            'address' => '北京市朝阳区xxx街道xxx号',
            'phone' => '13800138000',
            'description' => '这是一段测试描述文字，用于预览模板效果。',
        ];
        
        // 合并自定义变量
        $previewData = array_merge($defaultData, $customVariables);
        
        // 只返回模板中需要的变量
        $result = [];
        foreach ($variableNames as $varName) {
            if (isset($previewData[$varName])) {
                $result[$varName] = $previewData[$varName];
            } else {
                // 如果变量不存在，使用占位符
                $result[$varName] = "[{$varName}]";
            }
        }
        
        return $result;
    }

    /**
     * 生成多页预览
     * @param array $templateData JSON格式的模板数据（支持多页）
     * @param array $customVariables 自定义变量数据
     * @param float $scale 缩放比例
     * @return array ['pages' => array, 'variables' => array]
     */
    public function generateMultiPagePreview(array $templateData, array $customVariables = [], float $scale = 1.0): array
    {
        $pages = [];
        
        // 支持多页格式
        if (isset($templateData['pages']) && is_array($templateData['pages'])) {
            foreach ($templateData['pages'] as $pageIndex => $pageData) {
                $pages[] = [
                    'page_index' => $pageIndex,
                    'html' => $this->renderService->renderToHtml($pageData, $customVariables, $scale),
                    'width' => $pageData['width'] ?? 58,
                    'height' => $pageData['height'] ?? 40
                ];
            }
        } else {
            // 单页格式
            $pages[] = [
                'page_index' => 0,
                'html' => $this->renderService->renderToHtml($templateData, $customVariables, $scale),
                'width' => $templateData['width'] ?? 58,
                'height' => $templateData['height'] ?? 40
            ];
        }
        
        // 提取所有变量
        $allVariables = [];
        foreach ($pages as $page) {
            $pageVariables = $this->extractVariables(['elements' => $page['elements'] ?? []]);
            $allVariables = array_merge($allVariables, $pageVariables);
        }
        $allVariables = array_unique($allVariables);
        
        // 生成预览变量
        $previewVariables = $this->generatePreviewVariables($allVariables, $customVariables);
        
        return [
            'pages' => $pages,
            'variables' => $previewVariables,
            'template_variables' => $allVariables
        ];
    }
}

