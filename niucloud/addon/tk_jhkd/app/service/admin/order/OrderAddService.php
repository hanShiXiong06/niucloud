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

namespace addon\tk_jhkd\app\service\admin\order;

use addon\tk_jhkd\app\job\notice\Webhook;
use addon\tk_jhkd\app\model\order\OrderAdd;
use addon\tk_jhkd\app\service\core\CommonService;
use app\model\member\Member;
use app\service\core\notice\NoticeService;
use core\base\BaseAdminService;


/**
 * 订单列服务层
 * Class OrderAddService
 * @package addon\tk_jhkd\app\service\admin\order
 */
class OrderAddService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderAdd();
    }

    public function sendNotice($order_id)
    {
        $info = $this->model->where(['order_id' => $order_id, 'site_id' => $this->site_id])->findOrEmpty();
        (new NoticeService())->send($this->site_id, 'tk_jhkd_order_add', ['order_id' => $order_id]);
        //更改通知次数
        $this->model->where(['order_id' => $order_id, 'site_id' => $this->site_id])->update(['notice_num' => $info['notice_num'] + 1]);
        $config = (new CommonService())->getConfig($this->site_id);
        $text = '补差价订单号：' . $order_id . ',待补差价金额：' . $info['order_money'] . '元，已经发送催款通知';
        Webhook::dispatch(['config' => $config, 'text' => $text]);
        return [];
    }

    /**
     * 获取订单列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,order_id,order_no,order_money,order_status,out_trade_no,pay_time,create_time,close_time,ip,update_time,delete_time,notice_num,remark';
        $order = 'create_time desc';
        $search_model = $this->model->where([['site_id', "=", $this->site_id]])
            ->withSearch(["member_id", "order_id", "order_status", "create_time"], $where)
            ->with(['member', 'deliveryInfo', 'deliveryRealInfo'])
            ->field($field)
            ->order($order);
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
        $field = 'id,site_id,member_id,order_id,order_no,order_money,order_status,out_trade_no,pay_time,create_time,close_time,ip,update_time,delete_time,notice_num,remark';

        $info = $this->model->field($field)->where([['id', "=", $id]])
            ->with([
                'member',
                'deliveryInfo'=>function ($query) {
                    $query ->append(['delivery_arry']);
                },
                'deliveryRealInfo',
                'orderInfo' => function ($query) {
                    $query->with([
                        'payInfo' => function ($query) {
                            $query->field('trade_id,status,pay_time,cancel_time,fail_reason,type,trade_type')->append(['status_name', 'type_name']);
                        },
                    ]);
                    $query->append(['order_status_arr']);
                },
            ])
            ->findOrEmpty()->toArray();
        $info['order_status'] = strval($info['order_status']);
        $info['deliveryInfo']['price_rule'] = json_decode($info['deliveryInfo']['price_rule'], true);
        $info['deliveryInfo']['original_rule'] = json_decode($info['deliveryInfo']['original_rule'], true);
        $info['deliveryInfo']['start_address'] = json_decode($info['deliveryInfo']['start_address'], true);
        $info['deliveryInfo']['end_address'] = json_decode($info['deliveryInfo']['end_address'], true);
        $info['deliveryInfo']['courier_context'] = json_decode($info['deliveryInfo']['courier_context'], true);
        $info['deliveryInfo']['delivery_arry'] = (new CommonService())->getBrand($info['deliveryInfo']['platform'],$info['deliveryInfo']['delivery_type']);
        $fee_list=json_decode($info['deliveryRealInfo']['fee_blockList'],true);
        $new_fee_list=[];
        foreach ($fee_list as $fee){
            if($fee['type']!=0){
                $new_fee_list[]=[
                    'fee'=>$fee['fee'],
                    'type'=>$fee['type'],
                    'name'=>$fee['name']
                ];
            }
        }
        $info['deliveryRealInfo']['fee_blockList']=$new_fee_list;
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

        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }
    public function editOrderMoney(int $id, array $data)
    {
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
            'order_money' => $data['order_money'],
        ]);
        return true;
    }    /**
     * 删除订单列
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }

    public function getMemberAll()
    {
        $memberModel = new Member();
        return $memberModel->where([["site_id", "=", $this->site_id]])->select()->toArray();
    }

}
