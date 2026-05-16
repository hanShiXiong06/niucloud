<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin;

use addon\recycle\app\service\core\RecycleReturnOrderService as CoreRecycleReturnOrderService;

use core\base\BaseAdminService;

/**
 * 管理端退回订单服务
 * Class RecycleReturnOrderService
 * @package addon\recycle\app\service\admin
 */
class RecycleReturnOrderService extends BaseAdminService
{
    /**
     * @var CoreRecycleReturnOrderService
     */
    private $coreService;
    
    public function __construct()
    {
        parent::__construct();
        $this->coreService = new CoreRecycleReturnOrderService();
    }
    
    /**
     * 创建退回订单
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        // 记录操作人信息
        $data['operator_id'] = $this->uid;
        $data['operator_name'] = $this->username ?? '';
        
        // 添加站点ID
        $data['site_id'] = $this->site_id;
        
        return $this->coreService->create($data);
    }
    
    /**
     * 批量创建退回订单
     * @param array $data
     * @return array
     */
    public function batchCreate(array $data): array
    {
        // 处理每个订单的操作人信息
        foreach ($data['orders'] as &$order) {
            $order['operator_id'] = $this->uid;
            $order['operator_name'] = $this->username ?? '';
            $order['site_id'] = $this->site_id;
        }
        
        return $this->coreService->batchCreate($data);
    }
    
    /**
     * 更新退回订单状态
     * @param int $id
     * @param int $status
     * @param array $data
     * @return array
     */
    public function updateStatus(int $id, int $status, array $data = []): array
    {

        // 记录操作人信息
        $data['operator_id'] = $this->uid;
        $data['operator_name'] = $this->username ?? '';
        $data['site_id'] = $this->site_id;
       
        
        return $this->coreService->updateStatus($id, $status, $data);
    }
    
    /**
     * 确认退回订单
     * @param int $id
     * @return array
     */
    public function confirm(int $id, array $data): array
    {

        return $this->coreService->confirm($id, $this->site_id, $data);
    }
    
    /**
     * 取消退回订单
     * @param int $id
     * @param string $comment
     * @return array
     */
    public function cancel(int $id, string $comment = ''): array
    {
        return $this->coreService->cancel($id, $this->site_id, $comment);
    }
    
    /**
     * 获取退回订单详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id): array
    {

        
        return $this->coreService->detail($id, $this->site_id);
    }
    
    /**
     * 获取退回订单列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where): array
    {
      
        // 添加站点ID
        $where['site_id'] = $this->site_id;
        
        // 构建查询模型
        $search_model = new \addon\recycle\app\model\RecycleReturnOrder();
        
        // 使用withSearch方法进行条件查询
        $search_model = $search_model
            ->withSearch(['order_no', 'express_no', 'status'], $where)
            ->where([['delete_at', '=', 0],['site_id', '=', $this->site_id]]) 
          
            ->with([
                'returnDevices' => function($query) {
                    $query->field('id,return_order_id,device_id, status,remark')
                        ->append(['status_name'])
                        ->with(['device' => function($query) {
                            $query->field('id,site_id,order_id,imei,model,status,final_price');
                        }]);
                },
                'member' => function($query) {
                    $query->field('member_id,username,nickname,mobile,headimg');
                }
            ])
            ->field('id,site_id,order_id,order_no,status, remark,express_company,express_no,return_address,comment,create_at,update_at,over_at,operator_uid,operator_name,member_id,member_name,member_mobile')
            ->order('create_at desc')
            ->append(['status_name']);
        
        // 使用pageQuery方法进行分页查询
        return $this->pageQuery($search_model);
    }
    
    /**
     * 删除退回订单
     * @param int $id
     * @return array
     */
    public function delete(int $id): array
    {
        return $this->coreService->delete($id, $this->site_id);
    }
    
    /**
     * 获取退回订单状态统计
     * @return array
     */
    public function getStatusCount(): array
    {
        return $this->coreService->getStatusCount($this->site_id);
    }
} 