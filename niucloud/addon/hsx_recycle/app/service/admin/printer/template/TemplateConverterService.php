<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\printer\template;

use core\base\BaseAdminService;

/**
 * 模板转换服务类
 * 负责JSON格式和XML格式之间的转换
 * Class TemplateConverterService
 * @package addon\hsx_recycle\app\service\admin\printer\template
 */
class TemplateConverterService extends BaseAdminService
{
    /**
     * 将JSON格式的模板数据转换为芯烨云XML格式
     * @param array $templateData JSON格式的模板数据
     * @return string XML格式的打印指令
     */
    public function jsonToXml(array $templateData): string
    {
        // 支持多页格式：pages数组
        if (isset($templateData['pages']) && is_array($templateData['pages'])) {
            return $this->convertMultiPageToXml($templateData['pages']);
        }
        
        // 单页格式（兼容旧格式）
        return $this->convertSinglePageToXml($templateData);
    }

    /**
     * 转换单页模板为XML
     * @param array $templateData
     * @return string
     */
    private function convertSinglePageToXml(array $templateData): string
    {
        $xml = '';
        
        // 构建PAGE标签
        $copies = isset($templateData['copies']) ? intval($templateData['copies']) : 1;
        if ($copies > 1) {
            $xml .= "<PAGE n=\"{$copies}\">";
        } else {
            $xml .= '<PAGE>';
        }
        
        // 添加尺寸信息
        $width = $templateData['width'] ?? 58;
        $height = $templateData['height'] ?? 40;
        $xml .= "<SIZE>{$width},{$height}</SIZE>";
        
        // 处理元素列表
        $elements = $templateData['elements'] ?? [];
        
        foreach ($elements as $element) {
            $xml .= $this->convertElementToXml($element);
        }
        
        $xml .= '</PAGE>';
        
        return $xml;
    }

    /**
     * 转换多页模板为XML
     * @param array $pages 页面数组
     * @return string
     */
    private function convertMultiPageToXml(array $pages): string
    {
        $xml = '';
        
        foreach ($pages as $page) {
            // 构建PAGE标签，支持n属性（打印份数）
            $copies = isset($page['copies']) ? intval($page['copies']) : 1;
            if ($copies > 1) {
                $xml .= "<PAGE n=\"{$copies}\">";
            } else {
                $xml .= '<PAGE>';
            }
            
            // 每页可以有自己的尺寸
            if (isset($page['width']) || isset($page['height'])) {
                $width = $page['width'] ?? 58;
                $height = $page['height'] ?? 40;
                $xml .= "<SIZE>{$width},{$height}</SIZE>";
            }
            
            // 处理该页的元素列表
            $elements = $page['elements'] ?? [];
            foreach ($elements as $element) {
                $xml .= $this->convertElementToXml($element);
            }
            
            $xml .= '</PAGE>';
        }
        
        return $xml;
    }

    /**
     * 转换单个元素为XML
     * @param array $element 元素数据
     * @return string XML片段
     */
    private function convertElementToXml(array $element): string
    {
        $type = $element['type'] ?? 'text';
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        
        switch ($type) {
            case 'text':
            case 'variable':
                return $this->convertTextElement($element, $x, $y);
                
            case 'qrcode':
                return $this->convertQrcodeElement($element, $x, $y);
                
            case 'barcode':
                return $this->convertBarcodeElement($element, $x, $y);
                
            case 'image':
                return $this->convertImageElement($element, $x, $y);
                
            case 'line':
                return $this->convertLineElement($element, $x, $y);
                
            case 'rectangle':
                return $this->convertRectangleElement($element, $x, $y);
                
            default:
                return '';
        }
    }

    /**
     * 转换图片元素
     * @param array $element
     * @param int $x
     * @param int $y
     * @return string
     */
    private function convertImageElement(array $element, int $x, int $y): string
    {
        $width = $element['width'] ?? '50'; // logo图片宽度，默认50，范围20-100
        
        // 限制宽度范围
        $width = max(20, min(100, intval($width)));
        
        return "<IMG x=\"{$x}\" y=\"{$y}\" w=\"{$width}\"></IMG>";
    }

