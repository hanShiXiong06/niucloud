<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\listener\printer;

use addon\recycle\app\model\order\RecycleOrder;
use app\model\pay\Pay;
use app\model\sys\SysPrinterTemplate;
use app\service\core\printer\CorePrinterService;
use app\service\core\site\CoreSiteService;

/**
 * 回收订单小票打印内容
 * Class OrderPrinterListener
 * @package addon\recycle\app\listener\printer
 */
class OrderPrinterListener
{

    /**
     * 处理打印内容
     * @param $params
     * @return array
     * @throws \think\db\exception\DbException
     */
    public function handle($params)
    {
        $key = 'recycleOrder';
        if (!empty($params) && !empty($params['type']) && $params['type'] != $key) return [];

        // 根据自身业务查询符合条件的打印机列表
        $printer_service = new CorePrinterService();
        $printer_list = $printer_service->getList([
            ['site_id', '=', $params['site_id']],
            ['status', '=', 1],
            ['template_type', 'like', ['%' . $key . '%']],
            ['trigger', 'like', ['%' . $key . '_trigger_submit_after%', '%' . $key . '_trigger_complete_after%', '%' . $key . '_trigger_manual%'], 'or'],
        ], 'printer_id,brand,printer_name,printer_code,printer_key,open_id,apikey,value');

        if (empty($printer_list)) {
            return [
                'code' => -1,
                'message' => '未找到小票打印机'
            ];
        }

        // 查询订单信息
        $order_model = new RecycleOrder();
        $field = 'order_id, order_no, order_from, out_trade_no, status, member_id, contact_name, contact_mobile, recycle_money, discount_money, order_money, create_time, pay_time, finish_time, recycle_type, site_id, full_address, user_remark, admin_remark';
        $order_info = $order_model->where([['order_id', '=', $params['business']['order_id']], ['site_id', '=', $params['site_id']]])->field($field)
            ->with(
                [
                    'order_items' => function($query) {
                        $query->field('item_id, order_id, name, num, price, total_price, discount_price');
                    },
                    'member' => function($query) {
                        $query->field('member_id,nickname,mobile,balance,point');
                    }
                ]
            )->append(['order_from_name', 'status_name', 'recycle_type_name'])->findOrEmpty()->toArray();

        if (empty($order_info)) {
            return [
                'code' => -1,
                'message' => '未找到订单信息'
            ];
        }

        if ($order_info['out_trade_no']) {
            $order_info['pay'] = (new Pay())->where([['out_trade_no', '=', $order_info['out_trade_no']]])
                ->field('out_trade_no, type, pay_time')->append(['type_name'])->findOrEmpty()->toArray();
        }

        $res_data = [];

        $printer_template_model = new SysPrinterTemplate();

        // 拼装打印内容
        foreach ($printer_list as $k => $v) {

            if (!empty($v['value'][$params['type']]) && !empty($v['value'][$params['type']]['trigger_' . $params['trigger']])) {
                $info = $v['value'][$params['type']]['trigger_' . $params['trigger']];

                // 过滤不符合条件的模板
                if (in_array($order_info['recycle_type'], $info['print_delivery_type']) == false) {
                    continue;
                }

                // 查询小票模板内容
                $template_info = $printer_template_model->field('value')->where([
                    ['site_id', '=', $params['site_id']],
                    ['template_id', "=", $info['template_id']],
                    ['template_type', '=', $params['type']]
                ])->findOrEmpty()->toArray();

                if (empty($template_info) && empty($template_info['value'])) {
                    continue;
                }

                $template_info_value = $template_info['value'];

                $content = "<MN>" . $info['print_num'] . "</MN>";

                // 获取 票头信息内容
                $content .= $this->getTicketHeadContent($params['site_id'], $template_info_value['head_info']);

                // 获取 订单信息内容
                $content .= $this->getOrderInfoContent($template_info_value['order_info'], $order_info);

                // 获取 物品信息内容
                $content .= $this->getGoodsInfoContent($template_info_value['goods_info'], $order_info);

                // 获取 用户/回收信息内容
                $content .= $this->getUserInfoContent($template_info_value['user_info'], $order_info);

                // 获取 底部信息内容
                $content .= $this->getBottomInfoContent($template_info_value['bottom_info']);

                $item = [
                    'printer_info' => $v,
                    'origin_id' => $order_info['order_no'], // 内部订单号
                    'content' => $content
                ];

                $res_data[] = $item;
            }
        }

        return [
            'code' => 0,
            'message' => '',
            'data' => $res_data
        ];
    }

