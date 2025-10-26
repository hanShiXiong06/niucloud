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

namespace addon\home_service\app\service\api\order;

use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\order\Invoice;
use addon\home_service\app\model\order\Order;
use core\exception\ApiException;
use core\exception\CommonException;
use core\base\BaseApiService;
use think\facade\Db;


/**
 * 商品评价服务层
 * Class InvoiceService
 * @package addon\home_service\app\service\admin\order
 */
class InvoiceService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Invoice();
    }

    /**
     * 获取发票订单列表
     * @return array
     */
    public function getOrderPage()
    {
        $where['order_status'] = OrderDict::FINISH;
        $where['is_issue_invoice'] = 0;
        $field = 'order_id, order_type, site_id, member_id, order_from, order_type, order_no, out_trade_no, order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money, is_issue_invoice, order_name,reserve_service_time,service_finish_time';
        $order = 'create_time desc';
        $search_model = (new Order())->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])
            ->with(['item' => function ($query) {
                $query->field('order_id, item_id, item_name, item_image, pay_time,price, num, item_money,order_item_id, site_id, item_images, is_force_clock_in, is_force_departure, is_finish_photograph, item_type')->with(['goods_sku']);
            }])
            ->withSearch(['order_status', 'is_issue_invoice'], $where)->field($field)
            ->order($order)
            ->append(['item.item_image_thumb_small']);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取发票列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $order_model = new Order();
        $field = 'order_ids,header_type,header_name,type,status,content,tax_number,money,invoice_number,invoice_voucher,create_time,order_money';
        $order = 'create_time desc';
        $search_model = $this->model->where([ [ 'site_id', '=', $this->site_id ], [ 'member_id', '=', $this->member_id ] ])
            ->withSearch([ "status", "header_type", "type" ], $where)
            ->field($field)
            ->order($order)->append([ 'type_name', 'header_type_name', 'content_name', 'status_name' ]);
        $list = $this->pageQuery($search_model,function($item) use ($order_model){
            $order_no_list = $order_model->where([['site_id', '=', $this->site_id], ['order_id', 'in', $item['order_ids']]])->column('order_no');
            $item['order_no'] = implode(',',$order_no_list);
        });
        return $list;
    }

    /**
     * 获取发票详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'order_ids,header_type,header_name,type,status,content,tax_number,money,invoice_number,invoice_voucher,create_time,invoice_time';
        $invoice_info = $this->model->where([ [ 'site_id', '=', $this->site_id ], [ 'id', '=', $id ] ])
            ->field($field)->append([ 'type_name', 'header_type_name', 'content_name', 'status_name' ])->findOrEmpty()->toArray();
        $invoice_info['order_no'] = (new Order())->where([['site_id', '=', $this->site_id], ['order_id', 'in', $invoice_info['order_ids']]])->column('order_no');
        return $invoice_info;
    }

    /**
     * 添加发票
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        if(empty($data['order_ids'])) throw new ApiException('HOME_SERVICE_ORDER_NOT_FOUND');
        $order_model = new Order();
        $order_list = $order_model->field('pay_money,is_issue_invoice')->where([['site_id', '=', $this->site_id], ['order_id', 'in', $data['order_ids']]])->select();
        $is_issue_invoice_count = $pay_money_total = 0;
        foreach ($order_list as $value){
            if ($value['is_issue_invoice'] == 1) $is_issue_invoice_count += 1;
            $pay_money_total += $value['pay_money'];
        }
        if ($is_issue_invoice_count > 0) throw new ApiException('ORDER_ISSUED_INVOICE');
        Db::startTrans();
        try {
            $data['site_id'] = $this->site_id;
            $data['member_id'] = $this->member_id;
            $data['order_money'] = $pay_money_total;
            $data['order_ids'] = array_map(function ($item) {
                return (string) $item;
            }, $data[ 'order_ids' ]);

            $this->model->create($data);
            $order_model->where([['site_id', '=', $this->site_id], ['order_id', 'in', $data['order_ids']]])->update(['is_issue_invoice' => 1]);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }


}