    /**
     * 转换文本元素
     * @param array $element
     * @param int $x
     * @param int $y
     * @return string
     */
    private function convertTextElement(array $element, int $x, int $y): string
    {
        $content = $element['content'] ?? '';
        $font = $element['font'] ?? ''; // 字体类型 1-9
        $width_scale = $element['width_scale'] ?? '1';
        $height_scale = $element['height_scale'] ?? '1';
        $rotation = $element['rotation'] ?? '0';
        
        $escaped_content = $this->escapeXinYeText($content);
        
        // 构建属性字符串
        $attrs = "x=\"{$x}\" y=\"{$y}\"";
        
        // 如果有font属性，添加（文档支持font属性）
        if (!empty($font) && in_array($font, ['1', '2', '3', '4', '5', '6', '7', '8', '9'])) {
            $attrs .= " font=\"{$font}\"";
        }
        
        $attrs .= " w=\"{$width_scale}\" h=\"{$height_scale}\" r=\"{$rotation}\"";
        
        return "<TEXT {$attrs}>{$escaped_content}</TEXT>";
    }

    /**
     * 转换二维码元素
     * @param array $element
     * @param int $x
     * @param int $y
     * @return string
     */
    private function convertQrcodeElement(array $element, int $x, int $y): string
    {
        $content = $element['content'] ?? '';
        $size = $element['size'] ?? '2';
        $error_level = $element['error_level'] ?? 'L';
        
        return "<QRC x=\"{$x}\" y=\"{$y}\" s=\"{$size}\" e=\"{$error_level}\">{$content}</QRC>";
    }

    /**
     * 转换条形码元素
     * @param array $element
     * @param int $x
     * @param int $y
     * @return string
     */
    private function convertBarcodeElement(array $element, int $x, int $y): string
    {
        $content = $element['content'] ?? '';
        $barcode_type = $element['barcode_type'] ?? 'BC128'; // BC128 或 BC39
        $height = $element['height'] ?? '60';
        $scale = $element['scale'] ?? '1'; // s属性：是否人眼可识（0不可识，1可识）
        $narrow_width = $element['narrow_width'] ?? '1'; // n属性：窄bar宽度
        $wide_width = $element['wide_width'] ?? '1'; // w属性：宽bar宽度（BC128默认1，BC39默认2）
        $rotation = $element['rotation'] ?? '0';
        
        // BC39的宽bar默认宽度为2
        if ($barcode_type === 'BC39' && $wide_width === '1') {
            $wide_width = '2';
        }
        
        // 构建属性字符串
        $attrs = "x=\"{$x}\" y=\"{$y}\" h=\"{$height}\" s=\"{$scale}\" n=\"{$narrow_width}\" w=\"{$wide_width}\"";
        
        // 如果有旋转角度，添加r属性
        if ($rotation !== '0') {
            $attrs .= " r=\"{$rotation}\"";
        }
        
        $tag = $barcode_type === 'BC39' ? 'BC39' : 'BC128';
        
        return "<{$tag} {$attrs}>{$content}</{$tag}>";
    }

    /**
     * 转换线条元素
     * @param array $element
     * @param int $x
     * @param int $y
     * @return string
     */
    private function convertLineElement(array $element, int $x, int $y): string
    {
        $width = $element['width'] ?? '4';
        $height = $element['height'] ?? '2';
        
        return "<L x=\"{$x}\" y=\"{$y}\" w=\"{$width}\" h=\"{$height}\"></L>";
    }

    /**
     * 转换矩形元素
     * @param array $element
     * @param int $x
     * @param int $y
     * @return string
     */
    private function convertRectangleElement(array $element, int $x, int $y): string
    {
        $xe = $element['xe'] ?? ($x + 80);
        $ye = $element['ye'] ?? ($y + 40);
        $style = $element['style'] ?? '4';
        
        return "<SEQ x=\"{$x}\" y=\"{$y}\" xe=\"{$xe}\" ye=\"{$ye}\" s=\"{$style}\"></SEQ>";
    }

    /**
     * XML格式转换为JSON格式（兼容旧数据）
     * @param string $xmlContent XML格式的打印指令
     * @return array JSON格式的模板数据
     */
    public function xmlToJson(string $xmlContent): array
    {
        // 检查是否包含多个PAGE标签（多页格式）
        $pageMatches = [];
        if (preg_match_all('/<PAGE\s*([^>]*?)>(.*?)<\/PAGE>/s', $xmlContent, $pageMatches, PREG_SET_ORDER)) {
            // 多页格式
            if (count($pageMatches) > 1) {
                return $this->parseMultiPageXml($pageMatches);
            }
        }
        
        // 单页格式
        return $this->parseSinglePageXml($xmlContent);
    }

    /**
     * 解析多页XML格式
     * @param array $pageMatches PAGE标签匹配结果
     * @return array
     */
    private function parseMultiPageXml(array $pageMatches): array
    {
        $pages = [];
        
        foreach ($pageMatches as $match) {
            $attributes = $match[1];
            $pageContent = $match[2];
            
            // 提取copies属性（n属性）
            $copies = $this->extractAttribute($attributes, 'n', '1');
            $copies = intval($copies) > 0 ? intval($copies) : 1;
            
            // 解析该页的数据
            $page = $this->parseSinglePageXml($pageContent);
            $page['copies'] = $copies;
            
            $pages[] = $page;
        }
        
        return [
            'pages' => $pages
        ];
    }

