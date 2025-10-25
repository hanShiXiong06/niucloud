<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\recycle_return_order;

use addon\recycle\app\dict\order\RecycleReturnOrderDict;
use addon\recycle\app\service\admin\RecycleReturnOrderService;
use addon\recycle\app\validate\RecycleReturnOrderValidate;
use core\base\BaseAdminController;
use think\App;

/**
 * 退回订单控制器
 * Class RecycleReturnOrder
 * @package addon\recycle\app\adminapi\controller\recycle_return_order
 */
class RecycleReturnOrder extends BaseAdminController
{
    /**
     * @var RecycleReturnOrderService
     */
    protected $service;

    /**
     * @var RecycleReturnOrderValidate
     */
    protected $validate;

    /**
     * 构造函数
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleReturnOrderService();
        $this->validate = new RecycleReturnOrderValidate();
    }

    /**
     * 获取退回订单列表
     * @return mixed
     */
    public function lists()
    {
        $params = $this->request->params([
           
            ['order_no', ''],
            ['express_no', ''],
            ['status', ''],
            ['create_at', [date('Y-m-d'), date('Y-m-d')] ],
        ]);
        
        // 
      if($params['status']==0){
          unset($params['status']);
      }
        
        // 验证参数
        $this->validate->scene('list')->check($params);
        
        // 调用服务层的getPage方法获取数据
        $result = $this->service->getPage($params);
        
        return success($result);
    }

    /**
     * 获取退回订单详情
     * @param int $id
     * @return mixed
     */
    public function detail(int $id)
    {
        // 验证参数
        $this->validate->scene('id')->check(['id' => $id]);
        
        $result = $this->service->getInfo($id);
        return success($result);
    }

    /**
     * 创建退回订单
     * @return mixed
     */
    public function create()
    {
        $params = $this->request->params([
            ['order_id', 0],
            ['device_ids', []],
            ['express_company', ''],
            ['express_no', ''],
            ['return_address', ''],
            ['comment', ''],
            ['remark', ''],
           
        ]);
        
        // 验证参数
        $this->validate->scene('create')->check($params);
        
        $result = $this->service->create($params);
        return success($result);
    }

    /**
     * 批量创建退回订单
     * @return mixed
     */
    public function batchCreate()
    {
        $params = $this->request->params([
            ['orders', []]
        ]);
        
        // 验证参数
        $this->validate->scene('batch_create')->check($params);
        
        $result = $this->service->batchCreate($params);
        return success($result);
    }

    /**
     * 更新退回订单状态
     * @param int $id
     * @return mixed
     */
    public function updateStatus(int $id)
    {
     
        $params = $this->request->params([
            ['status', 0],
            ['comment', ''],
        ]);

       
        
        // 验证参数
        $this->validate->scene('update_status')->check(array_merge(['id' => $id], $params));
        
        $result = $this->service->updateStatus($id, $params['status'], $params);
        return success($result);
    }

    /**
     * 确认收到退回设备
     * @param int $id
     * @return mixed
     */
    public function confirm(int $id)
    {
        $params = $this->request->params([
            ['express_no', ''],
            ['express_company', ''],
            ['member_mobile', ''],
            ['member_name', ''],
            ['return_address', ''],
            ['remark',''],
            
           
        ]);

        // 验证参数
        $this->validate->scene('id')->check(['id' => $id]);
        
        $result = $this->service->confirm($id, $params);
        return success($result);
    }

    /**
     * 取消退回订单
     * @param int $id
     * @return mixed
     */
    public function cancel(int $id)
    {
        $params = $this->request->params([
            ['comment', '']
        ]);
        
        // 验证参数
        $this->validate->scene('cancel')->check(array_merge(['id' => $id], $params));
        
        $result = $this->service->cancel($id, $params['comment']);
        return success($result);
    }

    /**
     * 删除退回订单
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        // 验证参数
        $this->validate->scene('delete')->check(['id' => $id]);
        
        $result = $this->service->delete($id);
        return success($result);
    }

    /**
     * 获取退回订单状态统计
     * @return mixed
     */
    public function getStatus()
    {
        $result = $this->service->getStatusCount();
        return success($result);
    }

    /**
     * 获取退回订单状态列表
     * @return mixed
     */
    public function getStatusList()
    {
        $result = RecycleReturnOrderDict::getOrderStatusList();
        return success($result);
    }
} 