    /**
     * 获取票头信息内容
     * @param $site_id
     * @param $data
     * @return string
     */
    private function getTicketHeadContent($site_id, $data)
    {
        $content = '';

        // 小票名称
        if ($data['ticket_name']['status'] == 1 && !empty($data['ticket_name']['value'])) {
            // 文字大小
            if ($data['ticket_name']['fontSize'] == 'big') {
                $content .= "<FH2>";
            }

            // 文字加粗
            if ($data['ticket_name']['fontWeight'] == 'bold') {
                $content .= "<FB>";
            }

            $content .= "<center>";
            $content .= $data['ticket_name']['value'];
            $content .= "</center>";

            if ($data['ticket_name']['fontWeight'] == 'bold') {
                $content .= "</FB>";
            }

            // 文字大小
            if ($data['ticket_name']['fontSize'] == 'big') {
                $content .= "</FH2>";
            }

            $content .= str_repeat('.', 32);
        }

        $site_info = (new CoreSiteService())->getSiteCache($site_id);

        // 平台名称
        if ($data['shop_name']['status'] == 1 && !empty($site_info['site_name'])) {
            // 文字大小
            if ($data['shop_name']['fontSize'] == 'big') {
                $content .= "<FH2>";
            }

            // 文字加粗
            if ($data['shop_name']['fontWeight'] == 'bold') {
                $content .= "<FB>";
            }

            $content .= "<center>";
            $content .= $site_info['site_name'];
            $content .= "</center>";

            if ($data['shop_name']['fontWeight'] == 'bold') {
                $content .= "</FB>";
            }

            // 文字大小
            if ($data['shop_name']['fontSize'] == 'big') {
                $content .= "</FH2>";
            }

            $content .= str_repeat('.', 32);
        }

        return $content;
    }

    /**
     * 获取订单信息内容
     * @param $data
     * @param $order_info
     * @return string
     */
    private function getOrderInfoContent($data, $order_info)
    {
        $content = '';

        // 订单基础信息
        if ($data['order_item']['status'] == 1 && !empty($data['order_item']['value'])) {
            // 订单编号
            if (in_array('order_no', $data['order_item']['value'])) {
                $content .= '订单编号：' . $order_info['order_no'] . "\n";
            }

            // 订单来源
            if (in_array('order_from', $data['order_item']['value'])) {
                $content .= '订单来源：' . $order_info['order_from_name'] . "\n";
            }

            // 订单状态
            if (in_array('order_status', $data['order_item']['value'])) {
                $content .= '订单状态：' . $order_info['status_name'] . "\n";
            }

            // 支付方式
            if (in_array('payment_type', $data['order_item']['value']) && !empty($order_info['pay'])) {
                $content .= '支付方式：' . $order_info['pay']['type_name'] . "\n";
            }

            // 回收方式
            if (in_array('recycle_type', $data['order_item']['value'])) {
                $content .= '回收方式：' . $order_info['recycle_type_name'] . "\n";
            }

            // 创建时间
            if (in_array('create_time', $data['order_item']['value'])) {
                $content .= '下单时间：' . date('Y-m-d H:i:s', $order_info['create_time']) . "\n";
            }

            // 支付时间
            if (in_array('pay_time', $data['order_item']['value']) && !empty($order_info['pay_time'])) {
                $content .= '完成时间：' . date('Y-m-d H:i:s', $order_info['pay_time']) . "\n";
            }

            // 回收金额
            if (in_array('recycle_money', $data['order_item']['value'])) {
                $content .= '回收金额：￥' . $order_info['recycle_money'] . "\n";
            }

            // 优惠金额
            if (in_array('discount_money', $data['order_item']['value'])) {
                $content .= '优惠金额：￥' . $order_info['discount_money'] . "\n";
            }
        }

        // 实付金额
        if ($data['order_money']['status'] == 1) {
            // 文字大小
            if ($data['order_money']['fontSize'] == 'big') {
                $content .= "<FH2>";
            }

            // 文字加粗
            if ($data['order_money']['fontWeight'] == 'bold') {
                $content .= "<FB>";
            }

            $content .= '实付金额：￥' . $order_info['order_money'] . "\n";

            if ($data['order_money']['fontWeight'] == 'bold') {
                $content .= "</FB>";
            }

            // 文字大小
            if ($data['order_money']['fontSize'] == 'big') {
                $content .= "</FH2>";
            }
        }

        // 管理员备注
        if ($data['admin_remark']['status'] == 1 && !empty($order_info['admin_remark'])) {
            // 文字大小
            if ($data['admin_remark']['fontSize'] == 'big') {
                $content .= "<FH2>";
            }

            // 文字加粗
            if ($data['admin_remark']['fontWeight'] == 'bold') {
                $content .= "<FB>";
            }

            $content .= '管理员备注：' . $order_info['admin_remark'] . "\n";

            if ($data['admin_remark']['fontWeight'] == 'bold') {
                $content .= "</FB>";
            }

            // 文字大小
            if ($data['admin_remark']['fontSize'] == 'big') {
                $content .= "</FH2>";
            }
        }

        if (!empty($content)) {
            $content .= str_repeat('.', 32);
        }

        return $content;
    }

