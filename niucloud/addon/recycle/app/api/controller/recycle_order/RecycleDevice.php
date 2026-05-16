<?php
declare(strict_types=1);

namespace addon\recycle\app\api\controller\recycle_order;

use addon\recycle\app\service\api\recycle_order\RecycleDeviceService;
use core\base\BaseApiController;

/**
 * 回收设备控制器
 */
class RecycleDevice extends BaseApiController
{
    /**
     * 获取设备列表
     */
    public function lists()
    {
        $data = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['order_id', ''],
            ['imei', ''],
            ['model', ''],
            ['status', ''],
        ]);
        $service = new RecycleDeviceService();
        return success($service->getPage($data));
    }

    /**
     * 获取设备详情
     */
    public function info(int $id)
    {
       $data['id']= $id;
        $service = new RecycleDeviceService();
        return success($service->getInfo($data['id']));
    }

    /**
     * 获取订单下的设备列表
     */
    public function orderDevices(int $order_id)
    {
        $data['order_id'] = $order_id;
        $service = new RecycleDeviceService();
        return success($service->getOrderDevices($data['order_id']));
    }

    /**
     * 确认价格
     */
    public function confirmPrice(int $id)
    {
       
        $service = new RecycleDeviceService();
        return success( $service->confirmPrice($id , true));
    }

 
    /**
     * 确认设备处理方式（出售或退回）
     * @param int $id
     * @return mixed
     */
    public function confirm(int $id)
    {
        $data = $this->request->params([
            ['is_sell', true],       // true: 出售，false: 不出售（退回）
            ['remark', ''],          // 备注
        ]);
        $service = new RecycleDeviceService();
        $service->confirm($id, $data['is_sell'], $data['remark']);
        return success();
    }

    // getCount
    public function getCount(){

        return success( (new RecycleDeviceService())->getCount());
    }
    
    // deviceAllConfirm 批量确认设备
    public function deviceAllConfirm()
    {
        
        $data = $this->request->params([
            ['device_ids', []],
        ]);

        $service = new RecycleDeviceService();
        return success($service->deviceAllConfirm($data['device_ids']));
    }
       
}
