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
            ['template_type', 'device_label'],
            ['width', 58],
            ['height', 40],
            ['content', ''],
            ['template_data', ''],
            ['html_content', ''],
            ['variables', []],
            ['status', 1],
            ['is_default', 0],
            ['printer_id', 0],
            ['trigger_event', '']
        ]);

        // 验证参数
        $this->validate($data, [
            'template_name' => 'require',
            'width' => 'require|number',
            'height' => 'require|number'
        ]);

        if (empty($data['template_type'])) {
            $data['template_type'] = 'device_label';
        }

        if (!empty($data['template_data']) && empty($data['content'])) {
            $data['content'] = $data['template_data'];
        }

        $template_id = $this->service->add($data);

        return success('添加成功', ['id' => $template_id]);
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
            ['template_type', 'device_label'],
            ['width', 58],
            ['height', 40],
            ['content', ''],
            ['template_data', ''],
            ['html_content', ''],
            ['variables', []],
            ['status', 1],
            ['is_default', 0],
            ['printer_id', 0],
            ['trigger_event', '']
        ]);

        // 验证参数
        $this->validate($data, [
            'template_name' => 'require',
            'width' => 'require|number',
            'height' => 'require|number'
        ]);

        if (empty($data['template_type'])) {
            $data['template_type'] = 'device_label';
        }

        if (!empty($data['template_data']) && empty($data['content'])) {
            $data['content'] = $data['template_data'];
        }

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
        $data = $this->request->params([
            ['custom_variables', []],
            ['scale', 1.0]
        ]);
        
        $template = $this->service->getInfo($id);
        
        if (empty($template)) {
            return fail('模板不存在');
        }

        // 获取模板数据（优先使用template_data，否则使用content）
        $templateData = $template['template_data'] ?? $template['content'] ?? [];
        
        // 如果templateData是字符串，尝试解析为JSON
        if (is_string($templateData)) {
            $templateData = json_decode($templateData, true);
            if (!$templateData) {
                // 如果解析失败，可能是XML格式，需要转换
                $templateData = $this->service->getInfo($id)['template_data'] ?? [];
            }
        }
        
        // 确保templateData是数组格式
        if (empty($templateData) || !is_array($templateData)) {
            // 兼容旧格式
            return success([
                'html_content' => $template['html_content'] ?? '',
                'template_info' => $template
            ]);
        }

        // 使用新的预览服务
        $previewResult = $this->service->generatePreview(
            $templateData,
            $data['custom_variables'] ?? [],
            (float)($data['scale'] ?? 1.0)
        );
        
        return success([
            'html' => $previewResult['html'],
            'variables' => $previewResult['variables'],
            'template_variables' => $previewResult['template_variables'],
            'template_info' => $template
        ]);
    }

    /**
     * 验证模板数据
     * @return Response
     */
    public function validateTemplate()
    {
        $data = $this->request->params([
            ['template_data', []]
        ]);
        
        if (empty($data['template_data'])) {
            return fail('模板数据不能为空');
        }
        
        $result = $this->service->validateTemplateData($data['template_data']);
        
        if ($result['valid']) {
            return success('模板验证通过', $result);
        } else {
            return fail('模板验证失败', $result);
        }
    }

    /**
     * 验证XML格式
     * @return Response
     */
    public function validateXml()
    {
        $data = $this->request->params([
            ['xml', '']
        ]);
        
        if (empty($data['xml'])) {
            return fail('XML内容不能为空');
        }
        
        $result = $this->service->validateXml($data['xml']);
        
        if ($result['valid']) {
            return success('XML验证通过', $result);
        } else {
            return fail('XML验证失败', $result);
        }
    }

    /**
     * 提取模板变量
     * @return Response
     */
    public function extractVariables()
    {
        $data = $this->request->params([
            ['template_data', []]
        ]);
        
        if (empty($data['template_data'])) {
            return fail('模板数据不能为空');
        }
        
        $variables = $this->service->extractVariables($data['template_data']);
        
        return success($variables);
    }

    /**
     * 渲染模板（不包含预览数据）
     * @return Response
     */
    public function render()
    {
        $data = $this->request->params([
            ['template_data', []],
            ['scale', 1.0]
        ]);
        
        if (empty($data['template_data'])) {
            return fail('模板数据不能为空');
        }
        
        $html = $this->service->renderTemplate(
            $data['template_data'],
            (float)($data['scale'] ?? 1.0)
        );
        
        return success([
            'html' => $html
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
            return fail('获取设备数据失败: ' . $e->getMessage());
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
            return fail($result['message']);
        }
                        }
                    }
