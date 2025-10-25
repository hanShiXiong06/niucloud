<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\printer;

use addon\recycle\app\model\printer\RecyclePrinterTemplate;
use addon\recycle\app\model\DeviceQueryResult;
use core\base\BaseAdminService;
use core\exception\CommonException;
use core\exception\AdminException;

/**
 * 回收打印模板服务类
 * Class RecyclePrinterTemplateService  
 * @package addon\recycle\app\service\admin\printer
 */
class RecyclePrinterTemplateService extends BaseAdminService
{
    /**
     * 模型实例
     * @var RecyclePrinterTemplate
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecyclePrinterTemplate();
    }

    /**
     * 获取模板列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'template_id,template_name,template_type,width,height,status,is_default,create_time,update_time';
        $order = 'create_time desc';

        $search_model = $this->model->where([['site_id', '=', $this->site_id]]);
        
        // 处理搜索条件 - 只有非空值才添加到搜索条件
        if (!empty($where['template_name'])) {
            $search_model = $search_model->where('template_name', 'like', '%' . $where['template_name'] . '%');
        }
        
        if (!empty($where['template_type'])) {
            $search_model = $search_model->where('template_type', '=', $where['template_type']);
        }
        
        if (isset($where['status']) && $where['status'] !== '') {
            $search_model = $search_model->where('status', '=', $where['status']);
        }
        
        $search_model = $search_model->field($field)->order($order);
            
        $list = $this->pageQuery($search_model);
        
        // 处理返回数据
        if (!empty($list['data'])) {
            foreach ($list['data'] as &$item) {
                $item['type_name'] = RecyclePrinterTemplate::getTypeList()[$item['template_type']] ?? '';
                $item['status_name'] = RecyclePrinterTemplate::getStatusList()[$item['status']] ?? '';
            }
        }
        
        return $list;
    }

    /**
     * 获取模板详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'template_id,template_name,template_type,width,height,content,instruction_content,html_content,variables,status,is_default,create_time,update_time';
        
        $info = $this->model->field($field)
            ->where([['template_id', '=', $id], ['site_id', '=', $this->site_id]])
            ->findOrEmpty()
            ->toArray();
            
        if (!empty($info)) {
            $info['type_name'] = RecyclePrinterTemplate::getTypeList()[$info['template_type']] ?? '';
            $info['status_name'] = RecyclePrinterTemplate::getStatusList()[$info['status']] ?? '';
            
            // 解析变量
            $info['variables'] = $info['variables'] ? json_decode($info['variables'], true) : [];
            
            // 处理content字段
            if (!empty($info['content'])) {
                // 尝试解析为JSON
                $content_data = json_decode($info['content'], true);
                if ($content_data) {
                    // 是JSON格式
                    $info['content'] = $content_data;
                    $info['is_json_format'] = true;
                    
                    // 确保有instruction_content，如果没有则生成
                    if (empty($info['instruction_content'])) {
                        $info['instruction_content'] = $this->convertJsonToXinYeContent($content_data);
                        // 更新数据库
                        $this->model->where([
                            ['template_id', '=', $id],
                            ['site_id', '=', $this->site_id]
                        ])->update(['instruction_content' => $info['instruction_content']]);
                    }
                    
                    // 为了兼容，也生成XML格式
                    $info['xml_content'] = $info['instruction_content'];
                } else {
                    // 是旧的XML格式，转换为JSON
                    $content_data = $this->convertXmlToJson($info['content']);
                    $info['content'] = $content_data;
                    $info['is_json_format'] = false;
                    
                    // 生成instruction_content
                    $info['instruction_content'] = $this->convertJsonToXinYeContent($content_data);
                    $info['xml_content'] = $info['instruction_content'];
                    
                    // 更新数据库为新格式
                    $this->model->where([
                        ['template_id', '=', $id],
                        ['site_id', '=', $this->site_id]
                    ])->update([
                        'content' => json_encode($content_data),
                        'instruction_content' => $info['instruction_content']
                    ]);
                }
            } else {
                $info['content'] = ['width' => 58, 'height' => 40, 'elements' => []];
                $info['is_json_format'] = true;
                $info['instruction_content'] = '<PAGE><SIZE>58,40</SIZE></PAGE>';
                $info['xml_content'] = $info['instruction_content'];
            }
            
            // 重新生成HTML预览
            // $info['html_content'] = $this->generateHtmlFromJson(json_encode($info['content']));
            
            // 添加调试信息
            \think\facade\Log::info('读取模板信息', [
                'template_id' => $id,
                'is_json_format' => $info['is_json_format'],
                'content_data' => $info['content'],
                'instruction_content' => $info['instruction_content'],
                'content_length' => strlen(json_encode($info['content']))
            ]);
        }
        
        return $info;
    }

    /**
     * 添加模板
     * @param array $data
     * @return bool
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $data['update_time'] = time();
        $data['instruction_content'] = $data['content'];
        // $data['variables'] = isset($data['variables']) ? json_encode($data['variables']) : '[]';
        
        // 直接使用content字段存储JSON格式数据
        // if (!empty($data['content'])) {
        //     // 如果content是数组/对象，转为JSON字符串
        //     if (is_array($data['content'])) {
        //         $json_data = $data['content'];
        //         $data['content'] = json_encode($data['content']);
        //     }
        //     // 如果是XML格式，转换为JSON格式存储
        //     elseif (is_string($data['content']) && strpos($data['content'], '<PAGE>') !== false) {
        //         $template_data = $this->convertXmlToJson($data['content']);
        //         $json_data = $template_data;
        //         $data['content'] = json_encode($template_data);
        //     }
        //     // 如果是JSON字符串，解析为数组
        //     else {
        //         $json_data = json_decode($data['content'], true);
        //         if (!$json_data) {
        //             $json_data = ['width' => 58, 'height' => 40, 'elements' => []];
        //             $data['content'] = json_encode($json_data);
        //         }
        //     }
        // } else {
        //     // 如果没有content，创建默认的JSON格式数据
        //     $json_data = ['width' => 58, 'height' => 40, 'elements' => []];
        //     $data['content'] = json_encode($json_data);
        // }
        
        // 生成XML格式的instruction_content
        // $data['instruction_content'] = $this->convertJsonToXinYeContent($json_data);
        
        // 生成HTML预览
        // if (!empty($data['content'])) {
        //     $data['html_content'] = $this->generateHtmlFromJson($data['content']);
        // }
        
        // 添加调试日志
        // \think\facade\Log::info('新格式保存模板', [
        //     'template_name' => $data['template_name'],
        //     'json_content' => $data['content'],
        //     'xml_instruction' => $data['instruction_content'],
        //     'content_length' => strlen($data['content'])
        // ]);
        
        // 如果设置为默认模板，先取消其他默认模板
        if (!empty($data['is_default'])) {
            $this->model->where([
                ['site_id', '=', $this->site_id],
                ['template_type', '=', $data['template_type']]
            ])->update(['is_default' => 0]);
        }
        
        $this->model->save($data);
        
        return true;
    }

    /**
     * 将JSON格式的模板数据转换为芯烨云XML格式
     * @param array $templateData
     * @return string
     */
    public function convertJsonToXinYeContent(array $templateData): string
    {
        $xml = '<PAGE>';
        
        // 添加尺寸信息
        $width = $templateData['width'] ?? 58;
        $height = $templateData['height'] ?? 40;
        $xml .= "<SIZE>{$width},{$height}</SIZE>";
        
        // 处理元素列表
        $elements = $templateData['elements'] ?? [];
        
        // 按y坐标排序，确保打印顺序正确
        // usort($elements, function($a, $b) {
        //     return ($a['y'] ?? 0) - ($b['y'] ?? 0);
        // });
        
        foreach ($elements as $element) {
            $type = $element['type'] ?? 'text';
            $x = $element['x'] ?? 0;
            $y = $element['y'] ?? 0;
            
            switch ($type) {
                case 'text':
                    $content = $element['content'] ?? '';
                    $width_scale = $element['width_scale'] ?? '1';
                    $height_scale = $element['height_scale'] ?? '1';
                    $rotation = $element['rotation'] ?? '0';
                    
                    // 对文本内容进行芯烨云转义
                    $escaped_content = $this->escapeXinYeText($content);
                    
                    $xml .= "<TEXT x=\"{$x}\" y=\"{$y}\" w=\"{$width_scale}\" h=\"{$height_scale}\" r=\"{$rotation}\">{$escaped_content}</TEXT>";
                    break;
                    
                case 'qrcode':
                    $content = $element['content'] ?? '';
                    $size = $element['size'] ?? '2';
                    $error_level = $element['error_level'] ?? 'L';
                    
                    $xml .= "<QRC x=\"{$x}\" y=\"{$y}\" s=\"{$size}\" e=\"{$error_level}\">{$content}</QRC>";
                    break;
                    
                case 'barcode':
                    $content = $element['content'] ?? '';
                    $height = $element['height'] ?? '60';
                    $scale = $element['scale'] ?? '1';
                    
                    $xml .= "<BC128 x=\"{$x}\" y=\"{$y}\" h=\"{$height}\" s=\"{$scale}\">{$content}</BC128>";
                    break;
                    
                case 'line':
                    $width = $element['width'] ?? '4';
                    $height = $element['height'] ?? '2';
                    
                    $xml .= "<L x=\"{$x}\" y=\"{$y}\" w=\"{$width}\" h=\"{$height}\"></L>";
                    break;
                    
                case 'rectangle':
                    $xe = $element['xe'] ?? ($x + 80);
                    $ye = $element['ye'] ?? ($y + 40);
                    $style = $element['style'] ?? '4';
                    
                    $xml .= "<SEQ x=\"{$x}\" y=\"{$y}\" xe=\"{$xe}\" ye=\"{$ye}\" s=\"{$style}\"></SEQ>";
                    break;
                    
                case 'variable':
                    // 变量元素按文本处理
                    $content = $element['content'] ?? '';
                    $width_scale = $element['width_scale'] ?? '1';
                    $height_scale = $element['height_scale'] ?? '1';
                    $rotation = $element['rotation'] ?? '0';
                    
                    // 对文本内容进行芯烨云转义
                    $escaped_content = $this->escapeXinYeText($content);
                    
                    $xml .= "<TEXT x=\"{$x}\" y=\"{$y}\"  w=\"{$width_scale}\" h=\"{$height_scale}\" r=\"{$rotation}\">{$escaped_content}</TEXT>";
                    break;
            }
        }
        
        $xml .= '</PAGE>';
        
        \think\facade\Log::info('JSON转芯烨云XML', [
            'input_json' => $templateData,
            'output_xml' => $xml
        ]);
        
        return $xml;
    }

