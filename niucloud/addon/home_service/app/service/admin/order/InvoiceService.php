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

namespace addon\home_service\app\service\admin\order;

use addon\home_service\app\dict\order\InvoiceDict;
use addon\home_service\app\model\order\Invoice;
use addon\home_service\app\model\order\Order;
use core\base\BaseAdminService;
use core\exception\AdminException;


/**
 * 发票服务层
 */
class InvoiceService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Invoice();
    }

    /**
     * 获取发票列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {

        $order_model = new Order();
        $field = 'email,id,order_ids,header_type,header_name,type,status,content,tax_number,money,invoice_number,invoice_voucher,create_time,invoice_time,tax_number,telephone,address,bank_name,bank_card_number,order_money';
        $order = 'create_time desc';

        $where_arr = [];
        if ($where['status'] != 'all'){
            $where_arr[] = ['invoice.status', '=', $where['status']];
        }
        if (!empty($where['header_name'])){
            $where_arr[] = ['header_name', 'like', '%' . $where['header_name'] . '%'];
        }
        if (!empty($where['create_time'])){
            $start_time = empty($where['create_time'][0]) ? 0 : strtotime($where['create_time'][0]);
            $end_time = empty($where['create_time'][1]) ? 0 : strtotime($where['create_time'][1]);
            if ($start_time > 0 && $end_time > 0) {
                $where_arr[] = ['invoice.create_time','between',[$start_time,$end_time]];
            } else if ($start_time > 0 && $end_time == 0) {
                $where_arr[] = ['invoice.create_time', '>=', $start_time];
            } else if ($start_time == 0 && $end_time > 0) {
                $where_arr[] = ['invoice.create_time', '<=', $end_time];
            }
        }
        $search_model = $this->model->where([[ 'invoice.site_id', '=', $this->site_id ]])
                ->where($where_arr)
            ->field($field)
            ->withJoin(['member' => function ($query) use ($where){
                $query = $query->field('member.member_id,nickname');
                if (!empty($where['nickname'])){
                    $query->where([['nickname', '=', $where['nickname']]]);
                }
            }])
            ->order($order)->hidden(['member'])->append([ 'type_name', 'header_type_name', 'content_name', 'status_name' ]);
        $list = $this->pageQuery($search_model,function($item) use ($order_model){
            $order_no_list = $order_model->where([['site_id', '=', $this->site_id], ['order_id', 'in', $item['order_ids']]])->column('order_no');
            $item['order_no'] = implode(',',$order_no_list);
        });
        return $list;
    }

    /**
     * 开具发票
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function issueInvoice($id,$data)
    {
        $invoice_info = $this->model->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($invoice_info->isEmpty()) throw new AdminException('INVOICE_NOT_EXIST');
        if ($invoice_info->status == 1) throw new AdminException('INVOICE_ISSUED');

        if($data['money'] > $invoice_info->order_money) throw new AdminException('INVOICE_MONEY_NOT_GREATER_THAN_PAY_MONEY');

        $data['status'] = InvoiceDict::ISSUED;
        $data['invoice_time'] = time();
        $this->model->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->update($data);
        return true;
    }

    /**
     * 订单发票详情
     * @param array $data
     * @return array
     */
    public function  orderInvoiceInfo($data)
    {
        $order_model = new Order();

        $invoice_info = $order_model->where([['site_id', '=', $this->site_id], ['order_id', '=', $data['order_id']]])->findOrEmpty();
        if ($invoice_info->isEmpty()) return [];

        $invoice_info = $this->model->field('*')
            ->where([['invoice.site_id', '=', $this->site_id]])
            ->withSearch(['order_id'],$data)
            ->withJoin(['member' => function ($query){
            $query->field('member.member_id,nickname');
        }])->hidden(['member'])
            ->append([ 'type_name', 'header_type_name', 'content_name', 'status_name' ])->findOrEmpty()->toArray();
        if (empty($invoice_info)) return [];
        return $invoice_info;
    }





}