    /**
     * 获取物品信息内容
     * @param $data
     * @param $order_info
     * @return string
     */
    private function getGoodsInfoContent($data, $order_info)
    {
        $content = '';

        if (!empty($order_info['order_items'])) {
            // 表头
            $content .= str_pad('物品名称', 20, ' ', STR_PAD_RIGHT);
            $content .= str_pad('数量', 5, ' ', STR_PAD_LEFT);
            
            if ($data['goods_price']['status'] == 1) {
                $content .= str_pad('金额', 10, ' ', STR_PAD_LEFT);
            }
            
            $content .= "\n";
            $content .= str_repeat('.', 32) . "\n";

            // 物品明细
            foreach ($order_info['order_items'] as $item) {
                // 物品名称
                if ($data['goods_name']['status'] == 1) {
                    // 文字大小
                    if ($data['goods_name']['fontSize'] == 'big') {
                        $content .= "<FH2>";
                    }

                    // 文字加粗
                    if ($data['goods_name']['fontWeight'] == 'bold') {
                        $content .= "<FB>";
                    }

                    // 限制名称长度，防止超出打印宽度
                    $name = mb_strlen($item['name']) > 10 ? mb_substr($item['name'], 0, 8) . '..' : $item['name'];
                    $content .= str_pad($name, 20, ' ', STR_PAD_RIGHT);

                    if ($data['goods_name']['fontWeight'] == 'bold') {
                        $content .= "</FB>";
                    }

                    if ($data['goods_name']['fontSize'] == 'big') {
                        $content .= "</FH2>";
                    }
                }

                // 物品数量
                if ($data['goods_num']['status'] == 1) {
                    $content .= str_pad($item['num'], 5, ' ', STR_PAD_LEFT);
                }

                // 物品金额
                if ($data['goods_price']['status'] == 1) {
                    // 文字大小
                    if ($data['goods_price']['fontSize'] == 'big') {
                        $content .= "<FH2>";
                    }

                    // 文字加粗
                    if ($data['goods_price']['fontWeight'] == 'bold') {
                        $content .= "<FB>";
                    }

                    $content .= str_pad($item['total_price'], 10, ' ', STR_PAD_LEFT);

                    if ($data['goods_price']['fontWeight'] == 'bold') {
                        $content .= "</FB>";
                    }

                    if ($data['goods_price']['fontSize'] == 'big') {
                        $content .= "</FH2>";
                    }
                }

                $content .= "\n";
            }

            $content .= str_repeat('.', 32) . "\n";
        }

        return $content;
    }