    /**
     * 芯烨云文本转义
     * @param string $text
     * @return string
     */
    private function escapeXinYeText(string $text): string
    {
        // 芯烨云要求：文本中的 < 用 &lt 表示，> 用 &gt 表示
        // 但要保护变量格式 {{variable}}
        
        // 先保护变量
        $protected = preg_replace('/\{\{([^}]+)\}\}/', '{{$1}}', $text);
        
        // 转义 < 和 >
        $escaped = str_replace(['<', '>'], ['&lt', '&gt'], $protected);
        
        // 恢复变量
        $final = preg_replace('/___VAR_([^_]+)___/', '{{$1}}', $escaped);
        
        return $final;
    }

    /**
     * 将XML格式转换为JSON格式（兼容旧数据）
     * @param string $xmlContent
     * @return array
     */
    private function convertXmlToJson(string $xmlContent): array
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
        
        // 解析TEXT标签
        if (preg_match_all('/<TEXT\s+([^>]*?)>([^<]*?)<\/TEXT>/', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = $match[1];
                $content = $match[2];
                
                $element = [
                    'type' => 'text',
                    'content' => $this->unescapeXinYeText($content),
                    'x' => $this->extractAttribute($attributes, 'x', '0'),
                    'y' => $this->extractAttribute($attributes, 'y', '0'),
                    'width_scale' => $this->extractAttribute($attributes, 'w', '1'),
                    'height_scale' => $this->extractAttribute($attributes, 'h', '1'),
                    'rotation' => $this->extractAttribute($attributes, 'r', '0')
                ];
                
                $templateData['elements'][] = $element;
            }
        }
        
        // 解析QRC标签
        if (preg_match_all('/<QRC\s+([^>]*?)>([^<]*?)<\/QRC>/', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = $match[1];
                $content = $match[2];
                
                $element = [
                    'type' => 'qrcode',
                    'content' => $content,
                    'x' => $this->extractAttribute($attributes, 'x', '0'),
                    'y' => $this->extractAttribute($attributes, 'y', '0'),
                    'size' => $this->extractAttribute($attributes, 's', '2'),
                    'error_level' => $this->extractAttribute($attributes, 'e', 'L')
                ];
                
                $templateData['elements'][] = $element;
            }
        }
        
        // 解析BC128标签
        if (preg_match_all('/<BC128\s+([^>]*?)>([^<]*?)<\/BC128>/', $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = $match[1];
                $content = $match[2];
                
                $element = [
                    'type' => 'barcode',
                    'content' => $content,
                    'x' => $this->extractAttribute($attributes, 'x', '0'),
                    'y' => $this->extractAttribute($attributes, 'y', '0'),
                    'height' => $this->extractAttribute($attributes, 'h', '60'),
                    'scale' => $this->extractAttribute($attributes, 's', '1')
                ];
                
                $templateData['elements'][] = $element;
            }
        }
        
        return $templateData;
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
     * 芯烨云文本反转义
     * @param string $text
     * @return string
     */
    private function unescapeXinYeText(string $text): string
    {
        return str_replace(['&lt', '&gt'], ['<', '>'], $text);
    }

    /**
     * 从JSON数据生成HTML预览
     * @param string $jsonData
     * @return string
     */
    private function generateHtmlFromJson(string $jsonData): string
    {
        $templateData = json_decode($jsonData, true);
        if (!$templateData) {
            return '<div class="label-preview">无效的模板数据</div>';
        }
        
        $width = ($templateData['width'] ?? 58) * 4; // mm转px
        $height = ($templateData['height'] ?? 40) * 4;
        
        $html = '<div class="label-preview" style="position: relative; border: 1px solid #ccc; background: white;">';
        $html .= "<div style=\"width: {$width}px; height: {$height}px; position: relative; margin: 10px;\">";
        
        $elements = $templateData['elements'] ?? [];
        foreach ($elements as $element) {
            $type = $element['type'] ?? 'text';
            $x = ($element['x'] ?? 0) / 2; // dot转px
            $y = ($element['y'] ?? 0) / 2;
            
            switch ($type) {
                case 'text':
                    $content = htmlspecialchars($element['content'] ?? '');
                    $rotation = $element['rotation'] ?? '0';
                    
                    $html .= "<div style=\"position: absolute; left: {$x}px; top: {$y}px;  transform: rotate({$rotation}deg);\">{$content}</div>";
                    break;
                    
                case 'qrcode':
                    $size = ($element['size'] ?? '2') * 20; // 估算尺寸
                    $html .= "<div style=\"position: absolute; left: {$x}px; top: {$y}px; width: {$size}px; height: {$size}px; border: 1px solid #000; text-align: center; line-height: {$size}px; \">QR</div>";
                    break;
                    
                case 'barcode':
                    $bar_height = ($element['height'] ?? 60) / 2;
                    $html .= "<div style=\"position: absolute; left: {$x}px; top: {$y}px; width: 80px; height: {$bar_height}px; border: 1px solid #000; text-align: center; line-height: {$bar_height}px; \">|||</div>";
                    break;
                    
                case 'line':
                    $line_width = ($element['width'] ?? 4) / 2;
                    $line_height = ($element['height'] ?? 2) / 2;
                    $html .= "<div style=\"position: absolute; left: {$x}px; top: {$y}px; width: {$line_width}px; height: {$line_height}px; background: #000;\"></div>";
                    break;
                    
                case 'rectangle':
                    $rect_width = (($element['xe'] ?? ($element['x'] + 80)) - ($element['x'] ?? 0)) / 2;
                    $rect_height = (($element['ye'] ?? ($element['y'] + 40)) - ($element['y'] ?? 0)) / 2;
                    $html .= "<div style=\"position: absolute; left: {$x}px; top: {$y}px; width: {$rect_width}px; height: {$rect_height}px; border: 1px solid #000;\"></div>";
                    break;
            }
        }
        
        $html .= '</div></div>';
        
        return $html;
    }

   
    /**
     * 编辑模板
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $data['update_time'] = time();
        $data['instruction_content'] = $data['content'];
        // $data['variables'] = isset($data['variables']) ? json_encode($data['variables']) : '[]';
        
        // 直接使用content字段存储JSON格式数据
        // if (!empty($data['content'])) {
        //     // 如果content是数组/对象，转为JSON字符串
        //     if (is_array($data['content'])) {
        //         $json_data = $data['content'];
        //         $data['content'] = json_encode($data['content']);
        //     }
        //     // 如果是XML格式，转换为JSON格式存储
        //     elseif (is_string($data['content']) && strpos($data['content'], '<PAGE>') !== false) {
        //         $template_data = $this->convertXmlToJson($data['content']);
        //         $json_data = $template_data;
        //         $data['content'] = json_encode($template_data);
        //     }
        //     // 如果是JSON字符串，解析为数组
        //     else {
        //         $json_data = json_decode($data['content'], true);
        //         if (!$json_data) {
        //             $json_data = ['width' => 58, 'height' => 40, 'elements' => []];
        //             $data['content'] = json_encode($json_data);
        //         }
        //     }
            
        //     // 生成XML格式的instruction_content
        //     $data['instruction_content'] = $this->convertJsonToXinYeContent($json_data);
        // }
        
        // 生成HTML预览
        // if (!empty($data['content'])) {
        //     $data['html_content'] = $this->generateHtmlFromJson($data['content']);
        // }
        
        // 添加调试日志
        \think\facade\Log::info('编辑模板', [
            'template_id' => $id,
            'json_content' => $data['content'],
            'xml_instruction' => $data['instruction_content'] ?? '未生成',
            'content_length' => strlen($data['content'])
        ]);
        
        // 如果设置为默认模板，先取消其他默认模板
        if (!empty($data['is_default'])) {
            $this->model->where([
                ['site_id', '=', $this->site_id],
                ['template_type', '=', $data['template_type']],
                ['template_id', '<>', $id]
            ])->update(['is_default' => 0]);
        }
        
        $this->model->where([
            ['template_id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update($data);
        
        return true;
    }

    /**
     * 删除模板
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {

        
        $this->model->where([
            ['template_id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update([
            'delete_time' => time()
        ]);
        
        return true;
    }

    /**
     * 修改模板状态
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function modifyStatus(int $id, int $status)
    {
        $this->model->where([
            ['template_id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update([
            'status' => $status,
            'update_time' => time()
        ]);
        
        return true;
    }

    /**
     * 设置默认模板
     * @param int $id
     * @return bool
     */
    public function setDefault(int $id)
    {
        $template = $this->model->where([
            ['template_id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();
        
        if ($template->isEmpty()) {
            throw new CommonException('模板不存在');
        }
        
        // 取消同类型的其他默认模板
        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['template_type', '=', $template['template_type']]
        ])->update(['is_default' => 0]);
        
        // 设置当前模板为默认
        $template->save(['is_default' => 1, 'update_time' => time()]);
        
        return true;
    }

    /**
     * 获取模板类型列表
     * @return array
     */
    public function getTypeList()
    {
        return RecyclePrinterTemplate::getTypeList();
    }

    /**
     * 根据类型获取默认模板
     * @param string $type
     * @return array
     */
    public function getDefaultTemplate(string $type)
    {
        $template = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['template_type', '=', $type],
            ['is_default', '=', 1],
            ['status', '=', 1]
        ])->findOrEmpty()->toArray();
        
