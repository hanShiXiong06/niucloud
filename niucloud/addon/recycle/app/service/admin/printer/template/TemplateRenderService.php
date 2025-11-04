<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\printer\template;

use core\base\BaseAdminService;

/**
 * 模板渲染服务类
 * 负责模板的HTML渲染和预览生成
 * Class TemplateRenderService
 * @package addon\recycle\app\service\admin\printer\template
 */
class TemplateRenderService extends BaseAdminService
{
    /**
     * 布局服务
     * @var TemplateLayoutService
     */
    protected $layoutService;

    public function __construct()
    {
        parent::__construct();
        $this->layoutService = new TemplateLayoutService();
    }

    /**
     * 将JSON模板数据渲染为HTML预览
     * @param array $templateData JSON格式的模板数据
     * @param array $variables 变量数据（用于预览）
     * @param float $scale 缩放比例（默认1.0，用于网页显示）
     * @return string HTML内容
     */
    public function renderToHtml(array $templateData, array $variables = [], float $scale = 1.0): string
    {
        if (empty($templateData)) {
            return '<div class="label-preview">无效的模板数据</div>';
        }
        
        // 获取纸张尺寸（mm）
        $widthMm = $templateData['width'] ?? 58;
        $heightMm = $templateData['height'] ?? 40;
        
        // 转换为dot单位
        $widthDot = $this->layoutService->mmToDot($widthMm);
        $heightDot = $this->layoutService->mmToDot($heightMm);
        
        // 转换为px用于显示（考虑缩放）
        $widthPx = $this->layoutService->dotToPx($widthDot) * $scale;
        $heightPx = $this->layoutService->dotToPx($heightDot) * $scale;
        
        $html = '<div class="label-preview-container" style="padding: 20px; background: #f5f5f5; display: flex; justify-content: center;">';
        $html .= '<div class="label-preview" style="position: relative; border: 2px solid #333; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
        $html .= "<div style=\"width: {$widthPx}px; height: {$heightPx}px; position: relative; overflow: hidden;\">";
        
        // 添加纸张尺寸标签
        $html .= "<div style=\"position: absolute; top: -18px; left: 0; font-size: 12px; color: #666;\">{$widthMm}mm × {$heightMm}mm</div>";
        
        $elements = $templateData['elements'] ?? [];
        foreach ($elements as $element) {
            // 传递模板宽度给元素，用于计算最大宽度
            $element['template_width'] = $widthMm;
            $element['template_height'] = $heightMm;
            $html .= $this->renderElement($element, $variables, $scale);
        }
        
        $html .= '</div></div></div>';
        
        return $html;
    }

    /**
     * 渲染单个元素为HTML
     * @param array $element 元素数据
     * @param array $variables 变量数据
     * @param float $scale 缩放比例
     * @return string HTML片段
     */
    private function renderElement(array $element, array $variables = [], float $scale = 1.0): string
    {
        $type = $element['type'] ?? 'text';
        
        // 坐标转换：dot转px
        $x = $this->layoutService->dotToPx($element['x'] ?? 0) * $scale;
        $y = $this->layoutService->dotToPx($element['y'] ?? 0) * $scale;
        
        switch ($type) {
            case 'text':
            case 'variable':
                return $this->renderTextElement($element, $x, $y, $variables, $scale);
                
            case 'qrcode':
                return $this->renderQrcodeElement($element, $x, $y, $scale);
                
            case 'barcode':
            case 'bc128':
            case 'bc39':
                return $this->renderBarcodeElement($element, $x, $y, $scale);
                
            case 'line':
            case 'l':
                return $this->renderLineElement($element, $x, $y, $scale);
                
            case 'rectangle':
            case 'seq':
                return $this->renderRectangleElement($element, $x, $y, $scale);
                
            case 'image':
            case 'img':
                return $this->renderImageElement($element, $x, $y, $scale);
                
            default:
                return '';
        }
    }

