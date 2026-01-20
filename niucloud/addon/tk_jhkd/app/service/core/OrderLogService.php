<?php

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use addon\tk_jhkd\app\model\order\OrderLog;
use core\base\BaseApiService;
use core\exception\CommonException;
use Exception;
use think\facade\Db;


/**
 * 订单日志服务层
 */
class OrderLogService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderLog();
    }

    public function writeOrderLog($site_id,$order_id,$order_status,$action='回调操作',$main_type='system',$main_id='',$nick_name='')
    {
        try {
            $data = [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'order_status'=>$order_status,
                'action' => $action,
                'main_type' => $main_type,
                'main_id' => $main_id,
                'nick_name'=>$nick_name,
            ];
            $this->model->save($data);
            return true;
        } catch (Exception $e) {
            throw new CommonException($e->getMessage());
        }

    }

}