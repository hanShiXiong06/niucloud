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

namespace addon\wj_books\app\service\admin\wj_books_order;

use addon\wj_books\app\model\wj_books_order\WjBooksOrder;
use addon\wj_books\app\model\wj_books_order\WjBooksOrderBook;
use addon\wj_books\app\model\wj_books_order\WjBooksRejectedBook;
use addon\wj_books\app\model\wj_books_order\WjBooksRejectedImages;
use addon\wj_books\app\model\wj_books_order\WjBooksRetrieveApply;
use addon\wj_books\app\model\wj_books_order\WjBooksExpressChannel;
use addon\wj_books\app\model\wj_books_order\WjBooksExpressLog;
use app\model\member\Member;
use app\model\member\MemberAddress;
use core\base\BaseAdminService;
use think\facade\Db;
use Exception;
use app\service\core\upload\CoreUploadService;

/**
 * 二手书籍回收订单服务层
 * Class WjBooksOrderService
 * @package addon\wj_books\app\service\admin\wj_books_order
 */
class WjBooksOrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new WjBooksOrder();
    }

    /**
     * 获取订单列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,order_no,member_id,address_id,book_count,total_amount,final_amount,final_book_count,status,remark,create_time,pickup_time,pickup_actual_time,complete_time,cancel_time,cancel_reason,completion_message,retrieve_deadline,express_channel,express_waybill,express_status,express_weight,express_freight,express_courier_name,express_courier_phone,express_pickup_code,audit_progress';
        $order = 'create_time desc';

        $search_model = $this->model->where([['site_id', '=', $this->site_id], ['deleted', '=', 0]])
            ->withSearch(['order_no', 'member_id', 'status', 'create_time', 'express_waybill'], $where)
            ->field($field)
            ->order($order);
        
        $list = $this->pageQuery($search_model);
        
        // 获取地址信息
        if (!empty($list['data'])) {
            foreach ($list['data'] as &$item) {
                // 获取地址信息
                $address_info = (new MemberAddress())->field('id,name,mobile,full_address')
                    ->where([['id', '=', $item['address_id']]])
                    ->findOrEmpty()
                    ->toArray();
                
                if (!empty($address_info)) {
                    $item['pickup_name'] = $address_info['name'];
                    $item['pickup_mobile'] = $address_info['mobile'];
                    $item['pickup_address'] = $address_info['full_address'];
                } else {
                    $item['pickup_name'] = '';
                    $item['pickup_mobile'] = '';
                    $item['pickup_address'] = '';
                }
            }
        }
        
        return $list;
    }

    /**
     * 获取订单详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,order_no,member_id,address_id,book_count,total_amount,final_amount,final_book_count,status,remark,create_time,pickup_time,pickup_actual_time,complete_time,cancel_time,cancel_reason,completion_message,retrieve_deadline,express_channel_id,express_channel,express_waybill,express_shopbill,express_status,express_weight,express_freight,express_courier_name,express_courier_phone,express_pickup_code,audit_progress';

        $info = $this->model->field($field)
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id], ['deleted', '=', 0]])
            ->findOrEmpty()
            ->toArray();
        
        if (!empty($info)) {
            // 获取会员信息
            $info['member_info'] = (new Member())->field('member_id,nickname,mobile,headimg')
                ->where([['member_id', '=', $info['member_id']]])
                ->findOrEmpty()
                ->toArray();
            
            // 获取地址信息
            $info['address_info'] = (new MemberAddress())->field('id,name,mobile,full_address')
                ->where([['id', '=', $info['address_id']]])
                ->findOrEmpty()
                ->toArray();
                
            // 获取订单书籍列表
            $info['books'] = $this->getOrderBookList($id);
            
            // 获取拒收书籍列表
            $info['rejected_books'] = $this->getRejectedBookList($id);
        }
        
        return $info;
    }

    /**
     * 获取订单书籍列表
     * @param int $order_id
     * @return array
     */
    public function getOrderBookList(int $order_id)
    {
        $field = 'id,site_id,order_id,book_id,title,author,img,isbn,quantity,accepted_quantity,price,final_price,create_time';
        $order = 'id asc';

        $list = (new WjBooksOrderBook())->field($field)
            ->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id]])
            ->order($order)
            ->select()
            ->toArray();
        
        return $list;
    }

    /**
     * 更新订单状态
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function updateStatus(int $id, int $status)
    {
        $order = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id], ['deleted', '=', 0]])->find();
        if (empty($order)) {
            throw new Exception('订单不存在');
        }

        $data = ['status' => $status];
        
        // 根据状态设置相应的时间字段
        switch ($status) {
            case 2: // 已取件
                $data['pickup_actual_time'] = date('Y-m-d H:i:s');
                break;
            case 4: // 已完成
                $data['complete_time'] = date('Y-m-d H:i:s');
                break;
            case 5: // 已取消
                $data['cancel_time'] = date('Y-m-d H:i:s');
                break;
        }

        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 更新订单审核进度
     * @param int $id
     * @param int $progress
     * @return bool
     */
    public function updateAuditProgress(int $id, int $progress)
    {
        if ($progress < 0 || $progress > 100) {
            throw new Exception('进度值必须在0-100之间');
        }

        $order = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id], ['deleted', '=', 0]])->find();
        if (empty($order)) {
            throw new Exception('订单不存在');
        }

        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update(['audit_progress' => $progress]);
        return true;
    }

    /**
     * 更新订单书籍最终价格
     * @param int $id
     * @param float $final_price
     * @param int $accepted_quantity
     * @return bool
     */
    public function updateOrderBookPrice(int $id, float $final_price, int $accepted_quantity)
    {
        $book = (new WjBooksOrderBook())->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($book)) {
            throw new Exception('订单书籍不存在');
        }

        if ($accepted_quantity < 0 || $accepted_quantity > $book['quantity']) {
            throw new Exception('接收数量不能大于总数量');
        }

        if ($final_price < 0) {
            throw new Exception('最终价格不能为负数');
        }

        Db::startTrans();
        try {
            // 更新订单书籍价格和接收数量
            (new WjBooksOrderBook())->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
                'final_price' => $final_price,
                'accepted_quantity' => $accepted_quantity
            ]);

            // 更新订单总金额和总数量
            $order_id = $book['order_id'];
            $this->updateOrderFinalAmount($order_id);

            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 更新订单最终金额和数量
     * @param int $order_id
     * @return bool
     */
    private function updateOrderFinalAmount(int $order_id)
    {
        $books = (new WjBooksOrderBook())->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id]])
            ->field('final_price, accepted_quantity')
            ->select()
            ->toArray();
        
        $final_amount = 0;
        $final_book_count = 0;
        
        foreach ($books as $book) {
            if (!is_null($book['final_price']) && !is_null($book['accepted_quantity'])) {
                $final_amount += $book['final_price'] * $book['accepted_quantity'];
                $final_book_count += $book['accepted_quantity'];
            }
        }
        
        $this->model->where([['id', '=', $order_id], ['site_id', '=', $this->site_id]])->update([
            'final_amount' => $final_amount,
            'final_book_count' => $final_book_count
        ]);
        
        return true;
    }

    /**
     * 添加拒收书籍
     * @param array $data
     * @return int
     */
    public function addRejectedBook(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = date('Y-m-d H:i:s');
        
        // 验证订单书籍是否存在
        $order_book = (new WjBooksOrderBook())->where([
            ['id', '=', $data['order_book_id']],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($order_book)) {
            throw new Exception('订单书籍不存在');
        }
        
        // 验证拒收数量
        if ($data['rejected_quantity'] <= 0 || $data['rejected_quantity'] > $order_book['quantity']) {
            throw new Exception('拒收数量不合法');
        }
        
        // 补充书籍信息
        $data['book_id'] = $order_book['book_id'];
        $data['title'] = $order_book['title'];
        $data['author'] = $order_book['author'];
        $data['img'] = $order_book['img'];
        $data['isbn'] = $order_book['isbn'];
        $data['order_id'] = $order_book['order_id'];
        
        $id = (new WjBooksRejectedBook())->insertGetId($data);
        return $id;
    }

    /**
     * 获取拒收书籍列表
     * @param int $order_id
     * @return array
     */
    public function getRejectedBookList(int $order_id)
    {
        $field = 'id,site_id,order_id,order_book_id,book_id,title,author,img,isbn,rejected_quantity,reject_reason,can_retrieve,create_time';
        $order = 'id asc';

        $list = (new WjBooksRejectedBook())->field($field)
            ->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id]])
            ->order($order)
            ->select()
            ->toArray();
        
        return $list;
    }

    /**
     * 上传拒收书籍审核图片
     * @param array $data
     * @return int
     */
    public function uploadRejectedImage(array $data)
    {
        // 验证拒收书籍是否存在
        $rejected_book = (new WjBooksRejectedBook())->where([
            ['id', '=', $data['rejected_book_id']],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($rejected_book)) {
            throw new Exception('拒收书籍不存在');
        }
        
        // 验证图片URL是否存在
        if (empty($data['image_url'])) {
            throw new Exception('请选择要上传的图片');
        }
        
        // 保存图片记录
        $image_data = [
            'site_id' => $this->site_id,
            'rejected_book_id' => $data['rejected_book_id'],
            'image_url' => $data['image_url'],
            'image_type' => $data['image_type'] ?? 1,
            'create_time' => date('Y-m-d H:i:s')
        ];
        
        $id = (new WjBooksRejectedImages())->insertGetId($image_data);
        return $id;
    }

    /**
     * 获取拒收书籍审核图片列表
     * @param int $rejected_book_id
     * @return array
     */
    public function getRejectedImageList(int $rejected_book_id)
    {
        $field = 'id,site_id,rejected_book_id,image_url,image_type,create_time';
        $order = 'id asc';

        $list = (new WjBooksRejectedImages())->field($field)
            ->where([['rejected_book_id', '=', $rejected_book_id], ['site_id', '=', $this->site_id]])
            ->order($order)
            ->select()
            ->toArray();
        
        return $list;
    }

    /**
     * 获取取回申请列表
     * @param array $where
     * @return array
     */
    public function getRetrieveApplyPage(array $where = [])
    {
        $field = 'id,site_id,order_id,member_id,address_id,rejected_book_ids,express_waybill,express_company,status,remark,create_time,ship_time,complete_time';
        $order = 'create_time desc';

        $search_model = (new WjBooksRetrieveApply())->where([['site_id', '=', $this->site_id]])
            ->withSearch(['order_id', 'member_id', 'status', 'create_time'], $where)
            ->field($field)
            ->order($order);
        
        $list = $this->pageQuery($search_model);
        
        // 获取会员和地址信息
        if (!empty($list['data'])) {
            foreach ($list['data'] as &$item) {
                // 获取会员信息
                $item['member_info'] = (new Member())->field('member_id,nickname,mobile,headimg')
                    ->where([['member_id', '=', $item['member_id']]])
                    ->findOrEmpty()
                    ->toArray();
                
                // 获取地址信息
                $item['address_info'] = (new MemberAddress())->field('id,name,mobile,full_address')
                    ->where([['id', '=', $item['address_id']]])
                    ->findOrEmpty()
                    ->toArray();
                
                // 获取拒收书籍信息
                if (!empty($item['rejected_book_ids'])) {
                    $rejected_book_ids = explode(',', $item['rejected_book_ids']);
                    $item['rejected_books'] = (new WjBooksRejectedBook())->field('id,title,author,img,isbn,rejected_quantity')
                        ->where([['id', 'in', $rejected_book_ids], ['site_id', '=', $this->site_id]])
                        ->select()
                        ->toArray();
                } else {
                    $item['rejected_books'] = [];
                }
            }
        }
        
        return $list;
    }

    /**
     * 更新取回申请状态
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function updateRetrieveApplyStatus(int $id, int $status)
    {
        $apply = (new WjBooksRetrieveApply())->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($apply)) {
            throw new Exception('取回申请不存在');
        }

        $data = ['status' => $status];
        
        // 根据状态设置相应的时间字段
        switch ($status) {
            case 1: // 已发货
                $data['ship_time'] = date('Y-m-d H:i:s');
                break;
            case 2: // 已完成
                $data['complete_time'] = date('Y-m-d H:i:s');
                break;
        }

        (new WjBooksRetrieveApply())->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 更新物流信息
     * @param int $order_id
     * @param array $data
     * @return bool
     */
    public function updateExpress(int $order_id, array $data)
    {
        $order = $this->model->where([['id', '=', $order_id], ['site_id', '=', $this->site_id], ['deleted', '=', 0]])->find();
        if (empty($order)) {
            throw new Exception('订单不存在');
        }

        // 更新订单物流信息
        $update_data = [
            'express_channel' => $data['express_company'], // 使用express_channel字段存储物流公司名称
            'express_waybill' => $data['express_waybill'],
            'express_status' => 1 // 默认为待揽收状态
        ];
        
        // 如果有备注，更新remark字段
        if (!empty($data['express_remark'])) {
            $update_data['remark'] = $data['express_remark'];
        }

        $this->model->where([['id', '=', $order_id], ['site_id', '=', $this->site_id]])->update($update_data);
        return true;
    }

    /**
     * 获取物流渠道列表
     * @return array
     */
    public function getExpressChannelList()
    {
        $field = 'id,site_id,channel_id,channel,channel_logo_url,tag_type,tag_code,original_price,discount,freight,insured_rate,paozhong,channel_explain,price_comments';
        $order = 'id asc';

        $list = (new WjBooksExpressChannel())->field($field)
            ->where([['site_id', '=', $this->site_id], ['is_enabled', '=', 1]])
            ->order($order)
            ->select()
            ->toArray();
        
        return $list;
    }

    /**
     * 获取物流日志列表
     * @param int $order_id
     * @return array
     */
    public function getExpressLogList(int $order_id)
    {
        $field = 'id,site_id,order_id,waybill,shopbill,type,type_code,weight,real_weight,transfer_weight,cal_weight,volume,parse_weight,total_freight,freight,freight_insured,freight_haocai,change_bill,change_bill_freight,fee_over,courier_name,courier_phone,pickup_code,create_time';
        $order = 'create_time desc';

        $list = (new WjBooksExpressLog())->field($field)
            ->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id]])
            ->order($order)
            ->select()
            ->toArray();
        
        return $list;
    }

    /**
     * 删除拒收书籍审核图片
     * @param int $id 图片ID
     * @return bool
     */
    public function deleteRejectedImage(int $id)
    {
        $image = (new WjBooksRejectedImages())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($image)) {
            throw new Exception('图片不存在');
        }
        
        // 删除图片记录
        (new WjBooksRejectedImages())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->delete();
        
        return true;
    }
    
    /**
     * 更新拒收书籍信息
     * @param array $data
     * @return bool
     */
    public function updateRejectedBook(array $data)
    {
        if (empty($data['id'])) {
            throw new Exception('拒收书籍ID不能为空');
        }
        
        $rejected_book = (new WjBooksRejectedBook())->where([
            ['id', '=', $data['id']],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($rejected_book)) {
            throw new Exception('拒收书籍不存在');
        }
        
        // 更新拒收书籍信息
        $update_data = [];
        
        if (isset($data['reject_reason'])) {
            $update_data['reject_reason'] = $data['reject_reason'];
        }
        
        if (isset($data['can_retrieve'])) {
            $update_data['can_retrieve'] = $data['can_retrieve'];
        }
        
        if (!empty($update_data)) {
            (new WjBooksRejectedBook())->where([
                ['id', '=', $data['id']],
                ['site_id', '=', $this->site_id]
            ])->update($update_data);
        }
        
        return true;
    }
    
    /**
     * 更新拒收书籍数量
     * @param array $data
     * @return bool
     */
    public function updateRejectedBookQuantity(array $data)
    {
        if (empty($data['id'])) {
            throw new Exception('拒收书籍ID不能为空');
        }
        
        if (!isset($data['rejected_quantity']) || $data['rejected_quantity'] <= 0) {
            throw new Exception('拒收数量必须大于0');
        }
        
        $rejected_book = (new WjBooksRejectedBook())->where([
            ['id', '=', $data['id']],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($rejected_book)) {
            throw new Exception('拒收书籍不存在');
        }
        
        // 验证拒收数量
        $order_book = (new WjBooksOrderBook())->where([
            ['id', '=', $rejected_book['order_book_id']],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($order_book)) {
            throw new Exception('订单书籍不存在');
        }
        
        if ($data['rejected_quantity'] > $order_book['quantity']) {
            throw new Exception('拒收数量不能大于订单书籍数量');
        }
        
        // 更新拒收书籍数量
        (new WjBooksRejectedBook())->where([
            ['id', '=', $data['id']],
            ['site_id', '=', $this->site_id]
        ])->update(['rejected_quantity' => $data['rejected_quantity']]);
        
        return true;
    }

    /**
     * 完成订单
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function completeOrder(int $id, array $data)
    {
        $order = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id], ['deleted', '=', 0]])->find();
        if (empty($order)) {
            throw new Exception('订单不存在');
        }

        if ($order['status'] != 3) {
            throw new Exception('只有审核中的订单才能完成');
        }

        Db::startTrans();
        try {
            // 从配置表中获取拒收书籍可取回的期限天数
            $configModel = new \addon\wj_books\app\model\wj_books_config\WjBooksConfig();
            $config = $configModel->where([['site_id', '=', $this->site_id]])->find();
            
            // 默认为7天，如果配置表中有值则使用配置值
            $retrieveDays = !empty($config['rejected_book_retrieve_days']) ? intval($config['rejected_book_retrieve_days']) : 7;
            
            // 设置取回截止时间为当前时间+配置的天数
            $retrieveDeadline = date('Y-m-d H:i:s', strtotime('+' . $retrieveDays . ' days'));
            
            // 更新订单状态为已完成
            $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
                'status' => 4,
                'complete_time' => date('Y-m-d H:i:s'),
                'completion_message' => $data['completion_message'] ?? '',
                'retrieve_deadline' => $retrieveDeadline // 使用自动计算的截止时间
            ]);

            // TODO: 处理会员余额或积分增加等操作

            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 取消订单
     * @param int $id
     * @param string $cancel_reason
     * @return bool
     */
    public function cancelOrder(int $id, string $cancel_reason)
    {
        $order = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id], ['deleted', '=', 0]])->find();
        if (empty($order)) {
            throw new Exception('订单不存在');
        }

        if ($order['status'] == 4 || $order['status'] == 5) {
            throw new Exception('已完成或已取消的订单不能取消');
        }

        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
            'status' => 5,
            'cancel_time' => date('Y-m-d H:i:s'),
            'cancel_reason' => $cancel_reason
        ]);
        
        return true;
    }

    /**
     * 删除拒收书籍
     * @param int $id
     * @return bool
     */
    public function deleteRejectedBook(int $id)
    {
        $rejected_book = (new WjBooksRejectedBook())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($rejected_book)) {
            throw new Exception('拒收书籍不存在');
        }
        
        // 删除相关的拒收图片
        (new WjBooksRejectedImages())->where([
            ['rejected_book_id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->delete();
        
        // 删除拒收书籍记录
        (new WjBooksRejectedBook())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->delete();
        
        return true;
    }
}