    /**
     * 解析单页XML格式
     * @param string $xmlContent
     * @return array
     */
    private function parseSinglePageXml(string $xmlContent): array
    {
        $templateData = [
            'width' => 58,
            'height' => 40,
            'elements' => []
        ];
        
        // 解析SIZE标签
        if (preg_match('/<SIZE>(\d+),(\d+)<\/SIZE>/', $xmlContent, $matches)) {
            $templateData['width'] = intval($matches[1]);
            $templateData['height'] = intval($matches[2]);
        }
        
        // 解析PAGE标签的n属性（打印份数）
        if (preg_match('/<PAGE\s+([^>]*?)>/', $xmlContent, $matches)) {
            $copies = $this->extractAttribute($matches[1], 'n', '1');
            if ($copies !== '1') {
                $templateData['copies'] = intval($copies);
            }
        }
        
        // 解析TEXT标签（改进：支持多行内容和嵌套标签）
        // 使用非贪婪匹配，但需要处理可能的嵌套情况
        if (preg_match_all('/<TEXT\s+([^>]*?)>(.*?)<\/TEXT>/s', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = trim($match[1]);
                $content = $match[2];
                
                // 处理换行符：将XML中的换行符转换为\n
                $content = preg_replace('/[\r\n]+/', "\n", $content);
                
                // 如果文本包含换行符，需要分割成多个元素或标记为多行
                $lines = explode("\n", $content);
                
                // 解析属性
                $x = intval($this->extractAttribute($attributes, 'x', '0'));
                $y = intval($this->extractAttribute($attributes, 'y', '0'));
                $font = $this->extractAttribute($attributes, 'font', '9');
                $widthScale = intval($this->extractAttribute($attributes, 'w', '1'));
                $heightScale = intval($this->extractAttribute($attributes, 'h', '1'));
                $rotation = intval($this->extractAttribute($attributes, 'r', '0'));
                
                // 计算字体大小（用于换行后的Y坐标计算）
                $fontSizeDot = $this->getFontSizeDot(intval($font));
                $lineHeight = (int)($fontSizeDot * $heightScale * 1.2); // 行高
                
                // 处理每一行
                foreach ($lines as $index => $line) {
                    $line = trim($line);
                    if (empty($line) && $index > 0) {
                        // 空行，跳过但保持Y坐标递增
                        continue;
                    }
                    
                    $element = [
                        'type' => 'text',
                        'content' => $this->unescapeXinYeText($line),
                        'x' => $x,
                        'y' => $y + ($index * $lineHeight), // 自动换行位置
                        'font' => $font ? intval($font) : 9,
                        'width_scale' => $widthScale,
                        'height_scale' => $heightScale,
                        'rotation' => $rotation
                    ];
                    
                    $templateData['elements'][] = $element;
                }
            }
        }
        
        // 解析QRC标签（改进：支持换行和嵌套）
        if (preg_match_all('/<QRC\s+([^>]*?)>(.*?)<\/QRC>/s', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = trim($match[1]);
                $content = trim($match[2]);
                
                $element = [
                    'type' => 'qrcode',
                    'content' => $content,
                    'x' => intval($this->extractAttribute($attributes, 'x', '0')),
                    'y' => intval($this->extractAttribute($attributes, 'y', '0')),
                    'size' => intval($this->extractAttribute($attributes, 's', '2')),
                    'error_level' => strtoupper($this->extractAttribute($attributes, 'e', 'L'))
                ];
                
                $templateData['elements'][] = $element;
            }
        }
        
        // 解析BC128标签
        if (preg_match_all('/<BC128\s+([^>]*?)>(.*?)<\/BC128>/s', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = trim($match[1]);
                $content = trim($match[2]);
                
                $element = [
                    'type' => 'barcode',
                    'barcode_type' => 'BC128',
                    'content' => $content,
                    'x' => intval($this->extractAttribute($attributes, 'x', '0')),
                    'y' => intval($this->extractAttribute($attributes, 'y', '0')),
                    'height' => intval($this->extractAttribute($attributes, 'h', '60')),
                    'scale' => intval($this->extractAttribute($attributes, 's', '1')),
                    'narrow_width' => intval($this->extractAttribute($attributes, 'n', '1')),
                    'wide_width' => intval($this->extractAttribute($attributes, 'w', '1')),
                    'rotation' => intval($this->extractAttribute($attributes, 'r', '0'))
                ];
                
                $templateData['elements'][] = $element;
            }
        }
        
        // 解析BC39标签
        if (preg_match_all('/<BC39\s+([^>]*?)>(.*?)<\/BC39>/s', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = trim($match[1]);
                $content = trim($match[2]);
                
                $element = [
                    'type' => 'barcode',
                    'barcode_type' => 'BC39',
                    'content' => $content,
                    'x' => intval($this->extractAttribute($attributes, 'x', '0')),
                    'y' => intval($this->extractAttribute($attributes, 'y', '0')),
                    'height' => intval($this->extractAttribute($attributes, 'h', '60')),
                    'scale' => intval($this->extractAttribute($attributes, 's', '1')),
                    'narrow_width' => intval($this->extractAttribute($attributes, 'n', '1')),
                    'wide_width' => intval($this->extractAttribute($attributes, 'w', '2')),
                    'rotation' => intval($this->extractAttribute($attributes, 'r', '0'))
                ];
                
                $templateData['elements'][] = $element;
            }
        }
        
        // 解析IMG标签（图片）- 支持自闭合和闭合标签两种格式
        if (preg_match_all('/<IMG\s+([^>]*?)(?:\/>|>.*?<\/IMG>)/s', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = trim($match[1]);
                
                $element = [
                    'type' => 'image',
                    'x' => intval($this->extractAttribute($attributes, 'x', '0')),
                    'y' => intval($this->extractAttribute($attributes, 'y', '0')),
                    'width' => intval($this->extractAttribute($attributes, 'w', '50'))
                ];
                
                $templateData['elements'][] = $element;
            }
        }
        
        // 解析L标签（线条）- 支持自闭合和闭合标签
        if (preg_match_all('/<L\s+([^>]*?)(?:\/>|>.*?<\/L>)/s', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = trim($match[1]);
                
                $element = [
                    'type' => 'line',
                    'x' => intval($this->extractAttribute($attributes, 'x', '0')),
                    'y' => intval($this->extractAttribute($attributes, 'y', '0')),
                    'width' => intval($this->extractAttribute($attributes, 'w', '4')),
                    'height' => intval($this->extractAttribute($attributes, 'h', '2'))
                ];
                
                $templateData['elements'][] = $element;
            }
        }
        
        // 解析SEQ标签（矩形）- 支持自闭合和闭合标签
        if (preg_match_all('/<SEQ\s+([^>]*?)(?:\/>|>.*?<\/SEQ>)/s', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = trim($match[1]);
                
                $element = [
                    'type' => 'rectangle',
                    'x' => intval($this->extractAttribute($attributes, 'x', '0')),
                    'y' => intval($this->extractAttribute($attributes, 'y', '0')),
                    'xe' => intval($this->extractAttribute($attributes, 'xe', '80')),
                    'ye' => intval($this->extractAttribute($attributes, 'ye', '40')),
                    'style' => intval($this->extractAttribute($attributes, 's', '4'))
                ];
                
                $templateData['elements'][] = $element;
            }
        }
        
        return $templateData;
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
     * 从属性字符串中提取指定属性值
     * @param string $attributes
     * @param string $name
     * @param string $default
     * @return string
     */
    private function extractAttribute(string $attributes, string $name, string $default = ''): string
    {
        if (preg_match("/{$name}=\"([^\"]*)\"/", $attributes, $matches)) {
            return $matches[1];
        }
        return $default;
    }

    /**
     * 芯烨云文本转义
     * @param string $text
     * @return string
     */
    public function escapeXinYeText(string $text): string
    {
        // 芯烨云要求：文本中的 < 用 &lt 表示，> 用 &gt 表示
        // 但要保护变量格式 {{variable}}
        
        // 先临时替换变量，避免被转义
        $placeholders = [];
        $placeholderIndex = 0;
        
        // 保护变量格式 {{variable}}
        $protected = preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use (&$placeholders, &$placeholderIndex) {
            $placeholder = '___VAR_' . $placeholderIndex . '___';
            $placeholders[$placeholder] = $matches[0];
            $placeholderIndex++;
            return $placeholder;
        }, $text);
        
        // 转义 < 和 >
        $escaped = str_replace(['<', '>'], ['&lt', '&gt'], $protected);
        
        // 恢复变量
        foreach ($placeholders as $placeholder => $original) {
            $escaped = str_replace($placeholder, $original, $escaped);
        }
        
        return $escaped;
    }

    /**
     * 芯烨云文本反转义
     * @param string $text
     * @return string
     */
    public function unescapeXinYeText(string $text): string
    {
        return str_replace(['&lt', '&gt'], ['<', '>'], $text);
    }
}

