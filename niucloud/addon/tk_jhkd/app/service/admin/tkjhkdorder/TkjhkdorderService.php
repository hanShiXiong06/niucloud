<?php
namespace addon\tk_jhkd\app\service\admin\tkjhkdorder;
use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use core\base\BaseAdminService;
use app\model\member\Member;
/**
 * 订单列服务层
 * Class TkjhkdorderService
 * @package addon\tk_jhkd\app\service\admin\tkjhkdorder
 */
class TkjhkdorderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Tkjhkdorder();
    }

    /**
     * 获取订单列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,order_from,order_id,order_money,order_discount_money,is_send,is_pick,order_status,refund_status,out_trade_no,remark,pay_time,create_time,close_reason,is_enable_refund,close_time,ip,update_time,delete_time,send_log';
        $order = '';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(['"member_id","order_from","order_id","is_send","order_status","refund_status","remark","create_time"'], $where)->with('member')->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取订单列信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,member_id,order_from,order_id,order_money,order_discount_money,is_send,is_pick,order_status,refund_status,out_trade_no,remark,pay_time,create_time,close_reason,is_enable_refund,close_time,ip,update_time,delete_time,send_log';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with('member')->findOrEmpty()->toArray();
        $info['is_send'] = strval($info['is_send'])
   $info['is_pick'] = strval($info['is_pick']);
        return $info;
    }

    /**
     * 添加订单列
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 订单列编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除订单列
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }

    public function getMemberAll(){
        $memberModel = new Member();
        return $memberModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

}
