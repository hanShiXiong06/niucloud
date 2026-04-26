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

namespace addon\wj_books\app\service\admin\wj_books_retrieve_apply;

use addon\wj_books\app\model\wj_books_retrieve_apply\WjBooksRetrieveApply;
use addon\wj_books\app\model\wj_books_order\WjBooksOrder;
use addon\wj_books\app\model\wj_books_rejected_book\WjBooksRejectedBook;
use app\model\member\Member;
use app\model\member\MemberAddress;
use core\base\BaseAdminService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;

/**
 * 取回申请服务类
 * Class WjBooksRetrieveApplyService
 * @package addon\wj_books\app\service\admin\wj_books_retrieve_apply
 */
class WjBooksRetrieveApplyService extends BaseAdminService
{
    /**
     * @var WjBooksRetrieveApply
     */
    protected $model;

    /**
     * @var WjBooksOrder
     */
    protected $orderModel;

    /**
     * @var WjBooksRejectedBook
     */
    protected $rejectedBookModel;

    /**
     * @var Member
     */
    protected $memberModel;

    /**
     * @var MemberAddress
     */
    protected $addressModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = new WjBooksRetrieveApply();
        $this->orderModel = new WjBooksOrder();
        $this->rejectedBookModel = new WjBooksRejectedBook();
        $this->memberModel = new Member();
        $this->addressModel = new MemberAddress();
    }

    /**
     * 获取取回申请分页列表
     * @param array $where
     * @return array
     * @throws DbException
     */
    public function getPage(array $where = [])
    {
        $field = 'id, site_id, order_id, member_id, address_id, rejected_book_ids, express_waybill, 
                express_company, status, remark, create_time, ship_time, complete_time';
        
        $condition = [];
        
        // 站点条件
        $condition[] = ['site_id', '=', $this->site_id];
        
        // 订单号搜索
        if (!empty($where['order_no'])) {
            // 先通过订单号找到订单ID
            $orderIds = $this->orderModel->where([
                ['order_no', 'like', '%' . $where['order_no'] . '%'],
                ['site_id', '=', $this->site_id]
            ])->column('id');
            
            if (!empty($orderIds)) {
                $condition[] = ['order_id', 'in', $orderIds];
            } else {
                // 没有符合条件的订单，返回空结果
                return [
                    'count' => 0,
                    'list' => [],
                    'page' => $where['page'],
                    'limit' => $where['limit']
                ];
            }
        }
        
        // 状态筛选
        if (isset($where['status']) && $where['status'] !== '') {
            $condition[] = ['status', '=', $where['status']];
        }
        
        // 时间范围
        if (!empty($where['create_time']) && is_array($where['create_time']) && count($where['create_time']) == 2) {
            $start_time = $where['create_time'][0];
            $end_time = $where['create_time'][1];
            $condition[] = ['create_time', 'between', [$start_time . ' 00:00:00', $end_time . ' 23:59:59']];
        }

        $list = $this->model->where($condition)
            ->field($field)
            ->page($where['page'], $where['limit'])
            ->order('create_time desc')
            ->select()
            ->toArray();

        $count = $this->model->where($condition)->count();

        // 补充订单信息、会员信息、地址信息
        foreach ($list as &$item) {
            // 获取订单信息
            $order = $this->orderModel->where([
                ['id', '=', $item['order_id']],
                ['site_id', '=', $this->site_id]
            ])->field('id, order_no')->find();
            
            if ($order) {
                $item['order_no'] = $order['order_no'];
            } else {
                $item['order_no'] = '';
            }
            
            // 获取会员信息
            $member = $this->memberModel->where([
                ['member_id', '=', $item['member_id']]
            ])->field('member_id, nickname, mobile')->find();
            
            if ($member) {
                $item['member_name'] = $member['nickname'];
                $item['member_mobile'] = $member['mobile'];
            } else {
                $item['member_name'] = '';
                $item['member_mobile'] = '';
            }
            
            // 获取地址信息
            $address = $this->addressModel->where([
                ['id', '=', $item['address_id']]
            ])->field('id, name, mobile, province_id, city_id, district_id, address, full_address')->find();
            
            if ($address) {
                $item['address_info'] = $address;
            } else {
                $item['address_info'] = null;
            }
            
            // 计算拒收书籍数量
            if (!empty($item['rejected_book_ids'])) {
                $bookIds = explode(',', $item['rejected_book_ids']);
                $item['rejected_book_count'] = count($bookIds);
            } else {
                $item['rejected_book_count'] = 0;
            }
        }

        return [
            'count' => $count,
            'list' => $list,
            'page' => $where['page'],
            'limit' => $where['limit']
        ];
    }

    /**
     * 获取取回申请详情
     * @param int $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getDetail(int $id)
    {
        $field = 'id, site_id, order_id, member_id, address_id, rejected_book_ids, express_waybill, 
                express_company, status, remark, create_time, ship_time, complete_time';
                
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->field($field)->findOrFail();
        
        $info = $info->toArray();
        
        // 获取订单信息
        $order = $this->orderModel->where([
            ['id', '=', $info['order_id']],
            ['site_id', '=', $this->site_id]
        ])->field('id, order_no')->find();
        
        if ($order) {
            $info['order_no'] = $order['order_no'];
        } else {
            $info['order_no'] = '';
        }
        
        // 获取会员信息
        $member = $this->memberModel->where([
            ['member_id', '=', $info['member_id']]
        ])->field('member_id, nickname, mobile')->find();
        
        if ($member) {
            $info['member_name'] = $member['nickname'];
            $info['member_mobile'] = $member['mobile'];
        } else {
            $info['member_name'] = '';
            $info['member_mobile'] = '';
        }
        
        // 获取地址信息
        $address = $this->addressModel->where([
            ['id', '=', $info['address_id']]
        ])->field('id, name, mobile, province_id, city_id, district_id, address, full_address')->find();
        
        if ($address) {
            $info['address_info'] = $address;
        } else {
            $info['address_info'] = null;
        }
        
        // 获取拒收书籍信息
        if (!empty($info['rejected_book_ids'])) {
            $bookIds = explode(',', $info['rejected_book_ids']);
            $rejectedBooks = $this->rejectedBookModel->whereIn('id', $bookIds)
                ->where('site_id', $this->site_id)
                ->select()
                ->toArray();
                
            $info['rejected_books'] = $rejectedBooks;
        } else {
            $info['rejected_books'] = [];
        }

        return $info;
    }

    /**
     * 更新取回申请状态
     * @param array $data
     * @return array
     */
    public function updateStatus(array $data)
    {
        if (empty($data['id']) || !isset($data['status'])) {
            return error('', 'ID和状态不能为空');
        }
        
        $id = $data['id'];
        $status = $data['status'];
        
        // 检查是否存在
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (!$info) {
            return error('', '取回申请不存在');
        }
        
        // 更新状态
        $updateData = [
            'status' => $status
        ];
        
        // 根据不同状态设置不同字段
        if ($status == 1) { // 已发货
            $updateData['ship_time'] = date('Y-m-d H:i:s');
        } elseif ($status == 2) { // 已完成
            $updateData['complete_time'] = date('Y-m-d H:i:s');
        }
        
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update($updateData);
        
        return success(true);
    }

    /**
     * 发货处理
     * @param array $data
     * @return array
     */
    public function ship(array $data)
    {
        if (empty($data['id']) || empty($data['express_company']) || empty($data['express_waybill'])) {
            return error('', '参数不完整');
        }
        
        $id = $data['id'];
        
        // 检查是否存在
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (!$info) {
            return error('', '取回申请不存在');
        }
        
        // 检查状态
        if ($info['status'] != 0) {
            return error('', '只有申请中状态才能执行发货操作');
        }
        
        // 更新数据
        $updateData = [
            'status' => 1, // 已发货
            'express_company' => $data['express_company'],
            'express_waybill' => $data['express_waybill'],
            'ship_time' => date('Y-m-d H:i:s')
        ];
        
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update($updateData);
        
        return success(true);
    }

    /**
     * 完成取回申请
     * @param int $id
     * @return array
     */
    public function complete(int $id)
    {
        // 检查是否存在
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (!$info) {
            return error('', '取回申请不存在');
        }
        
        // 检查状态
        if ($info['status'] != 1) {
            return error('', '只有已发货状态才能执行完成操作');
        }
        
        // 更新数据
        $updateData = [
            'status' => 2, // 已完成
            'complete_time' => date('Y-m-d H:i:s')
        ];
        
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update($updateData);
        
        return success(true);
    }

    /**
     * 取消取回申请
     * @param array $data
     * @return array
     */
    public function cancel(array $data)
    {
        if (empty($data['id'])) {
            return error('', 'ID不能为空');
        }
        
        $id = $data['id'];
        $reason = $data['cancel_reason'] ?? '管理员取消';
        
        // 检查是否存在
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (!$info) {
            return error('', '取回申请不存在');
        }
        
        // 检查状态
        if ($info['status'] != 0) {
            return error('', '只有申请中状态才能取消');
        }
        
        Db::startTrans();
        try {
            // 更新取回申请状态
            $this->model->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])->update([
                'status' => 3 // 已取消
            ]);
            
            // 恢复拒收书籍的可取回状态
            if (!empty($info['rejected_book_ids'])) {
                $bookIds = explode(',', $info['rejected_book_ids']);
                $this->rejectedBookModel->whereIn('id', $bookIds)
                    ->where('site_id', $this->site_id)
                    ->update([
                        'can_retrieve' => 1 // 设置为可取回
                    ]);
            }
            
            Db::commit();
            return success(true);
        } catch (\Exception $e) {
            Db::rollback();
            return error('', '取消失败：' . $e->getMessage());
        }
    }

    /**
     * 删除取回申请
     * @param int $id
     * @return array
     */
    public function delete(int $id)
    {
        // 检查是否存在
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (!$info) {
            return error('', '取回申请不存在');
        }
        
        // 检查状态，只有已完成或已取消的申请才能删除
        if ($info['status'] != 2 && $info['status'] != 3) {
            return error('', '只有已完成或已取消的申请才能删除');
        }
        
        // 删除申请
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->delete();
        
        return success(true);
    }
} 