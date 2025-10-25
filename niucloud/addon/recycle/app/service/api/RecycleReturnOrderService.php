<?php
declare(strict_types=1);

namespace addon\recycle\app\service\api;

use addon\recycle\app\model\RecycleReturnOrder;
use addon\recycle\app\service\core\RecycleReturnOrderService as CoreRecycleReturnOrderService;
use core\base\BaseApiService;

/**
 * 用户端退回订单服务
 * Class RecycleReturnOrderService
 * @package addon\recycle\app\service\api
 */
class RecycleReturnOrderService extends BaseApiService
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
     * 获取退回订单详情
     * @param int $id
     * @param int $siteId
     * @param int $memberId
     * @return array
     */
    public function detail(int $id, int $siteId, int $memberId): array
    {
        try {
            $returnOrder = RecycleReturnOrder::with(['returnDevices.device', 'order'])
                ->where([
                    ['id', '=', $id],
                    ['site_id', '=', $siteId],
                    ['member_id', '=', $memberId]
                ])->find();
            
            if (empty($returnOrder)) {
                return error('退回订单不存在');
            }
            
            return success($returnOrder);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
    
    /**
     * 获取退回订单列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getList(array $where, int $page = 1, int $limit = 10): array
    {
        try {
            $field = 'id, site_id, order_id, order_no, express_id, express_company, express_no, return_address, status, create_at, update_at, over_at, comment';
            
            $count = RecycleReturnOrder::where($where)->count();
            $list = RecycleReturnOrder::where($where)
                ->field($field)
                ->with(['returnDevices'])
                ->order('create_at', 'desc')
                ->page($page, $limit)
                ->select();
            
            return success(['count' => $count, 'list' => $list]);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
    
    /**
     * 确认收到退回设备
     * @param int $id
     * @param int $siteId
     * @param int $memberId
     * @param string $comment
     * @return array
     */
    public function confirmReceive(int $id, int $siteId, int $memberId, string $comment = ''): array
    {
        try {
            $returnOrder = RecycleReturnOrder::where([
                ['id', '=', $id],
                ['site_id', '=', $siteId],
                ['member_id', '=', $memberId]
            ])->find();
            
            if (empty($returnOrder)) {
                return error('退回订单不存在');
            }
            
            // 只有退货中的订单才能确认收货
            if ($returnOrder->status != 1) {
                return error('只有退货中的订单才能确认收货');
            }
            
            // 更新为已完成状态
            $data = [
                'comment' => $comment
            ];
            
            return $this->coreService->updateStatus($id, 2, $data);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
    
    /**
     * 获取退回订单状态统计
     * @param int $siteId
     * @param int $memberId
     * @return array
     */
    public function getStatusCount(int $siteId, int $memberId): array
    {
        try {
            $all = RecycleReturnOrder::where([
                ['site_id', '=', $siteId],
                ['member_id', '=', $memberId]
            ])->count();
            
            $pending = RecycleReturnOrder::where([
                ['site_id', '=', $siteId],
                ['member_id', '=', $memberId],
                ['status', '=', 0]
            ])->count();
            
            $returning = RecycleReturnOrder::where([
                ['site_id', '=', $siteId],
                ['member_id', '=', $memberId],
                ['status', '=', 1]
            ])->count();
            
            $completed = RecycleReturnOrder::where([
                ['site_id', '=', $siteId],
                ['member_id', '=', $memberId],
                ['status', '=', 2]
            ])->count();
            
            $cancelled = RecycleReturnOrder::where([
                ['site_id', '=', $siteId],
                ['member_id', '=', $memberId],
                ['status', '=', 3]
            ])->count();
            
            $data = [
                'all' => $all,
                'pending' => $pending,
                'returning' => $returning,
                'completed' => $completed,
                'cancelled' => $cancelled
            ];
            
            return success($data);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }
} 