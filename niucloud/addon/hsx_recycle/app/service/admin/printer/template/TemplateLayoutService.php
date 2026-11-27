<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\printer\template;

use core\base\BaseAdminService;

/**
 * 模板布局服务类
 * 负责处理布局计算，如自动换行、位置对齐、坐标转换等
 * Class TemplateLayoutService
 * @package addon\hsx_recycle\app\service\admin\printer\template
 */
class TemplateLayoutService extends BaseAdminService
{
    /**
     * 常用纸张尺寸预设（mm）
     * @var array
     */
    public static $paperSizes = [
        '40x30' => ['width' => 40, 'height' => 30, 'name' => '40mm × 30mm'],
        '50x30' => ['width' => 50, 'height' => 30, 'name' => '50mm × 30mm'],
        '58x40' => ['width' => 58, 'height' => 40, 'name' => '58mm × 40mm'],
        '60x45' => ['width' => 60, 'height' => 45, 'name' => '60mm × 45mm'],
        '80x50' => ['width' => 80, 'height' => 50, 'name' => '80mm × 50mm'],
        '100x50' => ['width' => 100, 'height' => 50, 'name' => '100mm × 50mm'],
    ];

    /**
     * 获取纸张尺寸预设列表
     * @return array
     */
    public function getPaperSizePresets(): array
    {
        return self::$paperSizes;
    }

    /**
     * 获取指定尺寸的纸张信息
     * @param string $key 尺寸键（如 '40x30'）
     * @return array|null
     */
    public function getPaperSize(string $key): ?array
    {
        return self::$paperSizes[$key] ?? null;
    }
    /**
     * 坐标转换：毫米转点（dot）
     * 1mm = 8dot（芯烨云标准）
     * @param float $mm 毫米值
     * @return int dot值
     */
    public function mmToDot(float $mm): int
    {
        return (int)($mm * 8);
    }

    /**
     * 坐标转换：点（dot）转毫米
     * @param int $dot 点值
     * @return float 毫米值
     */
    public function dotToMm(int $dot): float
    {
        return $dot / 8.0;
    }

    /**
     * 坐标转换：点（dot）转像素（用于网页显示）
     * 1mm = 8dot = 3.78px (96 DPI标准)
     * 为了更好的显示效果，使用 1mm = 4px 的比例
     * @param int $dot 点值
     * @return float 像素值
     */
    public function dotToPx(int $dot): float
    {
        return $dot / 2.0; // 1dot = 0.5px (1mm = 8dot = 4px)
    }

    /**
     * 坐标转换：像素转点（dot）
     * @param float $px 像素值
     * @return int dot值
     */
    public function pxToDot(float $px): int
    {
        return (int)($px * 2.0);
    }

    /**
     * 计算文本自动换行（改进版：更精确的换行计算）
     * @param string $text 文本内容
     * @param int $fontSize 字体大小（dot单位）
     * @param int $maxWidth 最大宽度（dot单位）
     * @param int $widthScale 宽度放大倍率（1-10）
     * @param int $heightScale 高度放大倍率（1-10）
     * @param string $fontFamily 字体名称
     * @return array 换行后的文本数组，每个元素包含 'text' 和 'width'
     */
    public function calculateTextWrap(string $text, int $fontSize, int $maxWidth, int $widthScale = 1, int $heightScale = 1, string $fontFamily = 'Arial'): array
    {
        if (empty($text)) {
            return [];
        }
        
        $lines = [];
        $currentLine = '';
        $length = mb_strlen($text, 'UTF-8');
        
        // 计算实际字体大小（考虑高度放大倍率）
        $actualFontSize = $fontSize * $heightScale;
        
        // 处理换行符：先按换行符分割
        $paragraphs = preg_split('/\r\n|\r|\n/', $text);
        
        foreach ($paragraphs as $paragraphIndex => $paragraph) {
            if (empty($paragraph)) {
                // 空段落，添加空行
                if ($paragraphIndex > 0) {
                    $lines[] = [
                        'text' => '',
                        'width' => 0
                    ];
                }
                continue;
            }
            
            $paraLength = mb_strlen($paragraph, 'UTF-8');
            $paraCurrentLine = '';
            
            for ($i = 0; $i < $paraLength; $i++) {
                $char = mb_substr($paragraph, $i, 1, 'UTF-8');
                
                // 检查当前行加上新字符是否超过长度限制
                $testLine = $paraCurrentLine . $char;
                $testWidth = $this->calculateTextWidth($testLine, $actualFontSize, $widthScale, $fontFamily);
                
                if ($testWidth > $maxWidth) {
                    // 当前行已满，保存并开始新行
                    if (!empty($paraCurrentLine)) {
                        $lines[] = [
                            'text' => $paraCurrentLine,
                            'width' => $this->calculateTextWidth($paraCurrentLine, $actualFontSize, $widthScale, $fontFamily)
                        ];
                        $paraCurrentLine = $char;
                    } else {
                        // 单个字符就超长（强制换行）
                        $lines[] = [
                            'text' => $char,
                            'width' => $this->calculateTextWidth($char, $actualFontSize, $widthScale, $fontFamily)
                        ];
                        $paraCurrentLine = '';
                    }
                } else {
                    $paraCurrentLine .= $char;
                }
            }
            
            // 添加段落最后一行
            if (!empty($paraCurrentLine)) {
                $lines[] = [
                    'text' => $paraCurrentLine,
                    'width' => $this->calculateTextWidth($paraCurrentLine, $actualFontSize, $widthScale, $fontFamily)
                ];
            }
        }
        
        return $lines;
    }

