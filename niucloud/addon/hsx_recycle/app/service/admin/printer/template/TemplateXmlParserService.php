<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\printer\template;

use core\base\BaseAdminService;

/**
 * XML模板解析服务类
 * 负责将XML格式的模板转换为JSON格式
 * 支持所有芯烨云XML标签，处理换行、变量等
 * Class TemplateXmlParserService
 * @package addon\hsx_recycle\app\service\admin\printer\template
 */
class TemplateXmlParserService extends BaseAdminService
{
    /**
     * 转换服务（用于文本转义）
     * @var TemplateConverterService
     */
    protected $converterService;

    public function __construct()
    {
        parent::__construct();
        $this->converterService = new TemplateConverterService();
    }

    /**
     * 将XML格式的模板转换为JSON格式
     * @param string $xmlContent XML格式的打印指令
     * @return array JSON格式的模板数据
     */
    public function parseXml(string $xmlContent): array
    {
        if (empty($xmlContent)) {
            return [
                'width' => 58,
                'height' => 40,
                'elements' => []
            ];
        }

        // 清理XML内容
        $xmlContent = trim($xmlContent);
        
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
            $attributes = trim($match[1]);
            $pageContent = trim($match[2]);

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

        // 解析TEXT标签（支持多行和换行符）
        $this->parseTextElements($xmlContent, $templateData);

        // 解析QRC标签
        $this->parseQrcodeElements($xmlContent, $templateData);

        // 解析BC128标签
        $this->parseBarcodeElements($xmlContent, $templateData, 'BC128');

        // 解析BC39标签
        $this->parseBarcodeElements($xmlContent, $templateData, 'BC39');

        // 解析IMG标签
        $this->parseImageElements($xmlContent, $templateData);

        // 解析L标签（线条）
        $this->parseLineElements($xmlContent, $templateData);

        // 解析SEQ标签（矩形）
        $this->parseRectangleElements($xmlContent, $templateData);

        return $templateData;
    }

    /**
     * 解析TEXT元素
     * @param string $xmlContent
     * @param array $templateData
     * @return void
     */
    private function parseTextElements(string $xmlContent, array &$templateData): void
    {
        // 使用非贪婪匹配，支持多行内容
        if (preg_match_all('/<TEXT\s+([^>]*?)>(.*?)<\/TEXT>/s', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = trim($match[1]);
                $content = $match[2];

                // 处理换行符：将XML中的换行符转换为\n
                $content = preg_replace('/[\r\n]+/', "\n", $content);

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

                // 如果文本包含换行符，分割成多行
                $lines = explode("\n", $content);

                // 处理每一行
                foreach ($lines as $index => $line) {
                    $line = trim($line);
                    if (empty($line) && $index > 0) {
                        // 空行，跳过但保持Y坐标递增
                        continue;
                    }

                    $element = [
                        'type' => 'text',
                        'content' => $this->converterService->unescapeXinYeText($line),
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
    }

    /**
     * 解析二维码元素
     * @param string $xmlContent
     * @param array $templateData
     * @return void
     */
    private function parseQrcodeElements(string $xmlContent, array &$templateData): void
    {
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
    }

    /**
     * 解析条形码元素
     * @param string $xmlContent
     * @param array $templateData
     * @param string $type BC128 或 BC39
     * @return void
     */
    private function parseBarcodeElements(string $xmlContent, array &$templateData, string $type): void
    {
        $tag = $type === 'BC39' ? 'BC39' : 'BC128';
        
        if (preg_match_all("/<{$tag}\s+([^>]*?)>(.*?)<\/{$tag}>/s", $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = trim($match[1]);
                $content = trim($match[2]);

                $element = [
                    'type' => 'barcode',
                    'barcode_type' => $type,
                    'content' => $content,
                    'x' => intval($this->extractAttribute($attributes, 'x', '0')),
                    'y' => intval($this->extractAttribute($attributes, 'y', '0')),
                    'height' => intval($this->extractAttribute($attributes, 'h', '60')),
                    'scale' => intval($this->extractAttribute($attributes, 's', '1')),
                    'narrow_width' => intval($this->extractAttribute($attributes, 'n', '1')),
                    'wide_width' => intval($this->extractAttribute($attributes, 'w', $type === 'BC39' ? '2' : '1')),
                    'rotation' => intval($this->extractAttribute($attributes, 'r', '0'))
                ];

                $templateData['elements'][] = $element;
            }
        }
    }

    /**
     * 解析图片元素
     * @param string $xmlContent
     * @param array $templateData
     * @return void
     */
    private function parseImageElements(string $xmlContent, array &$templateData): void
    {
        // 支持自闭合和闭合标签两种格式
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
    }

    /**
     * 解析线条元素
     * @param string $xmlContent
     * @param array $templateData
     * @return void
     */
    private function parseLineElements(string $xmlContent, array &$templateData): void
    {
        // 支持自闭合和闭合标签
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
    }

    /**
     * 解析矩形元素
     * @param string $xmlContent
     * @param array $templateData
     * @return void
     */
    private function parseRectangleElements(string $xmlContent, array &$templateData): void
    {
        // 支持自闭合和闭合标签
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
     * 提取模板中的变量
     * @param string $xmlContent XML内容
     * @return array 变量列表
     */
    public function extractVariables(string $xmlContent): array
    {
        $variables = [];
        
        // 从TEXT标签中提取变量
        if (preg_match_all('/<TEXT\s+[^>]*?>(.*?)<\/TEXT>/s', $xmlContent, $matches)) {
            foreach ($matches[1] as $content) {
                // 匹配 {{variable}} 格式的变量
                preg_match_all('/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/', $content, $varMatches);
                
                if (!empty($varMatches[1])) {
                    foreach ($varMatches[1] as $variable) {
                        if (!in_array($variable, $variables)) {
                            $variables[] = $variable;
                        }
                    }
                }
            }
        }
        
        // 从QRC标签中提取变量
        if (preg_match_all('/<QRC\s+[^>]*?>(.*?)<\/QRC>/s', $xmlContent, $matches)) {
            foreach ($matches[1] as $content) {
                preg_match_all('/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/', $content, $varMatches);
                
                if (!empty($varMatches[1])) {
                    foreach ($varMatches[1] as $variable) {
                        if (!in_array($variable, $variables)) {
                            $variables[] = $variable;
                        }
                    }
                }
            }
        }
        
        return $variables;
    }
}

