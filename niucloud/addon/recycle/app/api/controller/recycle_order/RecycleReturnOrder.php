<?php
declare(strict_types=1);

namespace addon\recycle\app\api\controller\recycle_order;

use core\base\BaseApiController;
use addon\recycle\app\service\core\recycle_order\RecycleReturnOrderService as CoreRecycleReturnOrderService;
use think\App;

/**
 * 用户端退货订单控制器
 * Class RecycleReturnOrder
 * @package addon\recycle\app\api\controller\recycle_order
 */
class RecycleReturnOrder extends BaseApiController
{
    /**
     * @var CoreRecycleReturnOrderService
     */
    protected $coreService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->coreService = new CoreRecycleReturnOrderService();
    }

    /**
     * 根据原订单ID查询退货订单列表（含退货设备信息）
     * @param int $order_id
     * @return mixed
     */
    public function getByOrderId(int $order_id)
    {
        $site_id = $this->request->siteid();
        $member_id = $this->request->memberid();

        // 查询该原订单下的退货订单
        $model = new \addon\recycle\app\model\order\RecycleReturnOrder();
        $list = $model->where([
                ['order_id', '=', $order_id],
                ['site_id', '=', $site_id],
                ['member_id', '=', $member_id],
                ['delete_at', '=', 0],
            ])
            ->with([
                'returnDevices' => function($query) {
                    $query->field('id,return_order_id,device_id,status,remark')
                        ->append(['status_name'])
                        ->with(['device' => function($query) {
                            $query->field('id,site_id,order_id,imei,model,status,final_price');
                        }]);
                },
            ])
            ->field('id,site_id,order_id,order_no,status,remark,express_company,express_no,return_address,comment,create_at,update_at,over_at,member_id,member_name,member_mobile')
            ->order('create_at desc')
            ->append(['status_name'])
            ->select()
            ->toArray();

        return success($list);
    }

    /**
     * 获取退货订单详情
     * @param int $id
     * @return mixed
     */
    public function detail(int $id)
    {
        $site_id = $this->request->siteid();
        $member_id = $this->request->memberid();

        $model = new \addon\recycle\app\model\order\RecycleReturnOrder();
        $info = $model->where([
                ['id', '=', $id],
                ['site_id', '=', $site_id],
                ['member_id', '=', $member_id],
                ['delete_at', '=', 0],
            ])
            ->with([
                'returnDevices' => function($query) {
                    $query->field('id,return_order_id,device_id,status,remark')
                        ->append(['status_name'])
                        ->with(['device' => function($query) {
                            $query->field('id,site_id,order_id,imei,model,status,final_price');
                        }]);
                },
            ])
            ->field('id,site_id,order_id,order_no,status,remark,express_company,express_no,return_address,comment,create_at,update_at,over_at,member_id,member_name,member_mobile')
            ->append(['status_name'])
            ->find();

        if (empty($info)) {
            return success([]);
        }

        return success($info->toArray());
    }
}
