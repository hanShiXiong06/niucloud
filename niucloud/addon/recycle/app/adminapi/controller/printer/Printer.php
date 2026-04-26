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

use addon\recycle\app\service\admin\printer\RecyclePrinterService;
use core\base\BaseAdminController;
use think\App;
use core\exception\CommonException;
use think\Response;

/**
 * 回收打印机管理控制器
 * Class Printer
 * @package addon\recycle\app\adminapi\controller\printer
 */
class Printer extends BaseAdminController
{
    /**
     * 打印机服务
     * @var RecyclePrinterService
     */
    protected $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecyclePrinterService();
    }
  
    /**
     * 获取打印机品牌列表
     * @return Response
     */
    public function getBrandList()
    {
        return success($this->service->getBrandList());
    }

    /**
     * 获取当前用户的打印机
     * @return Response
     */
    public function getUserPrinter()
    {
        return success($this->service->getUserPrinter());
    }

    /**
     * 绑定打印机
     * @return Response
     */
    public function bindPrinter()
    {
        $data = $this->request->params([
            ['brand', 'xpyun'],
            ['printer_name', ''],
            ['sn', ''],
            ['user_name', ''],
            ['user_key', '']
        ]);
        
        // 验证参数
        $this->validate($data, [
            'printer_name' => 'require',
            'sn' => 'require',
            'user_name' => 'require',
            'user_key' => 'require',
        ]);
        
        try {
        $res = $this->service->bindPrinter($data);
        return success('绑定成功', $res);
        } catch (\Exception $e) {
            return fail('绑定失败：' . $e->getMessage());
        }
    }

    /**
     * 解绑打印机
     * @return Response
     */
    public function unbindPrinter()
    {
        try {
        $res = $this->service->unbindPrinter();
        return success('解绑成功', $res);
        } catch (\Exception $e) {
            return fail('解绑失败：' . $e->getMessage());
        }
    }

    /**
     * 测试打印机
     * @return Response
     */
    public function testPrint()
    {
        $data = $this->request->params([
            ['sn', ''],
            ['user_name', ''],
            ['user_key', ''],
            ['content', '']
        ]);
        
        // 验证参数
        $this->validate($data, [
            'sn' => 'require',
            'user_name' => 'require',
            'user_key' => 'require',
        ]);
        
        try {
            $result = $this->service->testPrint($data);
            if ($result['code'] == 0) {
                return success($result['message'], $result);
            } else {
                // 服务层已记录日志，直接返回错误
                return fail($result['message']);
            }
        } catch (\Exception $e) {
            $errorMsg = '测试失败：' . $e->getMessage();
            \think\facade\Log::error('【测试打印控制器】' . $errorMsg, [
                'data' => $data,
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString()
            ]);
            return fail($errorMsg);
        }
    }
    
    /**
     * 打印标签
     * @return Response
     */
    public function printLabel()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['brand', ''],
            ['model', ''],
            ['color', ''],
            ['memory', ''],
            ['imei', ''],
            ['check_result', '质检通过'],
            ['copies', 1]
        ]);
        
        try {
            $result = $this->service->printLabel($data);
            if ($result['code'] == 0) {
                return success($result['message'], $result);
            } else {
                // 服务层已记录日志，直接返回错误
                return fail($result['message']);
            }
        } catch (\Exception $e) {
            $errorMsg = '打印失败：' . $e->getMessage();
            \think\facade\Log::error('【打印标签控制器】' . $errorMsg, [
                'data' => $data,
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString()
            ]);
            return fail($errorMsg);
        }
    }

    /**
     * 打印设备标签
     * @param int $id 设备ID
     * @return Response
     */
    public function printDeviceLabel(int $id)
    {
        $result = $this->service->printDeviceLabel($id);
        if ($result['success']) {
            return success($result);
        } else {
            return fail($result['message']);
        }
    }

    /**
     * 获取打印机列表
     * @return Response
     */
    public function lists()
    {
        // 获取是否查询状态的参数
        $withStatus = $this->request->param('with_status', false);
        $withStatus = filter_var($withStatus, FILTER_VALIDATE_BOOLEAN);
        
        return success($this->service->lists($withStatus));
    }
    
    /**
     * 批量查询打印机状态
     * @return Response
     */
    public function batchQueryStatus()
    {
        $data = $this->request->params([
            ['printer_ids', []]
        ]);
        
        // 验证参数
        if (empty($data['printer_ids']) || !is_array($data['printer_ids'])) {
            return fail('请提供打印机ID列表');
        }
        
        try {
            $result = $this->service->batchQueryStatus($data['printer_ids']);
            return success('查询成功', $result);
        } catch (\Exception $e) {
            return fail('查询失败：' . $e->getMessage());
        }
    }

    /**
     * 获取打印机详情
     * @param int $id 打印机ID
     * @return Response
     */
    public function info($id)
    {
        $id = (int)$id;
        return success($this->service->info($id));
    }

    /**
     * 添加打印机
     * @return Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['brand', 'xpyun'],
            ['printer_name', ''],
            ['sn', ''],
            ['user_name', ''],
            ['user_key', ''],
            ['type', 'label']
        ]);
        
        // 验证参数
        $this->validate($data, [
            'printer_name' => 'require',
            'sn' => 'require',
            'user_name' => 'require',
            'user_key' => 'require',
        ]);
        
        try {
            $result = $this->service->add($data);
            return success('添加成功', $result);
        } catch (\Exception $e) {
            return fail('添加失败：' . $e->getMessage());
        }
    }

    /**
     * 更新打印机信息
     * @param int $id 打印机ID
     * @return Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['brand', 'xpyun'],
            ['printer_name', ''],
            ['sn', ''],
            ['user_name', ''],
            ['user_key', ''],
            ['type', 'label']
        ]);
        
        // 验证参数
        $this->validate($data, [
            'printer_name' => 'require',
            'sn' => 'require',
            'user_name' => 'require',
            'user_key' => 'require',
        ]);
        
        try {
        $this->service->edit($id, $data);
        return success('更新成功');
        } catch (\Exception $e) {
            return fail('更新失败：' . $e->getMessage());
        }
    }

    /**
     * 删除打印机
     * @param int $id 打印机ID
     * @return Response
     */
    public function del(int $id)
    {
        try {
        $this->service->del($id);
        return success('删除成功');
        } catch (\Exception $e) {
            return fail('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 切换打印机状态
     * @param int $id 打印机ID
     * @return Response
     */
    public function toggleStatus(int $id)
    {
        $data = $this->request->params([
            ['status', 1]
        ]);
        
        try {
        $this->service->toggleStatus($id, (int)$data['status']);
        return success($data['status'] ? '打印机已激活' : '打印机已停用');
        } catch (\Exception $e) {
            return fail('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 查询打印机状态
     * @param int $id 打印机ID
     * @return Response
     */
    public function queryPrinterStatus(int $id)
    {
        try {
            $result = $this->service->queryPrinterStatus($id);
            return success($result);
        } catch (\Exception $e) {
            return fail('查询失败：' . $e->getMessage());
        }
    }
} 