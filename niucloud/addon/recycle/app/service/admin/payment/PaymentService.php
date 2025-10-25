<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\payment;

use core\base\BaseAdminService;
use addon\recycle\app\model\PhoneShopPaymentInfo;

/**
 * 收款方式服务层
 * Class PaymentService
 * @package addon\recycle\app\service\admin\payment
 */
class PaymentService extends BaseAdminService
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new PhoneShopPaymentInfo();
    }

    /**
     * 获取收款方式列表
     * @param int $member_id 会员ID
     * @return array
     */
    public function getList($member_id)
    {
        $list = $this->model->where([
            ['member_id', '=', $member_id]
        ])->order('is_default', 'desc')->select()->toArray();
        
        return $list;
    }

    /**
     * 添加收款方式
     */
    public function add($data)
    {
        try {
            Db::startTrans();
            
            // 如果设置为默认，先将其他的设为非默认
            if (!empty($data['is_default'])) {
                $this->model->where([
                    ['member_id', '=', $data['member_id']]
                ])->update(['is_default' => 0]);
            }
            
            $result = $this->model->create($data);
            
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     * 编辑收款方式
     */
    public function edit($id, $data, $member_id)
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['member_id', '=', $member_id]
        ])->find();
        
        if (empty($info)) {
            return $this->fail('收款方式不存在');
        }

        try {
            Db::startTrans();
            
            if (!empty($data['is_default'])) {
                $this->model->where([
                    ['member_id', '=', $member_id]
                ])->update(['is_default' => 0]);
            }
            
            $info->save($data);
            
            Db::commit();
            return $this->success($info);
        } catch (\Exception $e) {
            Db::rollback();
            return $this->fail($e->getMessage());
        }
    }

    /**
     * 删除收款方式
     */
    public function delete($id, $member_id)
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['member_id', '=', $member_id]
        ])->find();
        
        if (empty($info)) {
            return $this->fail('收款方式不存在');
        }

        $info->delete();
        return $this->success();
    }

    /**
     * 设置默认收款方式
     */
    public function setDefault($id, $member_id)
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['member_id', '=', $member_id]
        ])->find();
        
        if (empty($info)) {
            return $this->fail('收款方式不存在');
        }

        try {
            Db::startTrans();
            
            // 先将所有收款方式设为非默认
            $this->model->where([
                ['member_id', '=', $member_id]
            ])->update(['is_default' => 0]);
            
            // 设置新的默认收款方式
            $info->save(['is_default' => 1]);
            
            Db::commit();
            return $this->success();
        } catch (\Exception $e) {
            Db::rollback();
            return $this->fail($e->getMessage());
        }
    }
}