    /**
     * 估算字符宽度（改进版：区分中英文）
     * @param int $fontSize 字体大小（dot单位）
     * @param string $char 单个字符
     * @param string $fontFamily 字体名称
     * @return float 单个字符的宽度（dot单位）
     */
    private function estimateCharWidth(int $fontSize, string $char = '', string $fontFamily = 'Arial'): float
    {
        // 如果有指定字符，精确计算
        if (!empty($char)) {
            // 判断是否为中文字符（包括中文标点）
            if (preg_match('/[\x{4e00}-\x{9fa5}\x{3000}-\x{303f}\x{ff00}-\x{ffef}]/u', $char)) {
                // 中文字符宽度约等于字体大小
                return (float)$fontSize;
            } else {
                // 英文字符宽度约等于字体大小的0.6倍（根据芯烨云字体规格）
                return $fontSize * 0.6;
            }
        }
        
        // 默认估算：使用平均值0.8倍作为估算
        return $fontSize * 0.8;
    }
    
    /**
     * 计算文本渲染后的实际宽度（改进版：精确计算中英文混合）
     * @param string $text 文本内容
     * @param int $fontSize 字体大小（dot单位）
     * @param int $widthScale 宽度放大倍率（1-10）
     * @param string $fontFamily 字体名称
     * @return int 宽度（dot单位）
     */
    public function calculateTextWidth(string $text, int $fontSize, int $widthScale = 1, string $fontFamily = 'Arial'): int
    {
        if (empty($text)) {
            return 0;
        }
        
        $totalWidth = 0;
        $length = mb_strlen($text, 'UTF-8');
        
        // 逐个字符计算宽度
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $charWidth = $this->estimateCharWidth($fontSize, $char, $fontFamily) * $widthScale;
            $totalWidth += $charWidth;
        }
        
