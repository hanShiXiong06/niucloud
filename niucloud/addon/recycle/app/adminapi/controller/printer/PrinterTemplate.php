<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\adminapi\controller\printer;

use addon\recycle\app\service\admin\printer\RecyclePrinterTemplateService;
use core\base\BaseAdminController;
use think\App;
use core\exception\CommonException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 回收打印模板控制器
 * Class PrinterTemplate
 * @package addon\recycle\app\adminapi\controller\printer
 */
class PrinterTemplate extends BaseAdminController
{
    /**
     * 模板服务
     * @var RecyclePrinterTemplateService
     */
    protected $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecyclePrinterTemplateService();
    }
  
    /**
     * 获取模板列表
     * @return Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['template_name', ''],
            ['template_type', ''],
            ['status', '']
        ]);
        
        return success($this->service->getPage($data));
    }

    /**
     * 获取模板详情
     * @param int $id
     * @return Response
     */
    public function info(int $id)
    {
        return success($this->service->getInfo($id));
    }

    /**
     * 添加模板
     * @return Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['template_name', ''],
            ['template_type', ''],
            ['width', 58],
            ['height', 40],
            ['content', ''],
            ['html_content', ''],
            ['variables', []],
            ['status', 1],
            ['is_default', 0]
        ]);
        
        // 添加调试日志 - 记录控制器接收到的原始数据
        \think\facade\Log::info('控制器接收到的原始数据', [
            'content' => $data['content'],
            'content_type' => gettype($data['content']),
            'content_length' => is_string($data['content']) ? strlen($data['content']) : 0,
            'raw_post' => file_get_contents('php://input')
        ]);
        
        // 验证参数
        $this->validate($data, [
            'template_name' => 'require',
            'template_type' => 'require',
            'width' => 'require|number',
            'height' => 'require|number'
        ]);
        
        $this->service->add($data);
        return success('添加成功');
    }

    /**
     * 编辑模板
     * @param int $id
     * @return Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['template_name', ''],
            ['template_type', ''],
            ['width', 58],
            ['height', 40],
            ['content', ''],
            ['html_content', ''],
            ['variables', []],
            ['status', 1],
            ['is_default', 0]
        ]);
        
        // 验证参数
        $this->validate($data, [
            'template_name' => 'require',
            'template_type' => 'require',
            'width' => 'require|number',
            'height' => 'require|number'
        ]);
        
        $this->service->edit($id, $data);
        return success('编辑成功');
    }

    /**
     * 删除模板
     * @param int $id
     * @return Response
     */
    public function del(int $id)
    {
        $this->service->del($id);
        return success('删除成功');
    }

    /**
     * 修改模板状态
     * @param int $id
     * @return Response
     */
    public function modifyStatus(int $id)
    {
        $data = $this->request->params([
            ['status', 1]
        ]);
        
        $this->service->modifyStatus($id, (int)$data['status']);
        return success('状态修改成功');
    }
    
    /**
     * 设置默认模板
     * @param int $id
     * @return Response
     */
    public function setDefault(int $id)
    {
        $this->service->setDefault($id);
        return success('设置成功');
    }

    /**
     * 获取模板类型列表
     * @return Response
     */
    public function getTypeList()
    {
        return success($this->service->getTypeList());
    }
    
    /**
     * 根据类型获取默认模板
     * @return Response
     */
    public function getDefaultTemplate()
    {
        $data = $this->request->params([
            ['template_type', '']
        ]);
        
        $this->validate($data, [
            'template_type' => 'require'
        ]);
        
        return success($this->service->getDefaultTemplate($data['template_type']));
    }

    /**
     * 预览模板
     * @param int $id
     * @return Response
     */
    public function preview(int $id)
    {
        $template = $this->service->getInfo($id);
        
        if (empty($template)) {
            return fail('模板不存在');
        }

        // 返回HTML预览内容
        return success([
            'html_content' => $template['html_content'],
            'template_info' => $template
        ]);
    }

    /**
     * 获取打印机列表
     * @return Response
     */
    public function getPrinterList()
    {
        $printer = $this->service->getDefaultPrinter();
        
        return success([
            'default_printer' => $printer,
            'has_printer' => !empty($printer)
        ]);
    }

    /**
     * 测试打印模板
     * @param int $id
     * @return Response
     */
    public function testPrint(int $id)
    {
        $data = $this->request->params([
            ['test_data', []]
        ]);

        $result = $this->service->testPrint($id, $data['test_data']);
        
        if ($result['success']) {
            return success($result);
        } else {
            return fail($result['message']);
        }
    }

    /**
     * 简单测试打印（使用固定内容）
     * @return Response
     */
    public function simpleTestPrint()
    {
        $result = $this->service->simpleTestPrint();
        
        if ($result['success']) {
            return success($result);
        } else {
            return fail($result['message']);
        }
    }

    /**
     * 修复模板XML格式
     * @param int $id
     * @return Response
     */
    public function fixXmlFormat(int $id)
    {
        $template = $this->service->getInfo($id);
        
        if (empty($template)) {
            return fail('模板不存在');
        }

        $fixed_content = $this->service->fixBrokenXml($template['content']);
        
        // 更新模板内容
        $this->service->edit($id, ['content' => $fixed_content]);
        
        return success([
            'message' => 'XML格式修复成功',
            'original_content' => $template['content'],
            'fixed_content' => $fixed_content
        ]);
    }

    /**
     * 批量修复所有模板的XML格式
     * @return Response
     */
    public function batchFixXmlFormat()
    {
        $result = $this->service->batchFixXmlFormat();
        
        return success([
            'message' => "批量修复完成，共修复 {$result['fixed_count']} 个模板",
            'data' => $result
        ]);
    }

    /**
     * 临时测试XML处理
     * @return Response
     */
    public function testXmlProcessing()
    {
        $test_content = '<PAGE><SIZE>58,40</SIZE><TEXT x="88" y="54" font="3" w="1" h="1" r="0">{{order_id}}</TEXT><TEXT x="242" y="188" font="3" w="1" h="1" r="0">{{imei}}</TEXT></PAGE>';
        
        // 模拟保存过程
        $data = [
            'template_name' => 'test_xml_processing',
            'template_type' => 'device_label',
            'width' => 58,
            'height' => 40,
            'content' => $test_content,
            'status' => 1,
            'is_default' => 0
        ];
        
        \think\facade\Log::info('测试XML处理 - 原始数据', [
            'original_content' => $test_content,
            'content_length' => strlen($test_content)
        ]);
        
        // 调用服务层保存
        try {
            $this->service->add($data);
            
            // 立即查询最新保存的模板
            $latest_template = $this->service->model->where([
                ['template_name', '=', 'test_xml_processing'],
                ['site_id', '=', $this->service->site_id]
            ])->order('template_id', 'desc')->find();
            
            return success([
                'message' => 'XML处理测试完成',
                'original_content' => $test_content,
                'saved_content' => $latest_template['content'] ?? '未找到',
                'content_match' => ($latest_template['content'] ?? '') === $test_content
            ]);
            
        } catch (\Exception $e) {
            return error('测试失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试JSON格式模板处理
     * @return Response
     */
    public function testJsonFormat()
    {
        // 创建测试用的JSON格式模板数据
        $contentData = [
            'width' => 58,
            'height' => 40,
            'elements' => [
                [
                    'type' => 'text',
                    'content' => '{{order_id}}',
                    'x' => 88,
                    'y' => 54,
                    'font' => '3',
                    'width_scale' => '1',
                    'height_scale' => '1',
                    'rotation' => '0'
                ],
                [
                    'type' => 'text',
                    'content' => '{{brand}}',
                    'x' => 138,
                    'y' => 40,
                    'font' => '3',
                    'width_scale' => '1',
                    'height_scale' => '1',
                    'rotation' => '0'
                ]
            ]
        ];
        
        $data = [
            'template_name' => 'JSON格式测试模板',
            'template_type' => 'device_label',
            'width' => 58,
            'height' => 40,
            'status' => 1,
            'is_default' => 0,
            'content' => $contentData,
            'variables' => [
                ['key' => 'order_id', 'name' => '订单号'],
                ['key' => 'brand', 'name' => '品牌']
            ]
        ];
        
        try {
            // 保存模板
            $this->service->add($data);
            
            // 获取最新保存的模板
            $latest_template = $this->service->model->where([
                ['template_name', '=', 'JSON格式测试模板'],
                ['site_id', '=', $this->service->site_id]
            ])->order('template_id', 'desc')->find();
            
            if ($latest_template) {
                // 读取模板详情
                $template_info = $this->service->getInfo($latest_template['template_id']);
                
                return success([
                    'message' => 'JSON格式测试成功',
                    'original_data' => $contentData,
                    'saved_content' => $latest_template['content'],
                    'parsed_data' => $template_info['content'] ?? null,
                    'xml_content' => $template_info['xml_content'] ?? null,
                    'html_content' => $template_info['html_content'] ?? null
                ]);
            } else {
                return error('未找到保存的模板');
            }
            
        } catch (\Exception $e) {
            return error('测试失败: ' . $e->getMessage());
        }
    }

    /**
     * 批量同步所有模板的instruction_content字段
     * @return Response
     */
    public function syncInstructionContent()
    {
        $result = $this->service->syncAllInstructionContent();
        
        return success([
            'message' => "批量同步完成，共处理 {$result['total_count']} 个模板，更新 {$result['updated_count']} 个",
            'data' => $result
        ]);
    }

    /**
     * 调试模板转换和变量替换
     * @param int $id
     * @return Response
     */
    public function debugTemplate(int $id)
    {
        try {
            $template = $this->service->getInfo($id);
            
            if (empty($template)) {
                return error('模板不存在');
            }
            
            // 获取测试数据
            $test_data = $this->service->getTestData();
            
            // 获取原始instruction_content
            $original_instruction = $template['instruction_content'] ?? '';
            
            // 进行变量替换
            $replaced_instruction = $this->service->replaceVariables($original_instruction, $test_data);
            
            return success([
                'template_info' => [
                    'template_id' => $template['template_id'],
                    'template_name' => $template['template_name'],
                    'content' => $template['content'],
                    'instruction_content' => $original_instruction
                ],
                'test_data' => $test_data,
                'conversion_result' => [
                    'original_xml' => $original_instruction,
                    'replaced_xml' => $replaced_instruction,
                    'has_variables' => strpos($original_instruction, '{{') !== false,
                    'xml_length' => strlen($replaced_instruction)
                ],
                'debug_info' => [
                    'json_elements_count' => count($template['content']['elements'] ?? []),
                    'xml_text_tags' => substr_count($original_instruction, '<TEXT'),
                    'variables_in_xml' => preg_match_all('/\{\{([^}]+)\}\}/', $original_instruction, $matches) ? $matches[1] : []
                ]
            ]);
            
        } catch (\Exception $e) {
            return error('调试失败: ' . $e->getMessage());
        }
    }

    /**
     * 增强版测试打印（包含调试信息）
     * @param int $id
     * @return Response
     */
    public function testPrintWithDebug(int $id)
    {
        try {
            // 获取模板信息
            $template = $this->service->getInfo($id);
            if (empty($template)) {
                return error('模板不存在');
            }
            
            // 获取测试数据
            $test_data = $this->service->getTestData();
            
            // 记录调试信息
            $debug_info = [
                'template_info' => [
                    'template_id' => $template['template_id'],
                    'template_name' => $template['template_name'],
                    'has_content' => !empty($template['content']),
                    'has_instruction_content' => !empty($template['instruction_content']),
                    'content_elements_count' => count($template['content']['elements'] ?? [])
                ],
                'conversion_info' => [
                    'original_xml' => $template['instruction_content'] ?? '',
                    'xml_text_tags_count' => substr_count($template['instruction_content'] ?? '', '<TEXT'),
                    'variables_in_xml' => []
                ],
                'test_data' => $test_data
            ];
            
            // 提取XML中的变量
            if (!empty($template['instruction_content'])) {
                preg_match_all('/\{\{([^}]+)\}\}/', $template['instruction_content'], $matches);
                $debug_info['conversion_info']['variables_in_xml'] = $matches[1] ?? [];
            }
            
            // 执行测试打印
            $print_result = $this->service->testPrint($id, $test_data);
            
            return success([
                'print_result' => $print_result,
                'debug_info' => $debug_info
            ]);
            
        } catch (\Exception $e) {
            return error('测试打印失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试XML内容生成
     * @return Response
     */
    public function testXmlGeneration()
    {
        try {
            // 创建测试用的JSON数据
            $test_json = [
                'width' => 58,
                'height' => 40,
                'elements' => [
                    [
                        'type' => 'text',
                        'content' => '设备回收标签',
                        'x' => 16,
                        'y' => 16,
                        'font' => '3',
                        'width_scale' => '1',
                        'height_scale' => '1',
                        'rotation' => '0'
                    ],
                    [
                        'type' => 'variable',
                        'content' => '{{order_id}}',
                        'x' => 16,
                        'y' => 48,
                        'font' => '3',
                        'width_scale' => '1',
                        'height_scale' => '1',
                        'rotation' => '0'
                    ],
                    [
                        'type' => 'variable',
                        'content' => '{{brand}}',
                        'x' => 16,
                        'y' => 80,
                        'font' => '3',
                        'width_scale' => '1',
                        'height_scale' => '1',
                        'rotation' => '0'
                    ],
                    [
                        'type' => 'qrcode',
                        'content' => '{{qrcode_content}}',
                        'x' => 300,
                        'y' => 16,
                        'size' => '3',
                        'error_level' => 'L'
                    ]
                ]
            ];
            
            // 调用转换方法
            $xml_content = $this->service->convertJsonToXinYeContent($test_json);
            
            // 获取测试数据并替换变量
            $test_data = $this->service->getTestData();
            $replaced_xml = $this->service->replaceVariables($xml_content, $test_data);
            
            return success([
                'input_json' => $test_json,
                'generated_xml' => $xml_content,
                'test_data' => $test_data,
                'final_xml' => $replaced_xml,
                'analysis' => [
                    'elements_count' => count($test_json['elements']),
                    'xml_tags_count' => substr_count($xml_content, '<'),
                    'variables_count' => substr_count($xml_content, '{{'),
                    'xml_length' => strlen($xml_content),
                    'final_length' => strlen($replaced_xml)
                ]
            ]);
            
        } catch (\Exception $e) {
            return error('XML生成测试失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据设备ID获取打印数据
     * @param int $device_id
     * @return Response
     */
    public function getDevicePrintData(int $device_id)
    {
        try {
            $data = $this->service->getDevicePrintData($device_id);
            
            return success([
                'device_data' => $data,
                'available_variables' => array_keys($data)
            ]);
            
        } catch (\Exception $e) {
            return error('获取设备数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 打印设备标签
     * @param int $device_id
     * @return Response
     */
    public function printDeviceLabel(int $device_id)
    {
        $data = $this->request->params([
            ['template_type', 'device_label']
        ]);
        
        $result = $this->service->printDeviceLabel($device_id, $data['template_type']);
        
        if ($result['success']) {
            return success($result);
        } else {
            return error($result['message'], [], $result);
        }
    }

    /**
     * 测试设备数据打印
     * @param int $device_id
     * @return Response
     */
    public function testDevicePrint(int $device_id)
    {
        $data = $this->request->params([
            ['template_id', 0],
            ['template_type', 'device_label']
        ]);
        
        try {
            // 获取设备数据
            $device_data = $this->service->getDevicePrintData($device_id);
            
            // 确定使用的模板
            if (!empty($data['template_id'])) {
                // 使用指定模板进行测试打印
                $result = $this->service->testPrint($data['template_id'], $device_data);
            } else {
                // 使用默认模板打印
                $result = $this->service->printDeviceLabel($device_id, $data['template_type']);
            }
            
            return success([
                'device_id' => $device_id,
                'device_data' => $device_data,
                'print_result' => $result
            ]);
            
        } catch (\Exception $e) {
            return error('测试打印失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试网络连接和API可用性
     * @return Response
     */
    public function testApiConnection()
    {
        try {
            // 获取默认打印机
            $printer = $this->service->getDefaultPrinter();
            if (empty($printer)) {
                return error('未找到可用的打印机，请先配置打印机');
            }
            
            // 测试基本的网络连接
            $api_url = 'https://open.xpyun.net/api/openapi/xprinter/printLabel';
            
            // 创建简单的测试内容
            $test_content = '<PAGE><SIZE>58,40</SIZE><TEXT x="8" y="8" w="1" h="1" r="0">Connection Test</TEXT></PAGE>';
            
            // 生成签名
            $timestamp = time();
            $sign_str = $printer['user_name'] . $printer['user_key'] . $timestamp;
            $sign = sha1($sign_str);
            
            $test_data = [
                'user' => $printer['user_name'],
                'timestamp' => $timestamp,
                'sign' => $sign,
                'debug' => 1, // 开启调试模式
                'sn' => $printer['sn'],
                'content' => $test_content,
                'copies' => 1,
                'horizontalOffset' => 0,
                'verticalOffset' => 0
            ];
            
            // 进行CURL测试
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json;charset=UTF-8',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            $curl_info = curl_getinfo($ch);
            curl_close($ch);
            
            // 组装诊断结果
            $result = [
                'connection_test' => [
                    'api_url' => $api_url,
                    'http_code' => $http_code,
                    'curl_errno' => $curl_errno,
                    'curl_error' => $curl_error,
                    'connect_time' => $curl_info['connect_time'] ?? 0,
                    'total_time' => $curl_info['total_time'] ?? 0,
                    'response_size' => strlen($response ?: '')
                ],
                'printer_config' => [
                    'printer_name' => $printer['printer_name'] ?? '',
                    'sn' => $printer['sn'] ?? '',
                    'user_name' => $printer['user_name'] ?? '',
                    'has_user_key' => !empty($printer['user_key']),
                    'brand' => $printer['brand'] ?? '',
                    'type' => $printer['type'] ?? ''
                ],
                'request_data' => [
                    'timestamp' => $timestamp,
                    'sign' => $sign,
                    'content_length' => strlen($test_content),
                    'json_size' => strlen(json_encode($test_data))
                ],
                'response_data' => [
                    'raw_response' => $response,
                    'response_preview' => substr($response ?: '', 0, 500)
                ]
            ];
            
            // 尝试解析响应
            if ($response) {
                $api_result = json_decode($response, true);
                if ($api_result) {
                    $result['api_response'] = $api_result;
                    
                    if (isset($api_result['msg'])) {
                        if ($api_result['msg'] === 'ok') {
                            $result['diagnosis'] = '连接成功，API调用正常';
                            $result['status'] = 'success';
                        } else {
                            $result['diagnosis'] = 'API调用失败：' . $api_result['msg'];
                            $result['status'] = 'api_error';
                        }
                    }
                } else {
                    $result['diagnosis'] = 'API响应无法解析为JSON';
                    $result['status'] = 'parse_error';
                }
            } else {
                $result['diagnosis'] = '网络连接失败';
                $result['status'] = 'network_error';
            }
            
            return success($result);
            
        } catch (\Exception $e) {
            return error('连接测试失败: ' . $e->getMessage());
        }
    }
} 