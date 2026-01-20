<?php

namespace addon\tk_jhkd\app\service\api;

use addon\tk_jhkd\app\model\order\OrderAdd;
use app\model\member\Member;
use core\base\BaseApiService;


/**
 * 补差价订单
 * Class OrderAddService
 * @package addon\tk_jhkd\app\service\api\order
 */
class OrderAddService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderAdd();
    }

    /**
     * 获取订单列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,order_id,order_no,order_money,order_status,out_trade_no,pay_time,create_time,close_time,ip,update_time,delete_time';
        $order = 'create_time desc';
        $search_model = $this->model
            ->where([['member_id', '=', $this->member_id]])
            ->where([['site_id', "=", $this->site_id]])
            ->withSearch(["id","member_id","order_id","order_no","order_money","order_status","out_trade_no","pay_time","create_time","close_time","ip","update_time","delete_time"], $where)
            ->with(
                [
                    'orderInfo',
                    'deliveryRealInfo',
                    'deliveryInfo'
                ]
            )
            ->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        $list['data'] = array_map(function ($item) {
            $item['order_status'] = strval($item['order_status']);
            $item['orderInfo']=$item['deliveryInfo'];
            $item['orderInfo']['price_rule'] = json_decode($item['orderInfo']['price_rule'], true);
            $item['orderInfo']['original_rule'] = json_decode($item['orderInfo']['original_rule'], true);
            $item['orderInfo']['courier_context'] = json_decode($item['orderInfo']['courier_context'], true);
            $item['orderInfo']['start_address'] = json_decode($item['orderInfo']['start_address'], true);
            $item['orderInfo']['end_address'] = json_decode($item['orderInfo']['end_address'], true);
            $fee_list=json_decode($item['deliveryRealInfo']['fee_blockList'],true);
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
            $item['deliveryRealInfo']['fee_blockList']=$new_fee_list;
            return $item;
        }, $list['data']);
        return $list;
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
}