        return (int)$totalWidth;
    }

    /**
     * 计算文本渲染后的实际高度
     * @param array $lines 文本行数组（calculateTextWrap返回的格式）
     * @param int $fontSize 字体大小（dot单位）
     * @param int $heightScale 高度放大倍率（1-10）
     * @param float $lineHeight 行高倍率（默认1.2）
     * @return int 高度（dot单位）
     */
    public function calculateTextHeight(array $lines, int $fontSize, int $heightScale = 1, float $lineHeight = 1.2): int
    {
        if (empty($lines)) {
            return 0;
        }
        
        $lineCount = count($lines);
        $actualFontSize = $fontSize * $heightScale;
        $singleLineHeight = $actualFontSize * $lineHeight;
        
        return (int)($lineCount * $singleLineHeight);
    }

    /**
     * 计算二维码的实际尺寸（改进版：更准确的尺寸计算）
     * @param int $size 二维码大小参数（1-10）
     * @param int $contentLength 内容长度
     * @return array ['width' => int, 'height' => int] dot单位
     */
    public function calculateQrcodeSize(int $size, int $contentLength): array
    {
        $moduleCount = $this->estimateQrcodeModuleCount($contentLength);
        $moduleSize = max(1, min(10, $size));
        $dimension = $moduleCount * $moduleSize;
        
        return [
            'width' => $dimension,
            'height' => $dimension
        ];
    }

    /**
     * 计算二维码安全区域尺寸，包含静区留白
     * @param int $size 二维码模块大小
     * @param int $contentLength 内容长度
     * @param int $quietZone 静区模块数
     * @return array
     */
    public function calculateQrcodeSafeSize(int $size, int $contentLength, int $quietZone = 4): array
    {
        $qrcodeSize = $this->calculateQrcodeSize($size, $contentLength);
        $moduleSize = max(1, min(10, $size));
        $quietZone = max(0, min(10, $quietZone));
        $quietDot = $quietZone * $moduleSize;

        return [
            'width' => $qrcodeSize['width'] + ($quietDot * 2),
            'height' => $qrcodeSize['height'] + ($quietDot * 2),
            'quiet_dot' => $quietDot,
            'qrcode_width' => $qrcodeSize['width'],
            'qrcode_height' => $qrcodeSize['height'],
        ];
    }

    /**
     * 估算二维码矩阵模块数。
     * 二维码 version 1 是 21×21，version 每增加 1，边长增加 4 个模块。
     * 这里按内容长度做保守估计，用于后台预览和边界检测，不改变实际打印指令。
     */
    private function estimateQrcodeModuleCount(int $contentLength): int
    {
        if ($contentLength <= 20) {
            $version = 1;
        } elseif ($contentLength <= 38) {
            $version = 2;
        } elseif ($contentLength <= 61) {
            $version = 3;
        } elseif ($contentLength <= 90) {
            $version = 4;
        } elseif ($contentLength <= 122) {
            $version = 5;
        } elseif ($contentLength <= 154) {
            $version = 6;
        } elseif ($contentLength <= 180) {
            $version = 7;
        } elseif ($contentLength <= 213) {
            $version = 8;
        } else {
            $version = 9;
        }

        return 21 + (($version - 1) * 4);
    }

    /**
     * 计算条形码的实际尺寸（改进版：区分BC128和BC39）
     * @param int $height 条形码高度（dot单位）
     * @param int $narrowBarWidth 窄条宽度（dot单位）
     * @param int $wideBarWidth 宽条宽度（dot单位）
     * @param string $content 条形码内容
     * @param string $type 条形码类型（BC128或BC39）
     * @return array ['width' => int, 'height' => int] dot单位
     */
    public function calculateBarcodeSize(int $height, int $narrowBarWidth, int $wideBarWidth, string $content, string $type = 'BC128'): array
    {
        // 条形码宽度计算：
        // CODE128: 每个字符大约需要11个模块（模块=窄条宽度）
        // CODE39: 每个字符大约需要13个模块
        // 加上起始/结束字符和校验字符
        
        $contentLength = mb_strlen($content, 'UTF-8');
        
        if ($type === 'BC39') {
            $modulesPerChar = 13; // CODE39标准
        } else {
            $modulesPerChar = 11; // CODE128标准
        }
        
        // 起始和结束字符各占一定模块数
        $startStopModules = $modulesPerChar * 2;
        
        // 计算总模块数
        $totalModules = ($contentLength * $modulesPerChar) + $startStopModules;
        
        // 宽度 = 窄条宽度 * 总模块数（简化计算）
        // 实际宽度会略大于此值，因为宽条宽度更大
        $width = (int)($totalModules * $narrowBarWidth * 1.5); // 乘以1.5作为估算
        
        return [
            'width' => $width,
            'height' => $height
        ];
    }

    /**
     * 对齐元素位置
     * @param array $element 元素数据
     * @param string $align 对齐方式（left/center/right）
     * @param int $containerWidth 容器宽度（dot单位）
     * @return array 对齐后的元素数据
     */
    public function alignElement(array $element, string $align, int $containerWidth): array
    {
        $type = $element['type'] ?? 'text';
        
        switch ($type) {
            case 'text':
            case 'variable':
                $fontSize = ($element['font'] ?? 9) * ($element['height_scale'] ?? 1);
                $text = $element['content'] ?? '';
                $widthScale = $element['width_scale'] ?? 1;
                $textWidth = $this->calculateTextWidth($text, $fontSize, $widthScale);
                
                switch ($align) {
                    case 'center':
                        $element['x'] = ($containerWidth - $textWidth) / 2;
                        break;
                        
                    case 'right':
                        $element['x'] = $containerWidth - $textWidth;
                        break;
                        
                    case 'left':
                    default:
                        // 保持原位置
                        break;
                }
                break;
                
            case 'qrcode':
                $size = $element['size'] ?? 2;
                $contentLength = mb_strlen($element['content'] ?? '', 'UTF-8');
                $qrcodeSize = $this->calculateQrcodeSize($size, $contentLength);
                
                switch ($align) {
                    case 'center':
                        $element['x'] = ($containerWidth - $qrcodeSize['width']) / 2;
                        break;
                        
                    case 'right':
                        $element['x'] = $containerWidth - $qrcodeSize['width'];
                        break;
                        
                    case 'left':
                    default:
                        break;
                }
                break;
                
            case 'barcode':
                $barcodeSize = $this->calculateBarcodeSize(
                    $element['height'] ?? 60,
                    $element['narrow_bar_width'] ?? 1,
                    $element['wide_bar_width'] ?? 2,
                    $element['content'] ?? '',
                    $element['barcode_type'] ?? 'BC128'
                );
                
                switch ($align) {
                    case 'center':
                        $element['x'] = ($containerWidth - $barcodeSize['width']) / 2;
                        break;
                        
                    case 'right':
                        $element['x'] = $containerWidth - $barcodeSize['width'];
                        break;
                        
                    case 'left':
                    default:
                        break;
                }
                break;
        }
        
        return $element;
    }

    /**
     * 检测元素是否超出模板边界
     * @param array $element 元素数据
     * @param int $templateWidth 模板宽度（dot单位）
     * @param int $templateHeight 模板高度（dot单位）
     * @return array ['out_of_bounds' => bool, 'message' => string]
     */
    public function checkElementBounds(array $element, int $templateWidth, int $templateHeight): array
    {
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $type = $element['type'] ?? '';
        
        // 基本位置验证
        if ($x < 0 || $y < 0) {
            return [
                'out_of_bounds' => true,
                'message' => "元素位置不能为负数 (x:{$x}, y:{$y})"
            ];
        }
        
        // 根据元素类型检查
        switch ($type) {
            case 'text':
            case 'variable':
                $fontSize = ($element['font'] ?? 9) * ($element['height_scale'] ?? 1);
                $text = $element['content'] ?? '';
                $widthScale = $element['width_scale'] ?? 1;
                $textWidth = $this->calculateTextWidth($text, $fontSize, $widthScale);
                $lines = $this->calculateTextWrap($text, $fontSize, $templateWidth - $x, $widthScale, $element['height_scale'] ?? 1);
                $textHeight = $this->calculateTextHeight($lines, $fontSize, $element['height_scale'] ?? 1);
                
                if ($x + $textWidth > $templateWidth) {
                    return [
                        'out_of_bounds' => true,
                        'message' => "文本超出右边界 (x:{$x}, width:{$textWidth}, templateWidth:{$templateWidth})"
                    ];
                }
                
                if ($y + $textHeight > $templateHeight) {
                    return [
                        'out_of_bounds' => true,
                        'message' => "文本超出下边界 (y:{$y}, height:{$textHeight}, templateHeight:{$templateHeight})"
                    ];
                }
                break;
                
            case 'qrcode':
                $size = $element['size'] ?? 2;
                $quietZone = (int)($element['quiet_zone'] ?? 4);
                $contentLength = mb_strlen($element['content'] ?? '', 'UTF-8');
                $qrcodeSize = $this->calculateQrcodeSize($size, $contentLength);
                $safeSize = $this->calculateQrcodeSafeSize($size, $contentLength, $quietZone);
                
                if ($x + $qrcodeSize['width'] > $templateWidth) {
                    return [
                        'out_of_bounds' => true,
                        'message' => "二维码超出右边界"
                    ];
                }
                
                if ($y + $qrcodeSize['height'] > $templateHeight) {
                    return [
                        'out_of_bounds' => true,
                        'message' => "二维码超出下边界"
                    ];
                }

                if ($x - $safeSize['quiet_dot'] < 0
                    || $y - $safeSize['quiet_dot'] < 0
                    || $x + $qrcodeSize['width'] + $safeSize['quiet_dot'] > $templateWidth
                    || $y + $qrcodeSize['height'] + $safeSize['quiet_dot'] > $templateHeight
                ) {
                    return [
                        'out_of_bounds' => true,
                        'message' => "二维码静区留白不足，可能无法识别"
                    ];
                }
                break;
                
            case 'barcode':
                $barcodeSize = $this->calculateBarcodeSize(
                    $element['height'] ?? 60,
                    $element['narrow_bar_width'] ?? 1,
                    $element['wide_bar_width'] ?? 2,
                    $element['content'] ?? '',
                    $element['barcode_type'] ?? 'BC128'
                );
                
                if ($x + $barcodeSize['width'] > $templateWidth) {
                    return [
                        'out_of_bounds' => true,
                        'message' => "条形码超出右边界"
                    ];
                }
                
                if ($y + $barcodeSize['height'] > $templateHeight) {
                    return [
                        'out_of_bounds' => true,
                        'message' => "条形码超出下边界"
                    ];
                }
                break;
                
            case 'rectangle':
                $xe = $element['xe'] ?? ($x + 80);
                $ye = $element['ye'] ?? ($y + 40);
                
                if ($xe < 0 || $ye < 0 || $xe > $templateWidth || $ye > $templateHeight) {
                    return [
                        'out_of_bounds' => true,
                        'message' => "矩形超出边界"
                    ];
                }
                break;
        }
        
        return [
            'out_of_bounds' => false,
            'message' => ''
        ];
    }
}
