<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\api\controller\recycle_order;

use core\base\BaseApiController;
use addon\recycle\app\service\api\recycle_order\RecycleOrderService;
use addon\recycle\app\dict\order\RecycleOrderDict;
use think\App;

/**
 * 二手机回收报价订单控制器
 * Class PhoneShopRecycleOrder
 * @package addon\recycle\app\api\controller\recycle_order
 */
class RecycleOrder extends BaseApiController
{
    /**
     * 二手机回收报价订单服务
     * @var RecycleOrderService
     */
    protected $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleOrderService();
    }

    /**
     * 初始化
     */
    public function initialize()
    {
        parent::initialize();
        $this->site_id = $this->request->siteid();
        $this->member_id = $this->request->memberid();
    }

    /**
     * 获取二手机回收订单列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["order_no", ""],              // 订单号
            ["express_no", ""],            // 快递单号
            ["search",''],
            ["delivery_type", ""],         // 配送方式
            ["status", ""],                // 订单状态
            ["create_at", ["", ""]],       // 创建时间范围
            ["remark", ""]                 // 备注
        ]);
      
        return success($this->service->getPage($data));
    }

    /**
     * 获取二手机回收报价订单详情
     * @param int $id
     * @return \think\Response
     */
    public function show(int $id)
    {
        $info = $this->service->getInfo($id);
        if (empty($info)) {
            return error('', '订单不存在');
        }
        return success($info);
    }

    /**
     * 添加二手机回收报价订单
     * @return \think\Response
     */
    public function store()
    {
        $data = $this->request->params([
            ["delivery_type", ""],    // 配送方式：1快递，2自送
            ["count", ""],            // 数量
           
            ["express_no", ""],       // 快递单号
            ["remark", ""],           // 备注
            ["devices", []]           // 设备列表
        ]);

        $this->validate($data, [
            'delivery_type' => 'require|in:1,2',
            
            // 'devices' => 'require|array|min:1',
            // 'devices.*.imei' => 'require|length:15',
            // 'devices.*.model' => 'require',
            'devices.*.initial_price' => 'float|min:0',
        ], [
            'delivery_type.require' => '请选择配送方式',
            'delivery_type.in' => '配送方式错误',
            'devices.require' => '请添加设备',
            'devices.array' => '设备数据格式错误',
            'devices.min' => '至少添加一个设备',
            'devices.*.imei.require' => '请输入IMEI号',
            'devices.*.imei.length' => 'IMEI号必须是15位',
            'devices.*.model.require' => '请输入设备型号',
            'devices.*.initial_price.require' => '请输入预估价格',
            'devices.*.initial_price.float' => '预估价格必须是数字',
            'devices.*.initial_price.min' => '预估价格必须大于0',
        ]);

        if ($data['delivery_type'] == 1 && empty($data['express_no'])) {
            return error('', '请输入快递单号');
        }

        return success($this->service->add($data));
    }

    /**
     * 更新二手机回收报价订单
     * @param int $id
     * @return \think\Response
     */
    public function update(int $id)
    {
        $data = $this->request->params([
            ["delivery_type", ""],
            ["express_no", ""],
            ["remark", ""]
        ]);

        return success($this->service->edit($id, $data));
    }

    /**
     * 删除二手机回收报价订单
     * @param int $id
     * @return \think\Response
     */
    public function destroy(int $id)
    {
        return success($this->service->del($id));
    }

    /**
     * 获取所有会员
     * @return \think\Response
     */
    public function getMemberAll()
    {
        $list = $this->service->getMemberAll();
        return success($list);
    }

    /**
     * 获取订单状态列表
     * @return \think\Response
     */
    public function getStatus()
    {
        $status = $this->service->getStatus();
        return success($status);
    }

    /**
     * 获取订单状态列表
     */
    // public function getStatusList()
    // {
    //     return success([
    //         'order_status' => RecycleOrderDict::ORDER_STATUS_TEXT,
    //         'device_status' => RecycleOrderDict::DEVICE_STATUS_TEXT
    //     ]);
    // }

    // getDeviceStatus 获取设备状态列表
    public function getDeviceStatus()
    {
        return success([
            'device_status' => RecycleOrderDict::DEVICE_STATUS_TEXT,
            'order_status' => RecycleOrderDict::ORDER_STATUS_TEXT,
        ]);
    }
    // updateStatus 更新订单状态
    public function updateStatus(int $id)
    {
        // action:"cancel"
        // status : 1
        $data = $this->request->params([
            ["action", ""],
            ["status", ""]
        ]);

        
        return success($this->service->edit($id, $data));
    }

   
    public function getStatusCount()
    {
        $service = new RecycleOrderService();
        $result = $service->getStatusCount($this->member_id);
            
        // 直接返回服务层的结果
        return success($result);
    }

   
    // deviceConfirm    
    public function deviceConfirm(int $id)
    {
        $res = $this->service->deviceConfirm($id);
        return success($res);
    }
    // deviceCancel
    public function deviceCancel(int $id)
    {
        $res = $this->service->deviceCancel($id);
        return success($res);
    }
}
