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
        
        $res = $this->service->bindPrinter($data);
        return success('绑定成功', $res);
    }

    /**
     * 解绑打印机
     * @return Response
     */
    public function unbindPrinter()
    {
        $res = $this->service->unbindPrinter();
        return success('解绑成功', $res);
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
        
        return success($this->service->testPrint($data));
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
        
        return success($this->service->printLabel($data));
    }

    /**
     * 打印设备标签
     * @param int $id 设备ID
     * @return Response
     */
    public function printDeviceLabel(int $id)
    {
        return success($this->service->printDeviceLabel($id));
    }

    /**
     * 获取打印机列表
     * @return Response
     */
    public function lists()
    {
        return success($this->service->lists());
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
        return success($this->service->add($data));
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
        
        $this->service->edit($id, $data);
        return success('更新成功');
    }

    /**
     * 删除打印机
     * @param int $id 打印机ID
     * @return Response
     */
    public function del(int $id)
    {
        $this->service->del($id);
        return success('删除成功');
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
        
        $this->service->toggleStatus($id, (int)$data['status']);
        return success($data['status'] ? '打印机已激活' : '打印机已停用');
    }
} 