    /**
     * 渲染文本元素（改进版：更好的换行处理）
     * @param array $element
     * @param float $x
     * @param float $y
     * @param array $variables
     * @param float $scale
     * @return string
     */
    private function renderTextElement(array $element, float $x, float $y, array $variables = [], float $scale = 1.0): string
    {
        $content = $element['content'] ?? '';
        
        // 替换变量
        $content = $this->replaceVariables($content, $variables);
        
        // 获取字体属性
        $font = (int)($element['font'] ?? 9); // 默认字体9（简体中文24dot）
        $widthScale = (int)($element['width_scale'] ?? 1);
        $heightScale = (int)($element['height_scale'] ?? 1);
        $rotation = (int)($element['rotation'] ?? 0);
        
        // 计算字体大小（dot转px）
        $fontSizeDot = $this->getFontSizeDot($font);
        $fontSizePx = $this->layoutService->dotToPx($fontSizeDot * $heightScale) * $scale;
        
        // 获取模板宽度（用于计算最大宽度）
        $templateWidthDot = $this->layoutService->mmToDot($element['template_width'] ?? 58);
        $maxWidthDot = $templateWidthDot - ($element['x'] ?? 0);
        
        // 计算文本宽度和换行
        $textLines = $this->layoutService->calculateTextWrap(
            $content,
            $fontSizeDot,
            $maxWidthDot > 0 ? $maxWidthDot : 1000, // 如果没有限制，使用较大的值
            $widthScale,
            $heightScale
        );
        
        // 计算文本高度
        $textHeightPx = $this->layoutService->dotToPx(
            $this->layoutService->calculateTextHeight($textLines, $fontSizeDot, $heightScale)
        ) * $scale;
        
        // 构建transform样式：组合旋转和缩放（先缩放再旋转）
        $transforms = [];
        if ($widthScale != 1) {
            $transforms[] = "scaleX({$widthScale})";
        }
        if ($rotation !== 0) {
            $transforms[] = "rotate({$rotation}deg)";
        }
        $transformStyle = !empty($transforms) ? "transform: " . implode(' ', $transforms) . "; transform-origin: left top;" : "";
        
        // 构建外层容器样式
        $containerStyle = "position: absolute; left: {$x}px; top: {$y}px;";
        if ($transformStyle) {
            $containerStyle .= " {$transformStyle}";
        }
        
        // 构建HTML
        $html = "<div style=\"{$containerStyle}\">";
        
        foreach ($textLines as $index => $line) {
            $lineY = $index * $fontSizePx * 1.2; // 行高
            $lineText = htmlspecialchars($line['text'], ENT_QUOTES, 'UTF-8');
            
            // 内部行样式（不再使用scaleX，因为已经在外层处理）
            $html .= "<div style=\"position: absolute; left: 0; top: {$lineY}px; font-size: {$fontSizePx}px; white-space: nowrap; font-family: 'Microsoft YaHei', 'SimHei', 'Arial', sans-serif;\">";
            $html .= $lineText;
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * 渲染二维码元素（改进版：添加data属性供前端生成二维码）
     * @param array $element
     * @param float $x
     * @param float $y
     * @param float $scale
     * @return string
     */
    private function renderQrcodeElement(array $element, float $x, float $y, float $scale = 1.0): string
    {
        $size = (int)($element['size'] ?? 2);
        $content = $element['content'] ?? '';
        $contentLength = mb_strlen($content, 'UTF-8');
        
        // 计算二维码尺寸
        $qrcodeSize = $this->layoutService->calculateQrcodeSize($size, $contentLength);
        $sizePx = $this->layoutService->dotToPx($qrcodeSize['width']) * $scale;
        
        // 转义内容
        $escapedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        
        // 生成二维码HTML（前端可以使用qrcode.js生成）
        return "<div class=\"qrcode-element\" style=\"position: absolute; left: {$x}px; top: {$y}px; width: {$sizePx}px; height: {$sizePx}px; border: 1px solid #000; background: white; display: flex; align-items: center; justify-content: center;\" data-qrcode-content=\"{$escapedContent}\" data-qrcode-size=\"{$size}\" data-qrcode-error-level=\"" . htmlspecialchars($element['error_level'] ?? 'L') . "\"><canvas></canvas></div>";
    }

    /**
     * 渲染条形码元素（改进版：添加data属性供前端生成条形码）
     * @param array $element
     * @param float $x
     * @param float $y
     * @param float $scale
     * @return string
     */
    private function renderBarcodeElement(array $element, float $x, float $y, float $scale = 1.0): string
    {
        $height = (int)($element['height'] ?? 60);
        $narrowBarWidth = (int)($element['narrow_width'] ?? $element['narrow_bar_width'] ?? 1);
        $wideBarWidth = (int)($element['wide_width'] ?? $element['wide_bar_width'] ?? 2);
        $content = $element['content'] ?? '';
        $barcodeType = $element['barcode_type'] ?? 'BC128';
        $rotation = (int)($element['rotation'] ?? 0);
        
        // 计算条形码尺寸
        $barcodeSize = $this->layoutService->calculateBarcodeSize($height, $narrowBarWidth, $wideBarWidth, $content, $barcodeType);
        $widthPx = $this->layoutService->dotToPx($barcodeSize['width']) * $scale;
        $heightPx = $this->layoutService->dotToPx($barcodeSize['height']) * $scale;
        
        // 转义内容
        $escapedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        
        // 构建样式
        $style = "position: absolute; left: {$x}px; top: {$y}px; width: {$widthPx}px; height: {$heightPx}px; border: 1px solid #000; background: white; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #666;";
        
        // 如果有旋转角度，添加旋转样式
        if ($rotation !== 0) {
            $style .= " transform: rotate({$rotation}deg); transform-origin: left top;";
        }
        
        // 生成条形码HTML（前端可以使用JsBarcode生成）
        return "<div class=\"barcode-element\" style=\"{$style}\" data-barcode-content=\"{$escapedContent}\" data-barcode-type=\"" . htmlspecialchars($barcodeType) . "\" data-barcode-height=\"{$height}\" data-barcode-narrow=\"{$narrowBarWidth}\" data-barcode-wide=\"{$wideBarWidth}\"><svg></svg></div>";
    }

    /**
     * 渲染线条元素
     * @param array $element
     * @param float $x
     * @param float $y
     * @param float $scale
     * @return string
     */
    private function renderLineElement(array $element, float $x, float $y, float $scale = 1.0): string
    {
        $lineWidth = $element['width'] ?? 4;
        $lineHeight = $element['height'] ?? 2;
        
        $widthPx = $this->layoutService->dotToPx($lineWidth) * $scale;
        $heightPx = $this->layoutService->dotToPx($lineHeight) * $scale;
        
        return "<div style=\"position: absolute; left: {$x}px; top: {$y}px; width: {$widthPx}px; height: {$heightPx}px; background: #000;\"></div>";
    }

    /**
     * 渲染矩形元素
     * @param array $element
     * @param float $x
     * @param float $y
     * @param float $scale
     * @return string
     */
    private function renderRectangleElement(array $element, float $x, float $y, float $scale = 1.0): string
    {
        $xe = $element['xe'] ?? ($element['x'] ?? 0) + 80;
        $ye = $element['ye'] ?? ($element['y'] ?? 0) + 40;
        $lineWidth = $element['line_width'] ?? 4;
        
        $xePx = $this->layoutService->dotToPx($xe) * $scale;
        $yePx = $this->layoutService->dotToPx($ye) * $scale;
        $borderWidthPx = $this->layoutService->dotToPx($lineWidth) * $scale;
        
        $widthPx = $xePx - $x;
        $heightPx = $yePx - $y;
        
        return "<div style=\"position: absolute; left: {$x}px; top: {$y}px; width: {$widthPx}px; height: {$heightPx}px; border: {$borderWidthPx}px solid #000;\"></div>";
    }

    /**
     * 渲染图片元素
     * @param array $element
     * @param float $x
     * @param float $y
     * @param float $scale
     * @return string
     */
    private function renderImageElement(array $element, float $x, float $y, float $scale = 1.0): string
    {
        $width = $element['width'] ?? 100;
        $widthPx = $this->layoutService->dotToPx($width) * $scale;
        
        return "<div style=\"position: absolute; left: {$x}px; top: {$y}px; width: {$widthPx}px; height: {$widthPx}px; border: 1px dashed #999; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;\">IMG</div>";
    }

    /**
     * 获取字体大小（dot单位）
     * @param int $font 字体编号
     * @return int dot单位
     */
    private function getFontSizeDot(int $font): int
    {
        // 根据芯烨云字体规格
        $fontSizes = [
            1 => 12,  // 8 x 12 dot
            2 => 20,  // 12 x 20 dot
            3 => 24,  // 16 x 24 dot
            4 => 32,  // 24 x 32 dot
            5 => 48,  // 32 x 48 dot
            6 => 19,  // 14 x 19 dot OCR-B
            7 => 27,  // 21 x 27 dot OCR-B
            8 => 25,  // 14 x 25 dot OCR-A
            9 => 24,  // 简体中文 24dot x 24dot
        ];
        
        return $fontSizes[$font] ?? 24;
    }

    /**
     * 处理变量替换
     * @param string $content 包含变量的内容
     * @param array $variables 变量数据
     * @return string 替换后的内容
     */
    private function replaceVariables(string $content, array $variables = []): string
    {
        if (empty($variables)) {
            return $content;
        }
        
        foreach ($variables as $key => $value) {
            $variable_pattern = '{{' . $key . '}}';
            if (strpos($content, $variable_pattern) !== false) {
                $valueStr = is_array($value) || is_object($value) 
                    ? json_encode($value, JSON_UNESCAPED_UNICODE) 
                    : (string)$value;
                $content = str_replace($variable_pattern, $valueStr, $content);
            }
        }
        
        return $content;
    }

    /**
     * 生成预览用的测试数据
     * @param array $variables 变量列表
     * @return array 测试数据
     */
    public function generatePreviewData(array $variables = []): array
    {
        $defaultData = [
            'device_id' => 'DEV001',
            'model' => 'iPhone 14 Pro Max',
            'imei' => '123456789012345',
            'order_no' => 'ORD20250103001',
            'price_staff_name' => '张三',
            'check_date' => date('Y-m-d H:i:s'),
            'check_staff' => '李四',
            'check_result' => '质检通过',
            'total_price' => '5000.00',
            'create_time' => date('Y-m-d H:i:s')
        ];
        
        return array_merge($defaultData, $variables);
    }
}

