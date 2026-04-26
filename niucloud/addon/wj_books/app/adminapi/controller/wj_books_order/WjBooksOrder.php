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

namespace addon\wj_books\app\adminapi\controller\wj_books_order;

use core\base\BaseAdminController;
use addon\wj_books\app\service\admin\wj_books_order\WjBooksOrderService;

/**
 * 二手书籍回收订单控制器
 * Class WjBooksOrder
 * @package addon\wj_books\app\adminapi\controller\wj_books_order
 */
class WjBooksOrder extends BaseAdminController
{
    /**
     * 获取订单列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["order_no", ""],
            ["member_id", ""],
            ["status", ""],
            ["express_waybill", ""],
            ["create_time", []],
        ]);
        return success((new WjBooksOrderService())->getPage($data));
    }

    /**
     * 订单详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new WjBooksOrderService())->getInfo($id));
    }

    /**
     * 获取订单书籍列表
     * @return \think\Response
     */
    public function bookList()
    {
        $data = $this->request->params([
            ["order_id", 0],
        ]);
        return success((new WjBooksOrderService())->getOrderBookList($data['order_id']));
    }

    /**
     * 更新订单状态
     * @return \think\Response
     */
    public function status()
    {
        $data = $this->request->params([
            ["id", 0],
            ["status", 1],
        ]);
        $this->validate($data, 'addon\wj_books\app\validate\wj_books_order\WjBooksOrder.update_status');
        (new WjBooksOrderService())->updateStatus($data['id'], $data['status']);
        return success('UPDATE_SUCCESS');
    }

    /**
     * 更新订单审核进度
     * @return \think\Response
     */
    public function auditProgress()
    {
        $data = $this->request->params([
            ["id", 0],
            ["audit_progress", 0],
        ]);
        $this->validate($data, 'addon\wj_books\app\validate\wj_books_order\WjBooksOrder.update_audit_progress');
        (new WjBooksOrderService())->updateAuditProgress($data['id'], $data['audit_progress']);
        return success('UPDATE_SUCCESS');
    }

    /**
     * 更新订单书籍最终价格
     * @return \think\Response
     */
    public function bookPrice()
    {
        $data = $this->request->params([
            ["id", 0],
            ["final_price", 0],
            ["accepted_quantity", 0],
        ]);
        $this->validate($data, 'addon\wj_books\app\validate\wj_books_order\WjBooksOrder.update_book_price');
        (new WjBooksOrderService())->updateOrderBookPrice($data['id'], $data['final_price'], $data['accepted_quantity']);
        return success('UPDATE_SUCCESS');
    }

