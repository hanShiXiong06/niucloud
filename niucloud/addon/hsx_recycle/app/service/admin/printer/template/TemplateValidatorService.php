<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\printer\template;

use core\base\BaseAdminService;

/**
 * 模板验证服务类
 * 负责验证模板数据的完整性和正确性
 * Class TemplateValidatorService
 * @package addon\hsx_recycle\app\service\admin\printer\template
 */
class TemplateValidatorService extends BaseAdminService
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
     * 验证模板数据结构
     * @param array $templateData 模板数据
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validateTemplate(array $templateData): array
    {
        $errors = [];
        
        // 验证必需字段
        if (!isset($templateData['width']) || !is_numeric($templateData['width'])) {
            $errors[] = '模板宽度无效';
        }
        
        if (!isset($templateData['height']) || !is_numeric($templateData['height'])) {
            $errors[] = '模板高度无效';
        }
        
        // 验证宽度和高度范围（mm单位）
        if (isset($templateData['width']) && ($templateData['width'] < 1 || $templateData['width'] > 1000)) {
            $errors[] = '模板宽度必须在1-1000mm之间';
        }
        
        if (isset($templateData['height']) && ($templateData['height'] < 1 || $templateData['height'] > 1000)) {
            $errors[] = '模板高度必须在1-1000mm之间';
        }
        
        // 转换为dot单位用于验证
        $widthDot = isset($templateData['width']) ? $this->layoutService->mmToDot((float)$templateData['width']) : 0;
        $heightDot = isset($templateData['height']) ? $this->layoutService->mmToDot((float)$templateData['height']) : 0;
        
        // 验证元素列表
        if (isset($templateData['elements']) && is_array($templateData['elements'])) {
            foreach ($templateData['elements'] as $index => $element) {
                $elementResult = $this->validateElement($element);
                if (!$elementResult['valid']) {
                    $errors[] = "元素{$index}: " . implode(', ', $elementResult['errors']);
                }
                
                // 验证元素位置和边界（使用布局服务）
                if ($widthDot > 0 && $heightDot > 0) {
                    $boundsResult = $this->layoutService->checkElementBounds($element, $widthDot, $heightDot);
                    if ($boundsResult['out_of_bounds']) {
                        $errors[] = "元素{$index}: " . $boundsResult['message'];
                    }
                }
            }

            if ($widthDot > 0 && $heightDot > 0) {
                $qrcodeWarnings = $this->validateQrcodeSafety($templateData['elements'], $widthDot, $heightDot);
                foreach ($qrcodeWarnings as $warning) {
                    $errors[] = $warning;
                }
            }
            
            // 检测元素重叠（警告级别，不阻止保存）
            $overlaps = $this->validateOverlap($templateData['elements']);
            if (!empty($overlaps)) {
                foreach ($overlaps as $overlap) {
                    $errors[] = "警告: 元素{$overlap['element1']}和元素{$overlap['element2']}可能重叠";
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 验证元素数据
     * @param array $element 元素数据
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validateElement(array $element): array
    {
        $errors = [];
        
        // 验证元素类型
        $validTypes = ['text', 'variable', 'qrcode', 'barcode', 'bc128', 'bc39', 'line', 'l', 'rectangle', 'seq', 'image', 'img'];
        if (!isset($element['type']) || !in_array($element['type'], $validTypes)) {
            $errors[] = '无效的元素类型: ' . ($element['type'] ?? '未定义');
        }
        
        // 验证坐标（必须是数值）
        if (!isset($element['x']) || !is_numeric($element['x']) || $element['x'] < 0) {
            $errors[] = 'X坐标无效，必须为非负数';
        }
        
        if (!isset($element['y']) || !is_numeric($element['y']) || $element['y'] < 0) {
            $errors[] = 'Y坐标无效，必须为非负数';
        }
        
        // 根据元素类型进行特定验证
        $type = $element['type'] ?? '';
        
        switch ($type) {
            case 'text':
            case 'variable':
                if (!isset($element['content']) || $element['content'] === '') {
                    $errors[] = '文本内容不能为空';
                }
                // 验证字体编号
                if (isset($element['font']) && ($element['font'] < 1 || $element['font'] > 9)) {
                    $errors[] = '字体编号必须在1-9之间';
                }
                // 验证宽度和高度放大倍率
                if (isset($element['width_scale']) && ($element['width_scale'] < 1 || $element['width_scale'] > 10)) {
                    $errors[] = '宽度放大倍率必须在1-10之间';
                }
                if (isset($element['height_scale']) && ($element['height_scale'] < 1 || $element['height_scale'] > 10)) {
                    $errors[] = '高度放大倍率必须在1-10之间';
                }
                // 验证旋转角度
                if (isset($element['rotation']) && !in_array($element['rotation'], [0, 90, 180, 270])) {
                    $errors[] = '旋转角度必须是0、90、180或270度';
                }
                break;
                
            case 'qrcode':
                if (empty($element['content'])) {
                    $errors[] = '二维码内容不能为空';
                }
                $contentLength = mb_strlen($element['content'] ?? '', 'UTF-8');
                if ($contentLength > 256) {
                    $errors[] = '二维码内容不能超过256个字符';
                }
                // 验证二维码大小
                if (isset($element['size']) && ($element['size'] < 1 || $element['size'] > 10)) {
                    $errors[] = '二维码大小必须在1-10之间';
                }
                // 验证纠错等级
                if (isset($element['error_level']) && !in_array($element['error_level'], ['L', 'M', 'Q', 'H'])) {
                    $errors[] = '二维码纠错等级必须是L、M、Q或H';
                }
                if (isset($element['quiet_zone']) && ($element['quiet_zone'] < 0 || $element['quiet_zone'] > 10)) {
                    $errors[] = '二维码静区必须在0-10个模块之间';
                }
                break;
                
            case 'barcode':
            case 'bc128':
            case 'bc39':
                if (empty($element['content'])) {
                    $errors[] = '条形码内容不能为空';
                }
                // 验证条形码高度
                if (isset($element['height']) && ($element['height'] < 1 || $element['height'] > 1000)) {
                    $errors[] = '条形码高度必须在1-1000dot之间';
                }
                // 验证窄条宽度
                if (isset($element['narrow_bar_width']) && ($element['narrow_bar_width'] < 1 || $element['narrow_bar_width'] > 10)) {
                    $errors[] = '窄条宽度必须在1-10dot之间';
                }
                // 验证宽条宽度
                if (isset($element['wide_bar_width']) && ($element['wide_bar_width'] < 1 || $element['wide_bar_width'] > 10)) {
                    $errors[] = '宽条宽度必须在1-10dot之间';
                }
                break;
                
            case 'rectangle':
            case 'seq':
                if (!isset($element['xe']) || !is_numeric($element['xe'])) {
                    $errors[] = '矩形结束X坐标无效';
                }
                if (!isset($element['ye']) || !is_numeric($element['ye'])) {
                    $errors[] = '矩形结束Y坐标无效';
                }
                if (isset($element['xe']) && isset($element['x']) && $element['xe'] <= $element['x']) {
                    $errors[] = '矩形结束X坐标必须大于起始X坐标';
                }
                if (isset($element['ye']) && isset($element['y']) && $element['ye'] <= $element['y']) {
                    $errors[] = '矩形结束Y坐标必须大于起始Y坐标';
                }
                break;
                
            case 'line':
            case 'l':
                // 验证线条宽度和高度
                if (isset($element['width']) && ($element['width'] < 1 || $element['width'] > 1000)) {
                    $errors[] = '线条宽度必须在1-1000dot之间';
                }
                if (isset($element['height']) && ($element['height'] < 1 || $element['height'] > 1000)) {
                    $errors[] = '线条高度必须在1-1000dot之间';
                }
                break;
                
            case 'image':
            case 'img':
                // 验证图片宽度
                if (isset($element['width']) && ($element['width'] < 20 || $element['width'] > 100)) {
                    $errors[] = '图片宽度必须在20-100dot之间';
                }
                break;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 校验二维码安全区域，重点检查静区是否被其他元素占用
     * @param array $elements
     * @param int $templateWidth
     * @param int $templateHeight
     * @return array
     */
    private function validateQrcodeSafety(array $elements, int $templateWidth, int $templateHeight): array
    {
        $warnings = [];

        foreach ($elements as $index => $element) {
            if (($element['type'] ?? '') !== 'qrcode') {
                continue;
            }

            $safeBox = $this->getQrcodeSafeBoundingBox($element);
            $qrBox = $this->getElementBoundingBox($element);
            if (!$safeBox || !$qrBox) {
                continue;
            }

            if ($safeBox['left'] < 0 || $safeBox['top'] < 0 || $safeBox['right'] > $templateWidth || $safeBox['bottom'] > $templateHeight) {
                $warnings[] = "元素{$index}: 二维码静区超出纸张范围，可能无法识别";
            }

            foreach ($elements as $otherIndex => $otherElement) {
                if ($otherIndex === $index) {
                    continue;
                }
                $otherBox = $this->getElementBoundingBox($otherElement);
                if (!$otherBox) {
                    continue;
                }
                if ($this->boxesOverlap($safeBox, $otherBox) && !$this->boxesOverlap($qrBox, $otherBox)) {
                    $warnings[] = "警告: 元素{$otherIndex}侵入二维码元素{$index}的安全留白，可能影响扫码";
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * 验证坐标是否在模板范围内（已废弃，使用layoutService->checkElementBounds）
     * @param array $element 元素数据
     * @param int $templateWidth 模板宽度（dot单位）
     * @param int $templateHeight 模板高度（dot单位）
     * @return bool
     * @deprecated 使用 TemplateLayoutService::checkElementBounds 代替
     */
    public function validatePosition(array $element, int $templateWidth, int $templateHeight): bool
    {
        $boundsResult = $this->layoutService->checkElementBounds($element, $templateWidth, $templateHeight);
        return !$boundsResult['out_of_bounds'];
    }

    /**
     * 检测元素是否有重叠
     * @param array $elements 元素列表
     * @return array 重叠的元素索引列表
     */
    public function validateOverlap(array $elements): array
    {
        $overlaps = [];
        
        for ($i = 0; $i < count($elements); $i++) {
            for ($j = $i + 1; $j < count($elements); $j++) {
                if ($this->isOverlapping($elements[$i], $elements[$j])) {
                    $overlaps[] = [
                        'element1' => $i,
                        'element2' => $j
                    ];
                }
            }
        }
        
        return $overlaps;
    }

    /**
     * 判断两个元素是否重叠
     * @param array $element1
     * @param array $element2
     * @return bool
     */
    private function isOverlapping(array $element1, array $element2): bool
    {
        $type1 = $element1['type'] ?? '';
        $type2 = $element2['type'] ?? '';
        
        // 获取元素1的边界框
        $bbox1 = $this->getElementBoundingBox($element1);
        if (!$bbox1) {
            return false;
        }
        
        // 获取元素2的边界框
        $bbox2 = $this->getElementBoundingBox($element2);
        if (!$bbox2) {
            return false;
        }
        
        // 检查矩形是否重叠
        return $this->boxesOverlap($bbox1, $bbox2);
    }

    private function boxesOverlap(array $bbox1, array $bbox2): bool
    {
        return !($bbox1['right'] < $bbox2['left'] ||
            $bbox1['left'] > $bbox2['right'] ||
            $bbox1['bottom'] < $bbox2['top'] ||
            $bbox1['top'] > $bbox2['bottom']);
    }

    private function getQrcodeSafeBoundingBox(array $element): ?array
    {
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $size = (int)($element['size'] ?? 2);
        $quietZone = (int)($element['quiet_zone'] ?? 4);
        $contentLength = mb_strlen($element['content'] ?? '', 'UTF-8');
        $safeSize = $this->layoutService->calculateQrcodeSafeSize($size, $contentLength, $quietZone);

        return [
            'left' => $x - $safeSize['quiet_dot'],
            'top' => $y - $safeSize['quiet_dot'],
            'right' => $x + $safeSize['qrcode_width'] + $safeSize['quiet_dot'],
            'bottom' => $y + $safeSize['qrcode_height'] + $safeSize['quiet_dot'],
        ];
    }

    /**
     * 获取元素的边界框
     * @param array $element 元素数据
     * @return array|null ['left' => int, 'top' => int, 'right' => int, 'bottom' => int]
     */
    private function getElementBoundingBox(array $element): ?array
    {
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $type = $element['type'] ?? '';
        
        switch ($type) {
            case 'text':
            case 'variable':
                $font = $element['font'] ?? 9;
                $fontSizeDot = $this->getFontSizeDot($font);
                $widthScale = $element['width_scale'] ?? 1;
                $heightScale = $element['height_scale'] ?? 1;
                $content = $element['content'] ?? '';
                
                // 计算文本宽度
                $textWidth = $this->layoutService->calculateTextWidth($content, $fontSizeDot, $widthScale);
                
                // 计算文本高度（考虑换行）
                $maxWidth = 1000; // 假设最大宽度
                $textLines = $this->layoutService->calculateTextWrap($content, $fontSizeDot, $maxWidth, $widthScale, $heightScale);
                $textHeight = $this->layoutService->calculateTextHeight($textLines, $fontSizeDot, $heightScale);
                
                return [
                    'left' => $x,
                    'top' => $y,
                    'right' => $x + $textWidth,
                    'bottom' => $y + $textHeight
                ];
                
            case 'qrcode':
                $size = $element['size'] ?? 2;
                $contentLength = mb_strlen($element['content'] ?? '', 'UTF-8');
                $qrcodeSize = $this->layoutService->calculateQrcodeSize($size, $contentLength);
                
                return [
                    'left' => $x,
                    'top' => $y,
                    'right' => $x + $qrcodeSize['width'],
                    'bottom' => $y + $qrcodeSize['height']
                ];
                
            case 'barcode':
            case 'bc128':
            case 'bc39':
                $barcodeSize = $this->layoutService->calculateBarcodeSize(
                    $element['height'] ?? 60,
                    $element['narrow_bar_width'] ?? 1,
                    $element['wide_bar_width'] ?? 2,
                    $element['content'] ?? ''
                );
                
                return [
                    'left' => $x,
                    'top' => $y,
                    'right' => $x + $barcodeSize['width'],
                    'bottom' => $y + $barcodeSize['height']
                ];
                
            case 'rectangle':
            case 'seq':
                $xe = $element['xe'] ?? ($x + 80);
                $ye = $element['ye'] ?? ($y + 40);
                
                return [
                    'left' => $x,
                    'top' => $y,
                    'right' => $xe,
                    'bottom' => $ye
                ];
                
            case 'line':
            case 'l':
                $width = $element['width'] ?? 4;
                $height = $element['height'] ?? 2;
                
                return [
                    'left' => $x,
                    'top' => $y,
                    'right' => $x + $width,
                    'bottom' => $y + $height
                ];
                
            case 'image':
            case 'img':
                $width = $element['width'] ?? 100;
                
                return [
                    'left' => $x,
                    'top' => $y,
                    'right' => $x + $width,
                    'bottom' => $y + $width // 图片是正方形
                ];
                
            default:
                return null;
        }
    }

    /**
     * 获取字体大小（dot单位）
     * @param int $font 字体编号
     * @return int dot单位
     */
    private function getFontSizeDot(int $font): int
    {
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
     * 验证XML格式
     * @param string $xml XML内容
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validateXml(string $xml): array
    {
        $errors = [];
        
        if (empty(trim($xml))) {
            $errors[] = 'XML内容不能为空';
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }
        
        // 使用libxml解析XML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = @$dom->loadXML($xml);
        
        if (!$loaded) {
            $libxmlErrors = libxml_get_errors();
            foreach ($libxmlErrors as $error) {
                $errors[] = 'XML解析错误: ' . trim($error->message);
            }
            libxml_clear_errors();
        }
        
        // 验证基本结构
        if ($loaded) {
            $pages = $dom->getElementsByTagName('PAGE');
            if ($pages->length === 0) {
                $errors[] = 'XML中必须包含至少一个PAGE标签';
            }
            
            foreach ($pages as $pageIndex => $page) {
                $sizes = $page->getElementsByTagName('SIZE');
                if ($sizes->length === 0) {
                    $errors[] = "第{$pageIndex}页缺少SIZE标签";
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