    /**
     * 获取用户/回收信息内容
     * @param $data
     * @param $order_info
     * @return string
     */
    private function getUserInfoContent($data, $order_info)
    {
        $content = '';

        // 会员基础信息
        if ($data['member_basic_info']['status'] == 1 && !empty($data['member_basic_info']['value']) && !empty($order_info['member'])) {
            // 买家昵称
            if (in_array('nickname', $data['member_basic_info']['value'])) {
                $content .= '用户昵称：' . $order_info['member']['nickname'] . "\n";
            }

            // 账户余额
            if (in_array('account_balance', $data['member_basic_info']['value'])) {
                $content .= '账户余额：￥' . $order_info['member']['balance'] . "\n";
            }

            // 账户积分
            if (in_array('account_point', $data['member_basic_info']['value'])) {
                $content .= '账户积分：' . $order_info['member']['point'] . "\n";
            }
        }

        // 会员手机号
        if ($data['user_mobile']['status'] == 1 && !empty($order_info['member']['mobile'])) {
            // 文字大小
            if ($data['user_mobile']['fontSize'] == 'big') {
                $content .= "<FH2>";
            }

            // 文字加粗
            if ($data['user_mobile']['fontWeight'] == 'bold') {
                $content .= "<FB>";
            }

            // 手机号脱敏处理
            $mobile = $order_info['member']['mobile'];
            if ($data['user_mobile']['value'] == 'yes') {
                $mobile = substr($mobile, 0, 3) . '****' . substr($mobile, -4);
            }

            $content .= '用户手机号：' . $mobile . "\n";

            if ($data['user_mobile']['fontWeight'] == 'bold') {
                $content .= "</FB>";
            }

            // 文字大小
            if ($data['user_mobile']['fontSize'] == 'big') {
                $content .= "</FH2>";
            }
        }

        if (!empty($content)) {
            $content .= str_repeat('.', 32) . "\n";
        }

        // 联系人姓名
        if ($data['contact_name']['status'] == 1 && !empty($order_info['contact_name'])) {
            // 文字大小
            if ($data['contact_name']['fontSize'] == 'big') {
                $content .= "<FH2>";
            }

            // 文字加粗
            if ($data['contact_name']['fontWeight'] == 'bold') {
                $content .= "<FB>";
            }

            $content .= '联系人：' . $order_info['contact_name'] . "\n";

            if ($data['contact_name']['fontWeight'] == 'bold') {
                $content .= "</FB>";
            }

            // 文字大小
            if ($data['contact_name']['fontSize'] == 'big') {
                $content .= "</FH2>";
            }
        }

        // 联系人手机号
        if ($data['contact_mobile']['status'] == 1 && !empty($order_info['contact_mobile'])) {
            // 文字大小
            if ($data['contact_mobile']['fontSize'] == 'big') {
                $content .= "<FH2>";
            }

            // 文字加粗
            if ($data['contact_mobile']['fontWeight'] == 'bold') {
                $content .= "<FB>";
            }

            // 手机号脱敏处理
            $mobile = $order_info['contact_mobile'];
            if ($data['contact_mobile']['value'] == 'yes') {
                $mobile = substr($mobile, 0, 3) . '****' . substr($mobile, -4);
            }

            $content .= '联系方式：' . $mobile . "\n";

            if ($data['contact_mobile']['fontWeight'] == 'bold') {
                $content .= "</FB>";
            }

            // 文字大小
            if ($data['contact_mobile']['fontSize'] == 'big') {
                $content .= "</FH2>";
            }
        }

        // 回收地址
        if ($data['recycle_address']['status'] == 1 && !empty($order_info['full_address'])) {
            // 文字大小
            if ($data['recycle_address']['fontSize'] == 'big') {
                $content .= "<FH2>";
            }

            // 文字加粗
            if ($data['recycle_address']['fontWeight'] == 'bold') {
                $content .= "<FB>";
            }

            $content .= '回收地址：' . $order_info['full_address'] . "\n";

            if ($data['recycle_address']['fontWeight'] == 'bold') {
                $content .= "</FB>";
            }

            // 文字大小
            if ($data['recycle_address']['fontSize'] == 'big') {
                $content .= "</FH2>";
            }
        }

        // 用户备注
        if ($data['user_remark']['status'] == 1 && !empty($order_info['user_remark'])) {
            // 文字大小
            if ($data['user_remark']['fontSize'] == 'big') {
                $content .= "<FH2>";
            }

            // 文字加粗
            if ($data['user_remark']['fontWeight'] == 'bold') {
                $content .= "<FB>";
            }

            $content .= '用户备注：' . $order_info['user_remark'] . "\n";

            if ($data['user_remark']['fontWeight'] == 'bold') {
                $content .= "</FB>";
            }

            // 文字大小
            if ($data['user_remark']['fontSize'] == 'big') {
                $content .= "</FH2>";
            }
        }

        if (!empty($content)) {
            $content .= str_repeat('.', 32) . "\n";
        }

        return $content;
    }

    /**
     * 获取底部信息内容
     * @param $data
     * @return string
     */
    private function getBottomInfoContent($data)
    {
        $content = '';

        // 二维码
        if ($data['qrcode']['status'] == 1) {
            $content .= "<QR>https://www.niucloud.com</QR>\n";
        }

        // 打印时间
        if ($data['ticket_print_time']['status'] == 1) {
            $content .= "<center>";
            $content .= "打印时间：" . date('Y-m-d H:i:s');
            $content .= "</center>\n";
        }

        // 底部备注
        if ($data['bottom_remark']['status'] == 1 && !empty($data['bottom_remark']['value'])) {
            $content .= "<center>";
            $content .= $data['bottom_remark']['value'];
            $content .= "</center>\n";
        }

        return $content;
    }
} 