        return $template;
    }

    /**
     * 生成HTML预览内容
     * @param string $content 打印指令内容
     * @return string
     */
    private function generateHtmlPreview(string $content): string
    {
        // 创建内容的副本，确保不修改原始内容
        $content_copy = $content;
        
        // 添加调试日志
        \think\facade\Log::info('generateHtmlPreview函数开始', [
            'input_content' => $content,
            'content_length' => strlen($content)
        ]);
        
        // 解析打印指令并转换为HTML预览
        $html = '<div class="label-preview" style="position: relative; border: 1px solid #ccc; background: white;">';
        
        // 解析SIZE标签获取尺寸
        if (preg_match('/<SIZE>(\d+),(\d+)<\/SIZE>/', $content_copy, $matches)) {
            $width = intval($matches[1]) * 4; // 转换为像素，1mm≈4px
            $height = intval($matches[2]) * 4;
            $html .= '<div style="width: ' . $width . 'px; height: ' . $height . 'px; position: relative; margin: 10px;">';
        } else {
            $html .= '<div style="width: 232px; height: 120px; position: relative; margin: 10px;">'; // 默认58mm宽度
        }
        
        // 解析TEXT标签 - 使用更安全的正则表达式
        if (preg_match_all('/<TEXT x="(\d+)" y="(\d+)"[^>]*>([^<]*)<\/TEXT>/', $content_copy, $textMatches, PREG_SET_ORDER)) {
            \think\facade\Log::info('找到TEXT标签', [
                'matches_count' => count($textMatches),
                'matches' => $textMatches
            ]);
            
            foreach ($textMatches as $match) {
                $x = intval($match[1]) / 2; // dot转像素
                $y = intval($match[2]) / 2;
                $text = $match[3];
                $html .= '<div style="position: absolute; left: ' . $x . 'px; top: ' . $y . 'px; ">' . $text . '</div>';
            }
        } else {
            \think\facade\Log::info('未找到TEXT标签', ['content' => $content_copy]);
        }
        
        // 解析QRC标签
        if (preg_match_all('/<QRC x="(\d+)" y="(\d+)"[^>]*>([^<]+)<\/QRC>/', $content_copy, $qrcMatches, PREG_SET_ORDER)) {
            foreach ($qrcMatches as $match) {
                $x = intval($match[1]) / 2;
                $y = intval($match[2]) / 2;
                $html .= '<div style="position: absolute; left: ' . $x . 'px; top: ' . $y . 'px; width: 40px; height: 40px; border: 1px solid #000; text-align: center; line-height: 40px; ">QR</div>';
            }
        }
        
        $html .= '</div></div>';
        
        \think\facade\Log::info('generateHtmlPreview函数结束', [
            'output_html' => $html,
            'original_content_unchanged' => $content === $content_copy
        ]);
        
        return $html;
    }

    /**
     * 将HTML内容转换为打印指令
     * @param string $htmlContent HTML内容
     * @param array $variables 变量列表
     * @return string
     */
    public function convertHtmlToInstructions(string $htmlContent, array $variables = []): string
    {
        // 获取模板尺寸（默认58mm宽）
        $width = 58;
        $height = 40;
        
        // 尝试从HTML中解析尺寸
        if (preg_match('/width:\s*(\d+)px/', $htmlContent, $matches)) {
            $width = intval($matches[1]) / 4; // 像素转mm
        }
        if (preg_match('/height:\s*(\d+)px/', $htmlContent, $matches)) {
            $height = intval($matches[1]) / 4; // 像素转mm
        }
        
        $instructions = "<PAGE>\n<SIZE>{$width},{$height}</SIZE>\n";
        
        // 解析HTML中的元素并转换为打印指令
        // 匹配所有的div元素
        preg_match_all('/<div[^>]*style="([^"]*)"[^>]*>([^<]*)<\/div>/', $htmlContent, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $style = $match[1];
            $content = trim($match[2]);
            
            // 解析样式属性
            $left = $this->extractStyleValue($style, 'left');
            $top = $this->extractStyleValue($style, 'top');
        $width_px = $this->extractStyleValue($style, 'width');
            $height_px = $this->extractStyleValue($style, 'height');
            
            if ($left !== null && $top !== null) {
                // 像素转dot（1px ≈ 2dots）
                $x = intval($left) * 2;
                $y = intval($top) * 2;
                
                // 判断元素类型并生成对应指令
                if (strpos($style, 'QR') !== false || $content === 'QR') {
                    // 二维码
                    $qr_content = $this->getVariableValue('qrcode_content', $variables, 'https://example.com');
                    $instructions .= "<QRC x=\"{$x}\" y=\"{$y}\" s=\"2\" e=\"L\">{$qr_content}</QRC>\n";
                    
                } elseif (strpos($style, '|||') !== false || strpos($content, '|||') !== false) {
                    // 条形码
                    $bar_content = $this->getVariableValue('barcode_content', $variables, '123456789');
                    $bar_height = $height_px ? intval($height_px) * 2 : 60;
                    $instructions .= "<BC128 x=\"{$x}\" y=\"{$y}\" h=\"{$bar_height}\" s=\"1\">{$bar_content}</BC128>\n";
                    
                } elseif (strpos($style, 'border') !== false && empty($content)) {
                    // 矩形框
                    $xe = $x + ($width_px ? intval($width_px) * 2 : 80);
                    $ye = $y + ($height_px ? intval($height_px) * 2 : 40);
                    $instructions .= "<SEQ x=\"{$x}\" y=\"{$y}\" xe=\"{$xe}\" ye=\"{$ye}\" s=\"4\"></SEQ>\n";
                    
                } elseif (strpos($style, 'background') !== false && (strpos($style, 'width') !== false)) {
                    // 线条
                    $line_width = $width_px ? intval($width_px) * 2 : 4;
                    $line_height = $height_px ? intval($height_px) * 2 : 2;
                    $instructions .= "<L x=\"{$x}\" y=\"{$y}\" w=\"{$line_width}\" h=\"{$line_height}\"></L>\n";
                    
                } elseif (!empty($content)) {
                    // 文本内容 - 确保生成完整的TEXT标签
                  
                    
                    // 处理变量替换
                    $text_content = $this->replaceVariables($content, $variables);
                    
                    // 生成完整的TEXT标签，包含所有必需属性
                    $instructions .= "<TEXT x=\"{$x}\" y=\"{$y}\"  w=\"1\" h=\"1\" r=\"0\">{$text_content}</TEXT>\n";
                }
            }
        }
        
        $instructions .= '</PAGE>';
        
        // 添加调试日志
        \think\facade\Log::info('生成的打印指令', [
            'instructions' => $instructions,
            'html_content' => $htmlContent
        ]);
        
        return $instructions;
    }
    
    /**
     * 从样式字符串中提取指定属性的值
     * @param string $style
     * @param string $property
     * @return int|null
     */
    private function extractStyleValue(string $style, string $property): ?int
    {
        if (preg_match("/{$property}:\s*(\d+)px/", $style, $matches)) {
            return intval($matches[1]);
        }
        return null;
    }
    
  
    /**
     * 获取变量值
     * @param string $key
     * @param array $variables
     * @param string $default
     * @return string
     */
    private function getVariableValue(string $key, array $variables, string $default = ''): string
    {
        return $variables[$key] ?? $default;
    }

    /**
     * 替换模板变量
     * @param string $content 模板内容
     * @param array $data 替换数据
     * @return string
     */
    public function replaceVariables(string $content, array $data): string
    {
        // 记录替换前的内容和变量数据
        \think\facade\Log::info('变量替换开始', [
            'original_content' => $content,
            'variables' => $data,
            'variable_count' => count($data)
        ]);
        
        // 查找所有模板变量
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        $template_variables = $matches[1] ?? [];
        
        // 记录模板中的变量
        \think\facade\Log::info('模板变量分析', [
            'template_variables' => $template_variables,
            'template_variable_count' => count($template_variables)
        ]);
        
        $replaced_variables = [];
        $missing_variables = [];
        
        foreach ($data as $key => $value) {
            // 确保value是字符串类型，处理各种数据类型
            if ($value === null) {
                $valueStr = '';
            } elseif (is_bool($value)) {
                $valueStr = $value ? '1' : '0';
            } elseif (is_array($value) || is_object($value)) {
                $valueStr = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_numeric($value)) {
                // 数字类型需要特殊处理
                if (is_float($value) || strpos((string)$value, '.') !== false) {
                    $valueStr = number_format((float)$value, 2, '.', '');
                } else {
                    $valueStr = (string)$value;
                }
            } else {
                $valueStr = (string)$value;
            }
            
            // 处理需要换行的长文本字段
            if ($this->needsLineWrap($key)) {
                $valueStr = $this->wrapLongText($valueStr, 20);
            }
            
            $variable_pattern = '{{' . $key . '}}';
            if (strpos($content, $variable_pattern) !== false) {
                // 如果是需要换行的字段且包含换行符，需要特殊处理
                if ($this->needsLineWrap($key) && strpos($valueStr, "\n") !== false) {
                    $content = $this->replaceWithMultiLineText($content, $variable_pattern, $valueStr);
                } else {
                    $content = str_replace($variable_pattern, $valueStr, $content);
                }
                $replaced_variables[$key] = $valueStr;
            }
        }
        
        // 检查是否有未替换的变量
        foreach ($template_variables as $template_var) {
            if (!isset($data[$template_var])) {
                $missing_variables[] = $template_var;
            }
        }
        
        // 记录替换结果
        \think\facade\Log::info('变量替换完成', [
            'final_content' => $content,
            'replaced_variables' => $replaced_variables,
            'missing_variables' => $missing_variables,
            'replaced_count' => count($replaced_variables),
            'missing_count' => count($missing_variables)
        ]);
        
        // 如果有缺失的变量，记录警告
        if (!empty($missing_variables)) {
            \think\facade\Log::warning('模板变量缺失', [
                'missing_variables' => $missing_variables,
                'available_variables' => array_keys($data)
            ]);
        }
        
        return $content;
    }
    
    /**
     * 判断字段是否需要换行处理
     * @param string $fieldName
     * @return bool
     */
    private function needsLineWrap(string $fieldName): bool
    {
        // 定义需要换行处理的字段
        $wrapFields = [
            'check_result',    // 质检结果
            'remark',          // 备注
            'price_remark',    // 定价备注
            'description',     // 描述
            'note',           // 说明
            'comment'         // 评论
        ];
        
        return in_array($fieldName, $wrapFields);
    }
    
    /**
     * 将长文本按指定长度换行
     * @param string $text
     * @param int $maxLength
     * @return string
     */
    private function wrapLongText(string $text, int $maxLength = 20): string
    {
        if (empty($text)) {
            return $text;
        }
        
        // 如果文本长度不超过限制，直接返回
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }
        
        $lines = [];
        $currentLine = '';
        $length = mb_strlen($text, 'UTF-8');
        
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            
            // 检查当前行加上新字符是否超过长度限制
            if (mb_strlen($currentLine . $char, 'UTF-8') > $maxLength) {
                // 当前行已满，保存并开始新行
                if (!empty($currentLine)) {
                    $lines[] = $currentLine;
                    $currentLine = $char;
                } else {
                    // 单个字符就超长（理论上不会发生）
                    $lines[] = $char;
                }
            } else {
                $currentLine .= $char;
            }
        }
        
        // 添加最后一行
        if (!empty($currentLine)) {
            $lines[] = $currentLine;
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * 将包含换行符的文本替换为多个TEXT标签
     * @param string $content
     * @param string $variable_pattern
     * @param string $multiLineText
     * @return string
     */
    private function replaceWithMultiLineText(string $content, string $variable_pattern, string $multiLineText): string
    {
        // 查找包含该变量的TEXT标签
        $pattern = '/<TEXT([^>]*?)>' . preg_quote($variable_pattern, '/') . '<\/TEXT>/';
        
        if (preg_match($pattern, $content, $matches)) {
            $fullTextTag = $matches[0];
            $attributes = $matches[1];
            
            // 解析原始TEXT标签的属性
            $x = $this->extractAttributeFromString($attributes, 'x', '0');
            $y = $this->extractAttributeFromString($attributes, 'y', '0');
            $w = $this->extractAttributeFromString($attributes, 'w', '1');
            $h = $this->extractAttributeFromString($attributes, 'h', '1');
            $r = $this->extractAttributeFromString($attributes, 'r', '0');
            
            // 分割文本行
            $lines = explode("\n", $multiLineText);
            $newTextTags = [];
            
            // 为每一行创建一个TEXT标签
            foreach ($lines as $index => $line) {
                if (trim($line) !== '') {
                    // 计算新的y坐标（每行间距约24dots）
                    $newY = intval($y) + ($index * 24);
                    $escapedLine = $this->escapeXinYeText(trim($line));
                    $newTextTags[] = "<TEXT x=\"{$x}\" y=\"{$newY}\" w=\"{$w}\" h=\"{$h}\" r=\"{$r}\">{$escapedLine}</TEXT>";
                }
            }
            
            // 替换原始的TEXT标签
            $content = str_replace($fullTextTag, implode('', $newTextTags), $content);
            
            \think\facade\Log::info('多行文本替换', [
                'variable' => $variable_pattern,
                'original_tag' => $fullTextTag,
                'lines_count' => count($lines),
                'new_tags' => $newTextTags
            ]);
        } else {
            // 如果没有找到TEXT标签，按普通方式替换
            $content = str_replace($variable_pattern, str_replace("\n", " ", $multiLineText), $content);
        }
        
        return $content;
    }
    
    /**
     * 从属性字符串中提取指定属性值
     * @param string $attributes
     * @param string $name
     * @param string $default
     * @return string
     */
    private function extractAttributeFromString(string $attributes, string $name, string $default = ''): string
    {
        if (preg_match("/{$name}=\"([^\"]*)\"/", $attributes, $matches)) {
            return $matches[1];
        }
        return $default;
    }

    /**
     * 获取默认打印机
     * @return array
     */
    public function getDefaultPrinter(): array
    {
        $printer_model = new \addon\recycle\app\model\printer\RecyclePrinter();
        
        // 先查找当前用户的默认打印机
        $printer = $printer_model->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
            ['is_default', '=', 1],
            ['uid', '=', $this->uid]
        ])->field('printer_id,printer_name,sn,user_name,user_key,brand,type')
        ->findOrEmpty()->toArray();
        
        // 如果没有找到用户专属的默认打印机，查找站点默认打印机
        if (empty($printer)) {
            $printer = $printer_model->where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
                ['is_default', '=', 1]
            ])->field('printer_id,printer_name,sn,user_name,user_key,brand,type')
            ->findOrEmpty()->toArray();
        }
        
        // 如果还是没有，获取第一个启用的打印机
        if (empty($printer)) {
            $printer = $printer_model->where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 1]
            ])->field('printer_id,printer_name,sn,user_name,user_key,brand,type')
            ->findOrEmpty()->toArray();
        }
        
        return $printer;
    }
    
    /**
     * 测试打印模板
     * @param int $template_id
     * @param array $test_data
     * @return array
     */
    public function testPrint(int $template_id, array $test_data = []): array
    {
        try {
            // 获取模板
            $template = $this->getInfo($template_id);
            if (empty($template)) {
                throw new AdminException('模板不存在');
            }
            
            // 获取默认打印机
            $printer = $this->getDefaultPrinter();
            if (empty($printer)) {
                throw new AdminException('未找到可用的打印机，请先配置打印机');
            }
            
            // 准备测试数据
            if (empty($test_data)) {
                $test_data = $this->getTestData();
            }
            
            // 直接使用模板的instruction_content，并替换变量
            $print_content = $template['instruction_content'] ?? '';
            if (empty($print_content)) {
                // 如果没有instruction_content，从JSON格式生成
                $template_data = $template['content'];
                $print_content = $this->generatePrintContent($template_data, $test_data);
            } else {
                // 如果有instruction_content，直接替换变量
                $print_content = $this->replaceVariables($print_content, $test_data);
            }
            
            // 记录打印信息
            \think\facade\Log::info('模板测试打印', [
                'template_id' => $template_id,
                'template_name' => $template['template_name'],
                'printer_name' => $printer['printer_name'],
                'template_content' => $template['content'],
                'instruction_content' => $template['instruction_content'],
                'test_data' => $test_data,
                'final_print_content' => $print_content
            ]);
            
           
    
            // 调用芯烨云打印API
            return $this->sendToPrinter($printer, $print_content);
            
        } catch (AdminException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage()
            ];
        }
    }

    /**
     * 从JSON模板数据生成打印内容，并替换变量
     * @param array $templateData
     * @param array $variables
     * @return string
     */
    private function generatePrintContent(array $templateData, array $variables = []): string
    {
        // 复制模板数据，避免修改原始数据
        $printData = $templateData;
        
        // 替换所有元素中的变量
        if (isset($printData['elements'])) {
            foreach ($printData['elements'] as &$element) {
                if (isset($element['content'])) {
                    $element['content'] = $this->replaceVariables($element['content'], $variables);
                }
            }
        }
        
        // 转换为芯烨云XML格式
        return $this->convertJsonToXinYeContent($printData);
    }

    /**
     * 获取测试数据
     * @return array
     */
    public function getTestData(): array
    {
        // 获取当前登录用户信息
        $current_user = null;
        if (!empty($this->uid)) {
            $current_user = \app\model\sys\SysUser::where('uid', $this->uid)
                ->field('uid,username,real_name')
                ->findOrEmpty()
                ->toArray();
        }
        
        // 设置用户名，优先使用real_name，其次username
        $staff_name = '未知用户';
        if (!empty($current_user)) {
            $staff_name = $current_user['real_name'] ?: $current_user['username'];
        }
        
        return [
            // 基础设备信息
            'device_id' => '12345',
            'id' => '12345',
            
            // 基础订单信息
            'order_id' => 'RC' . date('YmdHis'),
            'order_no' => 'RC' . date('YmdHis'),
            
            // 客户信息
            'customer_name' => '张三',
            'customer_phone' => '13800138000',
            
            // 设备信息
            'imei' => '867851234567890',
            'model' => 'iPhone 14 Pro Max',
            'brand' => 'Apple',
            'color' => '深空黑色',
            'memory' => '8GB',
            'capacity' => '256GB',
            
            // 质检信息
            'condition_level' => '9成新',
            'check_result' => '外观良好功能正常电池健康度85%屏幕无划痕摄像头清晰充电接口正常',
            'check_staff' => $staff_name,
            'check_staff_name' => $staff_name,
            'check_time' => date('Y-m-d H:i:s'),
            'check_date' => date('Y-m-d'),
            
            // 价格信息
            'price' => '5000.00',
            'final_price' => '5000.00',
            'price_staff' => $staff_name,
            'price_staff_name' => $staff_name,
            'price_date' => date('Y-m-d'),
            
            // 时间信息
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'current_time' => date('Y-m-d H:i:s'),
            'datetime' => date('Y-m-d H:i:s'),
            
            // 其他信息
            'site_name' => '回收中心',
            'staff_name' => $staff_name,
            
            // 二维码和条形码内容
            'qrcode_content' => 'https://example.com/order/RC' . date('YmdHis'),
            'barcode_content' => '867851234567890',
            
            // 兼容旧格式
            'content' => '<PAGE><SIZE>58,40</SIZE><TEXT x="8" y="8" w="1" h="1" r="0">设备回收质检标签</TEXT></PAGE>'
        ];
    }
    
    /**
     * 发送到打印机
     * @param array $printer
     * @param string $content
     * @return array
     */
    private function sendToPrinter(array $printer, string $content): array
    {

       
        try {
            // 检查打印机配置
            if (empty($printer['user_name']) || empty($printer['user_key']) || empty($printer['sn'])) {
                throw new AdminException('打印机配置不完整，请检查用户名、密钥和设备号');
            }
            
            // 保存当前打印机信息以供日志使用
            $this->current_printer = $printer;
            
            // 修复内容格式问题 - 确保XML属性中的引号正确处理
            $content = $this->fixXmlQuotes($content);
            
            // 芯烨云标签打印API - 使用printLabel接口
            $api_url = 'https://open.xpyun.net/api/openapi/xprinter/printLabel';
            
            // 生成签名
            $timestamp = time();
            $sign_str = $printer['user_name'] . $printer['user_key'] . $timestamp;
            $sign = sha1($sign_str);

            $post_data = [
                'user' => $printer['user_name'],
                'timestamp' => $timestamp,
                'sign' => $sign,
                'debug' => 0,
                'sn' => $printer['sn'],
                'content' => $content,
                'copies' => 1,
                'horizontalOffset' => 0,
                'verticalOffset' => 0
            ];
            
            // 记录请求日志
            \think\facade\Log::info('芯烨云标签打印请求', [
                'url' => $api_url,
                'printer' => $printer['printer_name'],
                'content_preview' => substr($content, 0, 200) . '...',
                'content_length' => strlen($content)
            ]);
            
            // 使用JSON格式调用API
            $result = $this->sendJsonRequest($api_url, $post_data);
           

            
            return $result;
            
        } catch (AdminException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage()
            ];
        }
    }

    /**
     * 修复XML中的引号问题
     * @param string $content
     * @return string
     */
    private function fixXmlQuotes(string $content): string
    {
        // 记录原始内容
        \think\facade\Log::info('修复XML引号前', ['content' => $content]);
        
        // 芯烨云要求XML属性值必须用双引号包围，但内容本身不能包含未转义的双引号
        // 先确保所有XML属性都正确使用双引号
        $patterns = [
            // 修复可能的单引号属性
            '/(\w+)=\'([^\']*)\'/m' => '$1="$2"',
            // 修复可能缺失的引号
            '/(\w+)=([^"\s>]+)/m' => '$1="$2"',
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // 确保TEXT标签内的文本内容不包含问题字符
        $content = preg_replace_callback('/<TEXT[^>]*>([^<]*)<\/TEXT>/', function($matches) {
            $tag_attrs = $matches[0];
            $text_content = $matches[1];
            
            // 对文本内容进行适当的转义
            $text_content = htmlspecialchars($text_content, ENT_QUOTES, 'UTF-8', false);
            
            return str_replace($matches[1], $text_content, $tag_attrs);
        }, $content);
        
        // 记录修复后的内容
        \think\facade\Log::info('修复XML引号后', ['content' => $content]);
        
        return $content;
    }

    /**
     * 修复打印内容格式
     * @param string $content
     * @return string
     */
    private function fixPrintContent(string $content): string
    {
        // 添加调试日志
        \think\facade\Log::info('原始打印内容', ['content' => $content]);
        
        // 首先尝试修复错误的XML格式

        
        // 检查是否已经是正确的XML格式
        if (strpos($content, '<PAGE>') !== false && strpos($content, '<TEXT x=') !== false) {
            \think\facade\Log::info('内容已是正确XML格式，直接使用');
            return $content;
        }
        
        // 如果不是XML格式，转换为标准的芯烨云指令格式
        // 解析原始内容，尝试提取有用信息
        $lines = explode(' ', trim($content));
        
        $fixed_content = '<PAGE>';
        $fixed_content .= '<SIZE>58,40</SIZE>';
        $fixed_content .= '<TEXT x="8" y="8" w="1" h="1" r="0">设备回收标签</TEXT>';
        
        // 如果有内容，尝试解析并显示
        if (!empty($lines)) {
            $y_pos = 32;
            $line_height = 24;
            $max_lines = 4; // 最多显示4行内容
            
            for ($i = 0; $i < min(count($lines), $max_lines); $i++) {
                $line_content = trim($lines[$i]);
                if (!empty($line_content) && $line_content !== '{{condition}}') {
                    $fixed_content .= '<TEXT x="8" y="' . $y_pos . '" w="1" h="1" r="0">' . htmlspecialchars($line_content) . '</TEXT>';
                    $y_pos += $line_height;
                }
            }
        }
        
        // 添加时间戳
        $fixed_content .= '<TEXT x="8" y="128" w="1" h="1" r="0">时间: ' . date('Y-m-d H:i:s') . '</TEXT>';
        $fixed_content .= '</PAGE>';
        
        \think\facade\Log::info('转换后的打印内容', ['fixed_content' => $fixed_content]);
        
        return $fixed_content;
    }

    /**
     * 当前打印机信息
     * @var array
     */
    private $current_printer = [];

    /**
     * 发送JSON请求到芯烨云API
     * @param string $url
     * @param array $data
     * @return array
     */
    private function sendJsonRequest(string $url, array $data): array
    {
        // 记录请求信息
        \think\facade\Log::info('芯烨云API请求', [
            'url' => $url,
            'request_data' => array_merge($data, ['content' => substr($data['content'] ?? '', 0, 100) . '...']),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json;charset=UTF-8',
            'Content-Length: ' . strlen(json_encode($data)),
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);
        
        // 记录详细的响应日志
        \think\facade\Log::info('芯烨云标签打印响应详情', [
            'url' => $url,
            'http_code' => $http_code,
            'curl_errno' => $curl_errno,
            'curl_error' => $curl_error,
            'response_preview' => substr($response ?: '', 0, 500),
            'content_preview' => substr($data['content'] ?? '', 0, 200) . '...'
        ]);
        
        // 简化日志记录 - 分开记录避免复杂结构
        \think\facade\Log::info("API响应 - HTTP状态码: {$http_code}");
        if ($curl_errno) {
            \think\facade\Log::info("CURL错误码: {$curl_errno}");
        }
        if ($curl_error) {
            \think\facade\Log::info("CURL错误信息: {$curl_error}");
        }
        if ($response) {
            \think\facade\Log::info("API响应内容: " . substr($response, 0, 1000));
        } else {
            \think\facade\Log::info("API响应内容: 空响应");
        }
        
        // 检查CURL错误
        if ($response === false || !empty($curl_error)) {
            $error_message = '网络请求失败';
            if ($curl_errno) {
                $error_message .= "（错误码：{$curl_errno}）";
            }
            if ($curl_error) {
                $error_message .= "：{$curl_error}";
            }
            
            return [
                'success' => false,
                'message' => $error_message,
                'http_code' => $http_code,
                'debug_info' => [
                    'curl_errno' => $curl_errno,
                    'curl_error' => $curl_error,
                    'url' => $url
                ]
            ];
        }
        
        // 检查HTTP状态码
        if ($http_code !== 200) {
            return [
                'success' => false,
                'message' => "HTTP请求失败，状态码：{$http_code}",
                'http_code' => $http_code,
                'response_preview' => substr($response, 0, 500),
                'debug_info' => [
                    'http_code' => $http_code,
                    'response' => $response
                ]
            ];
        }
        
        // 解析JSON响应
        $result = json_decode($response, true);
        
        if ($result === null) {
            return [
                'success' => false,
                'message' => 'API响应格式错误，无法解析JSON',
                'http_code' => $http_code,
                'response_preview' => substr($response, 0, 500),
                'debug_info' => [
                    'json_error' => json_last_error_msg(),
                    'raw_response' => $response
                ]
            ];
        }
        
        // 检查API返回结果
        if (isset($result['msg']) && $result['msg'] === 'ok') {

            return [
                'success' => true,
                'message' => '标签打印成功',
                'printer_info' => [
                    'name' => $this->current_printer['printer_name'] ?? '',
                    'sn' => $this->current_printer['sn'] ?? ''
                ],
                'api_response' => $result,
                'debug_info' => [
                    'content_length' => strlen($data['content'] ?? ''),
                    'api_msg' => $result['msg'] ?? '',
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        } else {
            $error_msg = $result['msg'] ?? '未知错误';
            
            // 常见错误代码解释
            $error_explanations = [
                '签名错误' => '检查user、user_key和timestamp参数',
                '设备不存在' => '检查打印机SN是否正确',
                '设备离线' => '检查打印机是否在线',
                '参数错误' => '检查content内容格式是否正确',
                'timestamp error' => '时间戳错误，请检查系统时间',
                'user not exist' => '用户不存在，请检查用户名',
                'sn not exist' => '设备序列号不存在'
            ];
            
            $explanation = '';
            foreach ($error_explanations as $keyword => $desc) {
                if (strpos(strtolower($error_msg), strtolower($keyword)) !== false) {
                    $explanation = $desc;
                    break;
                }
            }
            
            return [
                'success' => false,
                'message' => '打印失败：' . $error_msg,
                'explanation' => $explanation,
                'http_code' => $http_code,
                'api_response' => $result,
                'debug_info' => [
                    'api_error' => $error_msg,
                    'api_code' => $result['code'] ?? '',
                    'content_preview' => substr($data['content'] ?? '', 0, 200)
                ]
            ];
        }
    }

    /**
     * 简单测试打印 - 直接使用固定的标准内容
     * @return array
     */
    public function simpleTestPrint(): array
    {
        try {
            // 获取默认打印机
            $printer = $this->getDefaultPrinter();
            if (empty($printer)) {
                throw new AdminException('未找到可用的打印机，请先配置打印机');
            }
            
            // 创建标准的芯烨云测试内容
            $test_content = '<PAGE>';
            $test_content .= '<SIZE>58,40</SIZE>';
            $test_content .= '<TEXT x="8" y="8" w="1" h="1" r="0">设备回收质检标签</TEXT>';
            $test_content .= '<TEXT x="8" y="32" w="1" h="1" r="0">IMEI: 123456789012345</TEXT>';
            $test_content .= '<TEXT x="8" y="56" w="1" h="1" r="0">设备: iPhone 14 Pro 深空黑</TEXT>';
            $test_content .= '<TEXT x="8" y="80" w="1" h="1" r="0">质检员: 测试员</TEXT>';
            $test_content .= '<TEXT x="8" y="104" w="1" h="1" r="0">时间: ' . date('Y-m-d H:i:s') . '</TEXT>';
            $test_content .= '</PAGE>';
            
            \think\facade\Log::info('简单测试打印内容', [
                'test_content' => $test_content,
                'printer' => $printer['printer_name']
            ]);
            
            // 直接调用打印API
            return $this->sendToPrinter($printer, $test_content);
            
        } catch (AdminException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '系统错误：' . $e->getMessage()
            ];
        }
    }

    /**
     * 修复错误的XML格式内容
     * 芯烨云打印要求：
     * 1. 请求头使用 Content-Type: application/json;charset=UTF-8
     * 2. 文本内容中的 < 用 &lt 表示，> 用 &gt 表示
     * 3. 1mm = 8dots
     * @param string $content
     * @return string
     */
    public function fixBrokenXml(string $content): string
    {
        \think\facade\Log::info('后端接收到的content', ['content' => $content]);
        
        // 前端已经处理好XML格式和转义，这里只做基本验证和清理
        if (empty($content)) {
            return '<PAGE><SIZE>58,40</SIZE></PAGE>';
        }
        
        // 移除可能的多余空白和换行符，确保紧凑格式
        $cleaned_content = preg_replace('/>\s+</', '><', trim($content));
        $cleaned_content = str_replace(["\n", "\r", "\t"], '', $cleaned_content);
        
        // 基本格式验证
        if (!str_contains($cleaned_content, '<PAGE>') || !str_contains($cleaned_content, '</PAGE>')) {
            \think\facade\Log::warning('XML格式不完整，添加PAGE标签', ['content' => $cleaned_content]);
            
            // 如果缺少PAGE标签，添加基本结构
            if (!str_contains($cleaned_content, '<PAGE>')) {
                $cleaned_content = '<PAGE>' . $cleaned_content;
            }
            if (!str_contains($cleaned_content, '</PAGE>')) {
                $cleaned_content = $cleaned_content . '</PAGE>';
            }
            
            // 确保有SIZE标签
            if (!str_contains($cleaned_content, '<SIZE>')) {
                $cleaned_content = str_replace('<PAGE>', '<PAGE><SIZE>58,40</SIZE>', $cleaned_content);
            }
        }
        
        \think\facade\Log::info('后端处理后的content', [
            'original' => $content,
            'cleaned' => $cleaned_content,
            'reason' => 'frontend_escaped_content'
        ]);
        
        return $cleaned_content;
    }

    /**
     * 批量修复所有模板的XML格式
     * @return array
     */
    public function batchFixXmlFormat(): array
    {
        $templates = $this->model->where([
            ['site_id', '=', $this->site_id]
        ])->field('template_id,template_name,content')->select()->toArray();
        
        $fixed_count = 0;
        $results = [];
        
        foreach ($templates as $template) {
            $original_content = $template['content'];
            $fixed_content = $this->fixBrokenXml($original_content);
            
            // 如果内容有变化，说明进行了修复
            if ($original_content !== $fixed_content) {
                $this->model->where([
                    ['template_id', '=', $template['template_id']],
                    ['site_id', '=', $this->site_id]
                ])->update([
                    'content' => $fixed_content,
                    'update_time' => time()
                ]);
                
                $fixed_count++;
                $results[] = [
                    'template_id' => $template['template_id'],
                    'template_name' => $template['template_name'],
                    'status' => 'fixed',
                    'original_content' => $original_content,
                    'fixed_content' => $fixed_content
                ];
            } else {
                $results[] = [
                    'template_id' => $template['template_id'],
                    'template_name' => $template['template_name'],
                    'status' => 'no_change'
                ];
            }
        }
        
        return [
            'total_templates' => count($templates),
            'fixed_count' => $fixed_count,
            'results' => $results
        ];
    }

    /**
     * 批量同步所有模板的instruction_content字段
     * @return array
     */
    public function syncAllInstructionContent(): array
    {
        $templates = $this->model->where([
            ['site_id', '=', $this->site_id]
        ])->field('template_id,template_name,content,instruction_content')->select()->toArray();
        
        $updated_count = 0;
        $results = [];
        
        foreach ($templates as $template) {
            $need_update = false;
            $update_data = [];
            
            // 检查是否需要更新instruction_content
            if (!empty($template['content'])) {
                $content_data = json_decode($template['content'], true);
                if ($content_data) {
                    // 是JSON格式，生成或更新instruction_content
                    $new_instruction_content = $this->convertJsonToXinYeContent($content_data);
                    
                    if (empty($template['instruction_content']) || $template['instruction_content'] !== $new_instruction_content) {
                        $update_data['instruction_content'] = $new_instruction_content;
                        $need_update = true;
                    }
                } else {
                    // 是XML格式，转换为JSON并生成instruction_content
                    $content_data = $this->convertXmlToJson($template['content']);
                    $new_content = json_encode($content_data);
                    $new_instruction_content = $this->convertJsonToXinYeContent($content_data);
                    
                    $update_data['content'] = $new_content;
                    $update_data['instruction_content'] = $new_instruction_content;
                    $need_update = true;
                }
            }
            
            if ($need_update) {
                $this->model->where([
                    ['template_id', '=', $template['template_id']],
                    ['site_id', '=', $this->site_id]
                ])->update($update_data);
                
                $updated_count++;
                $results[] = [
                    'template_id' => $template['template_id'],
                    'template_name' => $template['template_name'],
                    'status' => 'updated',
                    'update_data' => $update_data
                ];
            } else {
                $results[] = [
                    'template_id' => $template['template_id'],
                    'template_name' => $template['template_name'],
                    'status' => 'no_change'
                ];
            }
        }
        
        return [
            'total_count' => count($templates),
            'updated_count' => $updated_count,
            'results' => $results
        ];
    }

    /**
     * 安全的时间格式化方法
     * @param mixed $time 时间值（可能是时间戳、字符串或空值）
     * @param string $format 时间格式
     * @return string
     */
    private function formatSafeTime($time, string $format = 'Y-m-d H:i:s'): string
    {
        if (empty($time)) {
            return '';
        }
        
        // 如果是数字（时间戳）
        if (is_numeric($time)) {
            return date($format, (int)$time);
        }
        
        // 如果是字符串，尝试转换为时间戳
        if (is_string($time)) {
            $timestamp = strtotime($time);
            if ($timestamp !== false) {
                return date($format, $timestamp);
            }
        }
        
        // 如果都失败了，返回空字符串
        return '';
    }

    /**
     * 根据设备ID获取实际打印数据
     * @param int $device_id
     * @return array
     */
    public function getDevicePrintData(int $device_id): array
    {
       
        // 查询设备数据，使用with关联查询质检员和定价员信息
        $device_model = new \addon\recycle\app\model\RecycleDevice();
        $device = $device_model->with(['checkUser', 'priceUser'])->where([
            ['id', '=', $device_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();

       
       
        if (empty($device)) {
            throw new AdminException('设备不存在');
        }
        
        // 获取订单信息（如果需要）
        $order_model = new \addon\recycle\app\model\RecycleOrder();
        $order = [];
        if (!empty($device['order_id'])) {
            $order = $order_model->where([
                ['id', '=', $device['order_id']],
                ['site_id', '=', $this->site_id]
            ])->findOrEmpty()->toArray();
        }
        
        // 使用设备模型中定义的固定分类数据
        $categories = [
            1 => '手机',
            2 => '平板',
            3 => '笔记本',
            4 => '手表',
            5 => '其他'
        ];
        $category_name = $categories[$device['category_id'] ?? 1] ?? '手机';
        
        // 状态名称映射
        $status_names = [
            1 => '待质检',
            2 => '质检中', 
            3 => '已质检',
            4 => '待确认',
            5 => '已回收',
            6 => '已退回',
            7 => '已定价',
            8 => '已定价（重新定价）'
        ];
        
        $check_status_names = [
            0 => '未质检',
            1 => '质检中',
            2 => '已质检'
        ];
        
        // 组装打印数据
        return [
            // 设备基本信息
            'device_id' => '设备id:'.(string)$device['id'],
            'id' => '设备id:'.(string)$device['id'],
            'imei' =>$device['imei'] ?? '',
            'model' => '型号:'. $device['model'] ?? '',
            
            // 订单信息
            'order_id' => '订单id:'.(string)($device['order_id'] ?? 0),
            'order_no' => '订单号:'.$order['order_no'] ?? '',
            
            // 分类信息（使用固定分类数据）
            'category_id' => '分类id:'.(string)($device['category_id'] ?? 1),
            'category_name' => '分类:'.$category_name,
            // 'brand' => '品牌:'.$device['brand'] ?? '', // 品牌信息直接从设备表获取
            
            // 价格信息
            'initial_price' =>  '初始价格:'.$device['initial_price'] ? number_format((float)$device['initial_price'], 2) : '0.00',
            'final_price' => '最终价格:'.$device['final_price'] ? number_format((float)$device['final_price'], 2) : '0.00',
            'price' => '最终价格:'.$device['final_price'] ? number_format((float)$device['final_price'], 2) : ($device['initial_price'] ? number_format((float)$device['initial_price'], 2) : '0.00'),
            
            // 状态信息
            'status' => '状态id:'.(string)($device['status'] ?? 1),
            'status_name' =>  '状态:'.$status_names[$device['status'] ?? 1] ?? '未知状态',
            'check_status' => '质检状态:'.(string)($device['check_status'] ?? 0),
            'check_status_name' => $check_status_names[$device['check_status'] ?? 0] ?? '未知状态',
            'final_status' =>   (string)($device['final_status'] ?? 0),
            'final_status_name' => '状态:'.($device['final_status'] ? '已确认' : '未确认'),
            
            // 质检信息（使用关联查询的数据）
            'check_result' =>  '结果:'.$device['check_result'] ?? '',
            'check_staff' => '质检员:'.$device['checkUser']['username'] ?? '',
            'check_date' => '质检时间:'.$this->formatSafeTime($device['check_at']),
            'check_time' => '质检时间:'.$this->formatSafeTime($device['check_at']),

            
            // 定价信息（使用关联查询的数据）
            'price_staff' => $device['checkUser']['username'] ?? '',
            'price_staff_name' => $device['checkUser']['real_name'] ?? $device['price_user']['username'] ?? '',
            'price_time' => '打款时间'.$this->formatSafeTime($device['price_at']),

            'price_remark' => '备注'.$device['price_remark'] ?? '',
            
            // 会员信息
            'member_id' => '会员:'.(string)($device['member_id'] ?? 0),
            
            // 时间信息
            'create_time' =>'入库时间:'.$this->formatSafeTime($device['create_at']),
            'update_time' => '更新时间:'.$this->formatSafeTime($device['update_at']),
            'current_time' => date('Y-m-d H:i:s'),
            'current_date' => date('Y-m-d'),
            'datetime' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            
            // 备注信息
            'remark' => '备注:'.$device['remark'] ?? '',
            
            // 二维码和条形码内容
            'qrcode_content' => "{$device['imei']}",
            'barcode_content' => $device['imei'] ?? '',
            
            // 其他常用字段
            'site_name' => '回收中心'
        ];
    }

    /**
     * 打印设备标签
     * @param int $device_id 设备ID
     * @param string $template_type 模板类型，默认为device_label
     * @return array
     */
    public function printDeviceLabel(int $device_id, string $template_type = 'device_label'): array
    {


        try {
            // 获取默认模板
            $template = $this->getDefaultTemplate($template_type);
            if (empty($template)) {
                throw new AdminException('未找到可用的模板，请先配置默认模板');
            }
        
            // 获取完整模板信息
            $template_info = $this->getInfo($template['template_id']);

            
            if (empty($template_info)) {
                throw new AdminException('模板信息获取失败');
            }
            
            // 获取设备打印数据
            $device_data = $this->getDevicePrintData($device_id);
            
           
            // 获取默认打印机
            $printer = $this->getDefaultPrinter();
            if (empty($printer)) {
                throw new AdminException('未找到可用的打印机，请先配置打印机');
            }
            
            // 使用模板的instruction_content进行打印
            $print_content = $template_info['instruction_content'] ?? '';
            if (empty($print_content)) {
                throw new AdminException('模板缺少打印指令内容');
            }
            
            
            // 替换变量
            $final_content = $this->replaceVariables($print_content, $device_data);
           
            // 记录打印信息
            \think\facade\Log::info('设备标签打印', [
                'device_id' => $device_id,
                'template_id' => $template['template_id'],
                'template_name' => $template_info['template_name'],
                'printer_name' => $printer['printer_name'],
                'device_data' => $device_data,
                'final_content' => $final_content
            ]);
            
           
            // var_dump($final_content);
            // exit;
            // 发送打印
            return $this->sendToPrinter($printer, $final_content);
            
        } catch (AdminException $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            throw new AdminException('系统错误：' . $e->getMessage());
            
        }
    }

    /**
     * 诊断网络连接问题
     * @return array
     */
    public function diagnoseNetworkIssue(): array
    {
        $diagnosis = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];
        
        // 1. 测试DNS解析
        $host = 'open.xpyun.net';
        $ip = gethostbyname($host);
        $diagnosis['tests']['dns'] = [
            'host' => $host,
            'resolved_ip' => $ip,
            'status' => ($ip !== $host) ? 'success' : 'failed'
        ];
        
        // 2. 测试基本HTTP连接
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://open.xpyun.net');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_NOBODY, true); // 只获取HEAD
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $connect_time = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        curl_close($ch);
        
        $diagnosis['tests']['basic_connection'] = [
            'url' => 'https://open.xpyun.net',
            'http_code' => $http_code,
            'curl_errno' => $curl_errno,
            'curl_error' => $curl_error,
            'connect_time' => $connect_time,
            'status' => ($http_code > 0) ? 'success' : 'failed'
        ];
        
        // 3. 获取打印机配置状态
        $printer = $this->getDefaultPrinter();
        $diagnosis['tests']['printer_config'] = [
            'has_printer' => !empty($printer),
            'printer_name' => $printer['printer_name'] ?? '',
            'has_user_name' => !empty($printer['user_name']),
            'has_user_key' => !empty($printer['user_key']),
            'has_sn' => !empty($printer['sn']),
            'status' => (!empty($printer) && !empty($printer['user_name']) && !empty($printer['user_key']) && !empty($printer['sn'])) ? 'complete' : 'incomplete'
        ];
        
        // 4. 测试API接口可用性（不实际打印）
        if (!empty($printer) && !empty($printer['user_name']) && !empty($printer['user_key'])) {
            $timestamp = time();
            $sign_str = $printer['user_name'] . $printer['user_key'] . $timestamp;
            $sign = sha1($sign_str);
            
            $test_data = [
                'user' => $printer['user_name'],
                'timestamp' => $timestamp,
                'sign' => $sign,
                'debug' => 1
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://open.xpyun.net/api/openapi/xprinter/printLabel');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json;charset=UTF-8'
            ]);
            
            $api_response = curl_exec($ch);
            $api_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $api_curl_error = curl_error($ch);
            $api_curl_errno = curl_errno($ch);
            curl_close($ch);
            
            $diagnosis['tests']['api_availability'] = [
                'url' => 'https://open.xpyun.net/api/openapi/xprinter/printLabel',
                'http_code' => $api_http_code,
                'curl_errno' => $api_curl_errno,
                'curl_error' => $api_curl_error,
                'response_preview' => substr($api_response ?: '', 0, 200),
                'status' => ($api_http_code == 200) ? 'success' : 'failed'
            ];
            
            // 尝试解析API响应
            if ($api_response) {
                $api_result = json_decode($api_response, true);
                if ($api_result && isset($api_result['msg'])) {
                    $diagnosis['tests']['api_availability']['api_message'] = $api_result['msg'];
                }
            }
        }
        
        // 总体诊断结果
        $overall_status = 'unknown';
        $issues = [];
        
        if ($diagnosis['tests']['dns']['status'] !== 'success') {
            $issues[] = 'DNS解析失败';
        }
        if ($diagnosis['tests']['basic_connection']['status'] !== 'success') {
            $issues[] = '基础网络连接失败';
        }
        if ($diagnosis['tests']['printer_config']['status'] !== 'complete') {
            $issues[] = '打印机配置不完整';
        }
        if (isset($diagnosis['tests']['api_availability']) && $diagnosis['tests']['api_availability']['status'] !== 'success') {
            $issues[] = 'API接口不可用';
        }
        
        if (empty($issues)) {
            $overall_status = 'healthy';
        } else {
            $overall_status = 'issues_found';
        }
        
        $diagnosis['overall'] = [
            'status' => $overall_status,
            'issues' => $issues,
            'recommendation' => $this->getRecommendation($issues)
        ];
        
        return $diagnosis;
    }
    
    /**
     * 获取问题建议
     * @param array $issues
     * @return string
     */
    private function getRecommendation(array $issues): string
    {
        if (empty($issues)) {
            return '网络连接正常，可以进行打印操作';
        }
        
        $recommendations = [
            'DNS解析失败' => '检查网络设置和DNS配置',
            '基础网络连接失败' => '检查防火墙设置和网络连接',
            '打印机配置不完整' => '请完善打印机的用户名、密钥和设备号配置',
            'API接口不可用' => '检查芯烨云服务状态和API密钥是否正确'
        ];
        
        $advice = [];
        foreach ($issues as $issue) {
            if (isset($recommendations[$issue])) {
                $advice[] = $recommendations[$issue];
            }
        }
        
        return implode('；', $advice);
    }
} 