    /**
     * 添加拒收书籍
     * @return \think\Response
     */
    public function rejectedBook()
    {
        $data = $this->request->params([
            ["order_book_id", 0],
            ["rejected_quantity", 1],
            ["reject_reason", ""],
            ["can_retrieve", 1],
        ]);
        $this->validate($data, 'addon\wj_books\app\validate\wj_books_order\WjBooksOrder.add_rejected_book');
        $id = (new WjBooksOrderService())->addRejectedBook($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 获取拒收书籍列表
     * @return \think\Response
     */
    public function rejectedBookList()
    {
        $data = $this->request->params([
            ["order_id", 0],
        ]);
        return success((new WjBooksOrderService())->getRejectedBookList($data['order_id']));
    }

    /**
     * 上传拒收书籍审核图片
     * @return \think\Response
     */
    public function uploadRejectedImage()
    {
        $data = $this->request->params([
            ["rejected_book_id", 0],
            ["image_type", 1],
            ["image_url", ""],
        ]);
        
        if (empty($data['image_url'])) {
            return fail('请选择要上传的图片');
        }
        
        $id = (new WjBooksOrderService())->uploadRejectedImage($data);
        return success('UPLOAD_SUCCESS', ['id' => $id]);
    }

    /**
     * 获取拒收书籍审核图片列表
     * @return \think\Response
     */
    public function rejectedImageList()
    {
        $data = $this->request->params([
            ["rejected_book_id", 0],
        ]);
        return success((new WjBooksOrderService())->getRejectedImageList($data['rejected_book_id']));
    }

    /**
     * 删除拒收书籍审核图片
     * @param int $id
     * @return \think\Response
     */
    public function deleteRejectedImage(int $id)
    {
        (new WjBooksOrderService())->deleteRejectedImage($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 更新拒收书籍信息
     * @return \think\Response
     */
    public function updateRejectedBook()
    {
        $data = $this->request->params([
            ["id", 0],
            ["reject_reason", ""],
            ["can_retrieve", 1],
        ]);
        (new WjBooksOrderService())->updateRejectedBook($data);
        return success('UPDATE_SUCCESS');
    }

    /**
     * 更新拒收书籍数量
     * @return \think\Response
     */
    public function updateRejectedBookQuantity()
    {
        $data = $this->request->params([
            ["id", 0],
            ["rejected_quantity", 1],
        ]);
        (new WjBooksOrderService())->updateRejectedBookQuantity($data);
        return success('UPDATE_SUCCESS');
    }

    /**
     * 获取取回申请列表
     * @return \think\Response
     */
    public function retrieveApplyList()
    {
        $data = $this->request->params([
            ["order_id", ""],
            ["member_id", ""],
            ["status", ""],
            ["create_time", []],
        ]);
        return success((new WjBooksOrderService())->getRetrieveApplyPage($data));
    }

    /**
     * 更新取回申请状态
     * @return \think\Response
     */
    public function retrieveApplyStatus()
    {
        $data = $this->request->params([
            ["id", 0],
            ["status", 0],
        ]);
        (new WjBooksOrderService())->updateRetrieveApplyStatus($data['id'], $data['status']);
        return success('UPDATE_SUCCESS');
    }

    /**
     * 更新物流信息
     * @param int $order_id
     * @return \think\Response
     */
    public function express(int $order_id)
    {
        $data = $this->request->params([
            ["express_company", ""],
            ["express_waybill", ""],
            ["express_remark", ""],
        ]);
        if (empty($data['express_company'])) {
            return fail('物流公司不能为空');
        }
        if (empty($data['express_waybill'])) {
            return fail('物流单号不能为空');
        }
        (new WjBooksOrderService())->updateExpress($order_id, $data);
        return success('UPDATE_SUCCESS');
    }

    /**
     * 获取物流渠道列表
     * @return \think\Response
     */
    public function expressChannelList()
    {
        return success((new WjBooksOrderService())->getExpressChannelList());
    }

    /**
     * 获取物流日志列表
     * @return \think\Response
     */
    public function expressLogList()
    {
        $data = $this->request->params([
            ["order_id", 0],
        ]);
        return success((new WjBooksOrderService())->getExpressLogList($data['order_id']));
    }

    /**
     * 完成订单
     * @param int $id
     * @return \think\Response
     */
    public function complete(int $id)
    {
        $data = $this->request->params([
            ["completion_message", ""],
            ["retrieve_deadline", null],
        ]);
        (new WjBooksOrderService())->completeOrder($id, $data);
        return success('COMPLETE_SUCCESS');
    }

    /**
     * 取消订单
     * @param int $id
     * @return \think\Response
     */
    public function cancel(int $id)
    {
        $data = $this->request->params([
            ["cancel_reason", ""],
        ]);
        $this->validate($data, 'addon\wj_books\app\validate\wj_books_order\WjBooksOrder.cancel_order');
        (new WjBooksOrderService())->cancelOrder($id, $data['cancel_reason']);
        return success('CANCEL_SUCCESS');
    }

    /**
     * 删除拒收书籍
     * @param int $id
     * @return \think\Response
     */
    public function deleteRejectedBook(int $id)
    {
        (new WjBooksOrderService())->deleteRejectedBook($id);
        return success('DELETE_SUCCESS');
    }
} 