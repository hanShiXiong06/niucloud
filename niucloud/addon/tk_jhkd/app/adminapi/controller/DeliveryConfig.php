<?php

namespace addon\tk_jhkd\app\adminapi\controller;

use addon\tk_jhkd\app\dict\delivery\DeliveryDict;
use addon\tk_jhkd\app\service\admin\DeliveryService;
use core\base\BaseAdminController;
use core\exception\AdminException;
use think\Response;

class DeliveryConfig extends BaseAdminController
{
    /**
     * 配置列表
     */
    public function deliveryList()
    {
        $res = (new DeliveryService())->getList();
        return success($res);
    }

    /**
     * 配置详情
     * @param $sms_type
     * @return Response
     */
    public function deliveryConfig($delivery_type)
    {
        $res = (new DeliveryService())->getConfig($delivery_type);
        return success($res);
    }

    /**
     * 配置修改
     * @return Response
     */
    public function editDelivery($delivery_type)
    {
        //参数获取
        $delivery_type_list = DeliveryDict::getType();
        if (!array_key_exists($delivery_type, $delivery_type_list)) throw new AdminException('DELIVERY_TYPE_NOT_EXIST');
        //数据验证
        $data = [
            ['is_use', 0]
        ];
        foreach ($delivery_type_list[$delivery_type]['params'] as $k_param => $v_param) {
            $data[] = [$k_param, ''];
        }

        $request_data = $this->request->params($data);
        (new DeliveryService())->setConfig($delivery_type, $request_data);
        return success();
    }

}
