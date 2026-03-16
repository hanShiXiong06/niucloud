<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\printer;

use addon\recycle\app\model\printer\RecyclePrinterTemplate;
use addon\recycle\app\service\admin\printer\template\TemplateConverterService;
use addon\recycle\app\service\admin\printer\template\TemplatePreviewService;
use addon\recycle\app\service\admin\printer\template\TemplatePrintService;
use addon\recycle\app\service\admin\printer\template\TemplateRenderService;
use addon\recycle\app\service\admin\printer\template\TemplateValidatorService;
use addon\recycle\app\service\admin\printer\template\VariableReplaceService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use core\exception\CommonException;

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

    /**
     * 转换服务
     * @var TemplateConverterService
     */
    protected $converterService;

    /**
     * 打印服务
     * @var TemplatePrintService
     */
    protected $printService;

    /**
     * 变量替换服务
     * @var VariableReplaceService
     */
    protected $variableReplaceService;

    /**
     * 预览服务
     * @var TemplatePreviewService
     */
    protected $previewService;

    /**
     * 验证服务
     * @var TemplateValidatorService
     */
    protected $validatorService;

    /**
     * 渲染服务
     * @var TemplateRenderService
     */
    protected $renderService;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecyclePrinterTemplate();
        $this->converterService = new TemplateConverterService();
        $this->printService = new TemplatePrintService();
        $this->variableReplaceService = new VariableReplaceService();
        $this->previewService = new TemplatePreviewService();
        $this->validatorService = new TemplateValidatorService();
        $this->renderService = new TemplateRenderService();
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
        $field = 'template_id,template_name,template_type,width,height,content,instruction_content,html_content,variables,status,is_default,printer_id,trigger_event,create_time,update_time';
        
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
                        $info['instruction_content'] = $this->converterService->jsonToXml($content_data);
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
                    $content_data = $this->converterService->xmlToJson($info['content']);
                    $info['content'] = $content_data;
                    $info['is_json_format'] = false;
                    
                    // 生成instruction_content
                    $info['instruction_content'] = $this->converterService->jsonToXml($content_data);
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
            
            // 重新生成HTML预览（已注释，保留接口）
        }
        
        // 添加template_data字段（用于前端可视化编辑器）
        if (isset($info['content'])) {
            if (is_array($info['content'])) {
                $info['template_data'] = $info['content'];
            } else {
                // 尝试解析JSON字符串
                $content_data = json_decode($info['content'], true);
                if ($content_data) {
                    $info['template_data'] = $content_data;
                } else {
                    // 是XML格式，转换为JSON
                    $info['template_data'] = $this->converterService->xmlToJson($info['content']);
                }
            }
        } else {
            $info['template_data'] = ['width' => 58, 'height' => 40, 'elements' => []];
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
        // 定义数据库表中存在的字段（根据SQL表结构）
        $allowedFields = [
            'site_id', 'template_name', 'template_type', 'size', 'content',
            'html_content', 'width', 'height', 'instruction_content',
            'variables', 'status', 'is_default', 'printer_id', 'trigger_event',
            'uid', 'create_time', 'update_time'
        ];
        
        $data['site_id'] = $this->site_id;
        $data['uid'] = $this->uid ?? 0;
        $data['create_time'] = time();
        $data['update_time'] = time();
        
        // 处理content字段：如果提供了template_data，优先使用
        if (isset($data['template_data']) && !empty($data['template_data'])) {
            // template_data是JSON字符串或数组，转换为content
            if (is_string($data['template_data'])) {
                $data['content'] = $data['template_data'];
            } else {
                $data['content'] = json_encode($data['template_data'], JSON_UNESCAPED_UNICODE);
            }
        }
        
        // 处理size字段（根据width和height计算）
        if (isset($data['width']) && isset($data['height'])) {
            $data['size'] = $data['width'] . 'mm';
        }
        
        // 处理variables字段（如果是数组，转换为JSON）
        if (isset($data['variables']) && is_array($data['variables'])) {
            $data['variables'] = json_encode($data['variables'], JSON_UNESCAPED_UNICODE);
        }
        
        // 处理content字段并生成instruction_content
        if (is_array($data['content'])) {
            // JSON格式，转换为XML
            $data['instruction_content'] = $this->converterService->jsonToXml($data['content']);
            $data['content'] = json_encode($data['content'], JSON_UNESCAPED_UNICODE);
        } else {
            // 字符串格式（可能是XML或JSON字符串）
            $content_data = json_decode($data['content'], true);
            if ($content_data) {
                // 是JSON字符串，转换为XML
                $data['instruction_content'] = $this->converterService->jsonToXml($content_data);
            } else {
                // 是XML格式，转换为JSON后存储
                $content_data = $this->converterService->xmlToJson($data['content']);
                $data['content'] = json_encode($content_data, JSON_UNESCAPED_UNICODE);
                $data['instruction_content'] = $this->converterService->jsonToXml($content_data);
            }
        }
        
        // 过滤掉不存在的字段，只保留数据库表中存在的字段
        $saveData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $saveData[$field] = $data[$field];
            }
        }
        
        // 如果设置为默认模板，先取消其他默认模板
        if (!empty($saveData['is_default'])) {
            $this->model->where([
                ['site_id', '=', $this->site_id],
                ['template_type', '=', $saveData['template_type']]
            ])->update(['is_default' => 0]);
        }
        
        $this->model->save($saveData);
        
        return $this->model->getKey(); // 返回新插入的模板ID
        }
        
    /**
     * 编辑模板
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        // 定义数据库表中存在的字段（根据SQL表结构，排除主键和自动字段）
        $allowedFields = [
            'template_name', 'template_type', 'size', 'content',
            'html_content', 'width', 'height', 'instruction_content',
            'variables', 'status', 'is_default', 'printer_id', 'trigger_event',
            'update_time'
                ];
                
        $data['update_time'] = time();
        
        // 处理content字段：如果提供了template_data，优先使用
        if (isset($data['template_data']) && !empty($data['template_data'])) {
            // template_data是JSON字符串或数组，转换为content
            if (is_string($data['template_data'])) {
                $data['content'] = $data['template_data'];
            } else {
                $data['content'] = json_encode($data['template_data'], JSON_UNESCAPED_UNICODE);
        }
        }
        
        // 处理size字段（根据width和height计算）
        if (isset($data['width']) && isset($data['height'])) {
            $data['size'] = $data['width'] . 'mm';
        }
        
        // 处理variables字段（如果是数组，转换为JSON）
        if (isset($data['variables']) && is_array($data['variables'])) {
            $data['variables'] = json_encode($data['variables'], JSON_UNESCAPED_UNICODE);
        }
        
        // 处理content字段并生成instruction_content
        if (isset($data['content'])) {
            if (is_array($data['content'])) {
                // JSON格式，转换为XML
                $data['instruction_content'] = $this->converterService->jsonToXml($data['content']);
                $data['content'] = json_encode($data['content'], JSON_UNESCAPED_UNICODE);
            } else {
                // 字符串格式（可能是XML或JSON字符串）
                $content_data = json_decode($data['content'], true);
                if ($content_data) {
                    // 是JSON字符串，转换为XML
                    $data['instruction_content'] = $this->converterService->jsonToXml($content_data);
                } else {
                    // 是XML格式，转换为JSON后存储
                    $content_data = $this->converterService->xmlToJson($data['content']);
                    $data['content'] = json_encode($content_data, JSON_UNESCAPED_UNICODE);
                    $data['instruction_content'] = $this->converterService->jsonToXml($content_data);
    }
            }
        }
        
        // 过滤掉不存在的字段，只保留数据库表中存在的字段
        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        // 如果设置为默认模板，先取消其他默认模板
        if (!empty($updateData['is_default'])) {
            $this->model->where([
                ['site_id', '=', $this->site_id],
                ['template_type', '=', $updateData['template_type']],
                ['template_id', '<>', $id]
            ])->update(['is_default' => 0]);
        }
        
        $this->model->where([
            ['template_id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update($updateData);
        
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
                $print_content = $this->converterService->jsonToXml($template_data);
            }
            
            // 使用打印服务替换变量并打印
            return $this->printService->printWithVariables($print_content, $test_data, $printer);
            
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
            'member_nickname' => '小明',
            'customer_phone' => '13800138000',
            'pay_type' => '支付宝',
            'pay_account' => '138****8000',
            'total_amount' => '5000.00',
            'device_count' => '4',
            'order_status' => '已完成',
            'order_remark' => '无',

            // 设备信息
            'imei' => '867851234567890',
            'imei2' => '867851234567891',
            'sn' => 'C02XG0FHJHD5',
            'model' => 'iPhone 14 Pro Max',
            'brand' => 'Apple',
            'color' => '深空黑色',
            'memory' => '8GB',
            'capacity' => '256GB',
            'system_version' => 'iOS 17.3.1',
            'warranty_info' => '2025-12-31',

            // 设备序号信息
            'device_index' => '1',
            'device_total' => '4',
            'device_number' => '1/4',

            // 质检信息
            'condition_level' => '9成新',
            'check_result' => '外观良好功能正常电池健康度85%屏幕无划痕摄像头清晰充电接口正常',
            'check_result_seller' => '外观良好功能正常',
            'check_result_buyer' => '电池健康度85%',
            'check_staff' => $staff_name,
            'check_staff_name' => $staff_name,
            'check_time' => date('Y-m-d H:i:s'),
            'check_date' => date('Y-m-d'),
            'check_status' => '已质检',

            // 价格信息
            'price' => '5000.00',
            'initial_price' => '5000.00',
            'final_price' => '4800.00',
            'sell_price' => '5200.00',
            'before_price' => '4500.00',
            'price_staff' => $staff_name,
            'price_staff_name' => $staff_name,
            'price_date' => date('Y-m-d'),
            'price_time' => date('Y-m-d H:i:s'),
            'price_remark' => '市场行情调整',

            // 快递信息
            'express_company' => '顺丰速运',
            'express_no' => 'SF1234567890',
            'delivery_type' => '快递',
            'delivery_fee' => '15.00',
            'delivery_status' => '已签收',
            'delivery_operator' => '张三',

            // 状态信息
            'status' => '已回收',
            'status_name' => '已回收',
            'final_status' => '已确认',

            // 时间信息
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'current_time' => date('Y-m-d H:i:s'),
            'current_date' => date('Y-m-d'),
            'datetime' => date('Y-m-d H:i:s'),
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'sign_time' => date('Y-m-d H:i:s'),
            'complete_time' => date('Y-m-d H:i:s'),
            'pay_time' => date('Y-m-d H:i:s'),

            // 其他信息
            'site_name' => '回收中心',
            'staff_name' => $staff_name,
            'remark' => '无',
            'member_id' => '10001',

            // 二维码和条形码内容
            'qrcode_content' => 'https://example.com/order/RC' . date('YmdHis'),
            'barcode_content' => '867851234567890',
            'device_url' => request()->domain() . '/site/recycle_order/list?id=12345',

            // 兼容旧格式
            'content' => '<PAGE><SIZE>58,40</SIZE><TEXT x="8" y="8" w="1" h="1" r="0">设备回收质检标签</TEXT></PAGE>'
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
     * 获取快递状态名称
     * @param int $status
     * @return string
     */
    private function getDeliveryStatusName(int $status): string
    {
        $names = [
            0 => '未下单',
            1 => '已下单',
            2 => '运输中',
            3 => '已签收',
            4 => '已取消'
        ];
        return $names[$status] ?? '未知';
    }

    /**
     * 根据设备ID获取实际打印数据
     * @param int $device_id
     * @return array
     */
    public function getDevicePrintData(int $device_id): array
    {

        // 查询设备数据，使用with关联查询质检员和定价员信息
        $device_model = new \addon\recycle\app\model\order\RecycleDevice();
        $device = $device_model->with(['checkUser', 'priceUser'])->where([
            ['id', '=', $device_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();



        if (empty($device)) {
            throw new AdminException('设备不存在');
        }

        // 获取订单信息（如果需要）
        $order_model = new \addon\recycle\app\model\order\RecycleOrder();
        $order = [];
        $member_nickname = '';
        if (!empty($device['order_id'])) {
            $order = $order_model->where([
                ['id', '=', $device['order_id']],
                ['site_id', '=', $this->site_id]
            ])->findOrEmpty()->toArray();
            // 关联查询会员昵称
            if (!empty($order['member_id'])) {
                $member = (new \app\model\member\Member())->where([
                    ['member_id', '=', $order['member_id']]
                ])->field('nickname')->findOrEmpty();
                $member_nickname = $member['nickname'] ?? '';
            }
        }

        // 计算设备在订单中的序号
        $device_index = 1;
        $device_total = 1;
        if (!empty($device['order_id'])) {
            // 查询同订单的所有设备，按创建时间排序
            $devices = $device_model->where([
                ['order_id', '=', $device['order_id']],
                ['site_id', '=', $this->site_id]
            ])->order('id', 'asc')->column('id');

            $device_total = count($devices);
            $device_index = array_search($device_id, $devices) + 1; // +1 因为数组从0开始
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
            'device_id' => (string)$device['id'],
            'id' => (string)$device['id'],
            'imei' => $device['imei'] ?? '',
            'imei2' => $device['imei2'] ?? '',
            'sn' => $device['sn'] ?? '',
            'model' => $device['model'] ?? '',
            'system_version' => $device['system_version'] ?? '',
            'warranty_info' => $device['warranty_info'] ?? '',
            'capacity' => $device['capacity'] ?? '',
            'color' => $device['color'] ?? '',

            // 设备序号信息
            'device_index' => (string)$device_index,
            'device_total' => (string)$device_total,
            'device_number' => $device_index . '/' . $device_total,

            // 订单信息
            'order_id' => (string)($device['order_id'] ?? 0),
            'order_no' => $order['order_no'] ?? '',
            'customer_name' => $order['customer_name'] ?? '',
            'customer_phone' => $order['customer_phone'] ?? '',
            'member_nickname' => $member_nickname,
            'pay_type' => $order['pay_type'] ?? '',
            'pay_account' => $order['pay_account'] ?? '',
            'total_amount' => $order['total_amount'] ?? '0.00',
            'device_count' => $order['device_count'] ?? '0',
            'order_status' => $status_names[$order['status'] ?? 1] ?? '',
            'order_remark' => $order['remark'] ?? '',

            // 分类信息
            'category_id' => (string)($device['category_id'] ?? 1),
            'category_name' => $category_name,

            // 价格信息
            'initial_price' => $device['initial_price'] ? number_format((float)$device['initial_price'], 2) : '0.00',
            'final_price' => $device['final_price'] ? number_format((float)$device['final_price'], 2) : '0.00',
            'sell_price' => $device['sell_price'] ? number_format((float)$device['sell_price'], 2) : '0.00',
            'price' => $device['final_price'] ? number_format((float)$device['final_price'], 2) : ($device['initial_price'] ? number_format((float)$device['initial_price'], 2) : '0.00'),
            'before_price' => $device['before_price'] ?? '',
            'price_remark' => $device['price_remark'] ?? '',

            // 状态信息
            'status' => (string)($device['status'] ?? 1),
            'status_name' => $status_names[$device['status'] ?? 1] ?? '',
            'check_status' => (string)($device['check_status'] ?? 0),
            'check_status_name' => $check_status_names[$device['check_status'] ?? 0] ?? '',
            'final_status' => (string)($device['final_status'] ?? 0),
            'final_status_name' => $device['final_status'] ? '已确认' : '未确认',

            // 质检信息
            'check_result' => $device['check_result'] ?? '',
            'check_result_seller' => $device['check_result_seller'] ?? '',
            'check_result_buyer' => $device['check_result_buyer'] ?? '',
            'check_staff' => $device['checkUser']['username'] ?? '',
            'check_date' => $this->formatSafeTime($device['check_at'], 'Y-m-d'),
            'check_time' => $this->formatSafeTime($device['check_at']),

            // 定价信息
            'price_staff' => $device['checkUser']['username'] ?? '',
            'price_staff_name' => $device['checkUser']['real_name'] ?? $device['price_user']['username'] ?? '',
            'price_time' => $this->formatSafeTime($device['price_at']),

            // 快递信息
            'express_company' => $order['express_company'] ?? '',
            'express_no' => $order['express_no'] ?? '',
            'delivery_type' => ($order['delivery_type'] ?? '') === 'express' ? '快递' : '自送',
            'delivery_fee' => $order['delivery_fee'] ?? '0.00',
            'delivery_status' => $this->getDeliveryStatusName($order['delivery_status'] ?? 0),
            'delivery_operator' => $order['delivery_operator_name'] ?? '',

            // 会员信息
            'member_id' => (string)($device['member_id'] ?? 0),

            // 时间信息
            'create_time' => $this->formatSafeTime($device['create_at']),
            'update_time' => $this->formatSafeTime($device['update_at']),
            'sign_time' => $this->formatSafeTime($order['sign_at'] ?? 0),
            'complete_time' => $this->formatSafeTime($order['complete_at'] ?? 0),
            'pay_time' => $this->formatSafeTime($order['pay_time'] ?? 0),
            'current_time' => date('Y-m-d H:i:s'),
            'current_date' => date('Y-m-d'),
            'datetime' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),

            // 备注信息
            'remark' => $device['remark'] ?? '',

            // 二维码和条形码内容
            'qrcode_content' => "{$device['imei']}",
            'barcode_content' => $device['imei'] ?? '',
            'device_url' => request()->domain() . '/site/recycle_order/list?id=' . $device['id'],

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
            
            
            // 使用打印服务替换变量并打印
            return $this->printService->printWithVariables($print_content, $device_data, $printer);
            
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
     * 生成模板预览HTML
     * @param array $templateData JSON格式的模板数据
     * @param array $customVariables 自定义变量数据（可选）
     * @param float $scale 缩放比例
     * @return array ['html' => string, 'variables' => array]
     */
    public function generatePreview(array $templateData, array $customVariables = [], float $scale = 1.0): array
    {
        return $this->previewService->generatePreview($templateData, $customVariables, $scale);
    }

    /**
     * 验证模板数据
     * @param array $templateData JSON格式的模板数据
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validateTemplateData(array $templateData): array
    {
        return $this->validatorService->validateTemplate($templateData);
    }

    /**
     * 验证XML格式
     * @param string $xml XML内容
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validateXml(string $xml): array
    {
        return $this->validatorService->validateXml($xml);
        }
        
    /**
     * 从模板数据中提取变量
     * @param array $templateData JSON格式的模板数据
     * @return array 变量列表
     */
    public function extractVariables(array $templateData): array
    {
        return $this->previewService->extractVariables($templateData);
    }
    
    /**
     * 渲染模板为HTML（不包含预览数据）
     * @param array $templateData JSON格式的模板数据
     * @param float $scale 缩放比例
     * @return string HTML内容
     */
    public function renderTemplate(array $templateData, float $scale = 1.0): string
    {
        return $this->renderService->renderToHtml($templateData, [], $scale);
    }

    /**
     * 根据触发时机获取模板
     * @param string $triggerEvent 触发时机：draft/complete
     * @param string $templateType 模板类型，默认 device_label
     * @return array
     */
    public function getTemplateByTrigger(string $triggerEvent, string $templateType = 'device_label'): array
    {
        $template = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['trigger_event', '=', $triggerEvent],
            ['template_type', '=', $templateType],
            ['status', '=', 1]
        ])->findOrEmpty()->toArray();

        return $template;
    }

} 