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

namespace addon\home_service\app\service\api\card;

use addon\home_service\app\dict\card\MemberCardDict;
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\model\card\MemberCard;
use addon\home_service\app\model\card\MemberCardItem;
use addon\home_service\app\model\order\Order;
use core\base\BaseApiService;


/**
 * 会员次卡服务层
 * Class MemberCardService
 * @package addon\home_service\app\service\api\card
 */
class MemberCardService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new MemberCard();
    }

    /**
     * 订单状态
     * @return array|array[]|string
     */
    public function getStatus()
    {
        return array_values(array_map(function ($item) {
            return ['name' => $item['name'], 'status' => $item['status']];
        }, MemberCardDict::getStatus()));
    }

    /**
     * 获取次卡套餐列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,card_id,card_no,total_num,total_use_num,status,expire_time';

        $order = 'create_time desc';

        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])
            ->withSearch(["status"], $where)
            ->with(['card'])
            ->field($field)
            ->order($order)
            ->append(['status_name']);
        return $this->pageQuery($search_model,function($item){
            $item['expire_time'] = $item['expire_time'] ? date('Y-m-d H:i:s', $item['expire_time']) : '永久';
        });
    }

    /**
     * 会员次卡项目列表
     * @param array $where
     * @return array
     */
    public function getItem(array $where)
    {
        $search_model = (new MemberCardItem())
            ->where([['member_card_id', '=', $where['member_card_id']], ['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])
            ->with([
                'goods'=>function($query){
                    $query->field('goods_id,site_id,goods_name,goods_cover,goods_image')->append([ 'goods_cover_thumb_mid' ]);
                },'goodsSku'
            ]);
        return $this->pageQuery($search_model,function($item){
            $item['expire_time'] = $item['expire_time'] ? date('Y-m-d H:i:s', $item['expire_time']) : '永久';
        });
    }

    /**
     * 会员次卡使用记录
     * @param array $where
     * @return array
     */
    public function getCardUseRecords($where)
    {
        $info = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $where['member_card_id']],
            ['member_id', '=', $this->member_id]
        ])->with([
            'card' => function($query){
                $query->field('site_id,card_id,card_name,card_cover,price,card_image')->append(['card_cover_thumb_small']);
            },
            'useRecords' => function($query){
                $query->with([
                    'order'=> function($query1){
                        $query1->withTrashed()->field('site_id,order_id,order_no,order_name,order_status,reserve_service_time_stamp,technician_id,refund_status')->append(['order_status_info', 'technician_name']);
                    }
                ]);
            }
        ])->findOrEmpty()->toArray();
        if(!empty($info)){
            $info['expire_time'] = $info['expire_time'] ? date('Y-m-d H:i:s', $info['expire_time']) : '永久';
        }

        if (isset($info['useRecords'])){
            foreach ($info['useRecords'] as $key=>$value){
                if (!empty($value['order'])){
                    $info['useRecords'][$key]['order']['reserve_service_time']  = Order::formatTime($value['order']['reserve_service_time_stamp']);
                    $is_refund = false;
                    if (!empty($value['order']['refund_status']) && $value['order']['refund_status'] == RefundDict::REFUND_COMPLETED){
                        $is_refund = true;
                    }
                    $info['useRecords'][$key]['order']['is_refund'] = $is_refund;
                }
            }
        }
        return $info;
    }
}
