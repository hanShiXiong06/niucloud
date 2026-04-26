<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\wj_books\app\api\controller\wj_books_info;

use core\base\BaseApiController;
use addon\wj_books\app\service\api\wj_books_order\WjBooksOrderService;
use think\Response;

/**
 * 图书回收订单API控制器
 * Class WjBooksOrder
 * @package addon\wj_books\app\api\controller\wj_books_info
 */
class WjBooksOrder extends BaseApiController
{
    /**
     * 创建回收订单
     * @return Response
     */
    public function createOrder()
    {
        $data = $this->request->params([
            ["address_id", 0],
            ["pickup_time", ""],
            ["remark", ""],
            ["book_list", []]
        ]);
        
        // 验证参数
        if (empty($data['address_id'])) {
            return fail('请选择回收地址');
        }
        
        if (empty($data['pickup_time'])) {
            return fail('请选择预约上门时间');
        }
        
        if (empty($data['book_list'])) {
            return fail('请选择要回收的图书');
        }
        
        // 调用服务创建订单
        $result = (new WjBooksOrderService())->createOrder($data);
        
        if ($result['code'] == 0) {
            return success($result['data'], $result['msg']);
        } else {
            return fail($result['msg']);
        }
    }
    
    /**
     * 获取订单详情
     * @return Response
     */
    public function getOrderDetail()
    {
        $data = $this->request->params([
            ["id", 0],
            ["action", ""]
        ]);
        
        if (empty($data['id'])) {
            return fail('订单ID不能为空');
        }
        
        // 如果有action参数，处理相应的操作
        if (!empty($data['action'])) {
            switch ($data['action']) {
                case 'cancel':
                    // 取消订单
                    $result = (new WjBooksOrderService())->cancelOrder($data['id'], $data['reason'] ?? '用户取消');
                    if ($result['code'] == 0) {
                        return success([], $result['msg']);
                    } else {
                        return fail($result['msg']);
                    }
                    break;
                    
                case 'delete':
                    // 删除订单
                    $result = (new WjBooksOrderService())->deleteOrder($data['id']);
                    if ($result['code'] == 0) {
                        return success([], $result['msg']);
                    } else {
                        return fail($result['msg']);
                    }
                    break;
                    
                default:
                    break;
            }
        }
        
        // 获取订单详情
        $result = (new WjBooksOrderService())->getOrderDetail($data['id']);
        
        if ($result['code'] == 0) {
            return success($result['data']);
        } else {
            return fail($result['msg']);
        }
    }
    
    /**
     * 获取订单列表
     * @return Response
     */
    public function getOrderList()
    {
        $data = $this->request->params([
            ["page", 1],
            ["page_size", 10],
            ["status", 0] // 0表示全部
        ]);
        
        $result = (new WjBooksOrderService())->getOrderList($data['page'], $data['page_size'], $data['status']);
        
        return success($result);
    }
    
    /**
     * 取消订单
     * @return Response
     */
    public function cancelOrder()
    {
        $data = $this->request->params([
            ["id", 0],
            ["reason", "用户取消"]
        ]);
        
        if (empty($data['id'])) {
            return fail('订单ID不能为空');
        }
        
        $result = (new WjBooksOrderService())->cancelOrder($data['id'], $data['reason']);
        
        if ($result['code'] == 0) {
            return success([], $result['msg']);
        } else {
            return fail($result['msg']);
        }
    }
    
    /**
     * 删除订单
     * @return Response
     */
    public function deleteOrder()
    {
        $data = $this->request->params([
            ["id", 0]
        ]);
        
        if (empty($data['id'])) {
            return fail('订单ID不能为空');
        }
        
        $result = (new WjBooksOrderService())->deleteOrder($data['id']);
        
        if ($result['code'] == 0) {
            return success([], $result['msg']);
        } else {
            return fail($result['msg']);
        }
    }
    
    /**
     * 获取物流信息
     * @return Response
     */
    public function getExpressInfo()
    {
        $data = $this->request->params([
            ["waybill", ""],
            ["channel", ""]
        ]);
        
        if (empty($data['waybill'])) {
            return fail('物流单号不能为空');
        }
        
        $result = (new WjBooksOrderService())->getExpressInfo($data['waybill'], $data['channel']);
        
        if ($result['code'] == 0) {
            return success($result['data']);
        } else {
            return fail($result['msg']);
        }
    }
    
    /**
     * 申请取回不合格书籍
     * @return Response
     */
    public function applyRetrieve()
    {
        $data = $this->request->params([
            ["order_id", 0],
            ["address_id", 0],
            ["book_ids", []],
            ["remark", ""]
        ]);
        
        if (empty($data['order_id'])) {
            return fail('订单ID不能为空');
        }
        
        if (empty($data['address_id'])) {
            return fail('收货地址不能为空');
        }
        
        if (empty($data['book_ids'])) {
            return fail('请选择要取回的书籍');
        }
        
        $result = (new WjBooksOrderService())->applyRetrieve($data);
        
        if ($result['code'] == 0) {
            return success($result['data'], $result['msg']);
        } else {
            return fail($result['msg']);
        }
    }
} 