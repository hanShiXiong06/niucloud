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

use addon\home_service\app\dict\card\CardOrderDict;
use addon\home_service\app\model\card\CardOrder;
use addon\home_service\app\service\core\card\CoreCardOrderCreateService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 订单服务层
 * Class OrderService
 * @package app\service\api\order
 */
class CardOrderCreateService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new CardOrder();
    }

    /**
     * 订单创建
     * @param array $data
     * @return array|null
     */
    public function create(array $data) {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $data['order_from'] = $this->channel;
        return (new CoreCardOrderCreateService())->create($data);
    }


    /**
     * 删除订单
     * @param int $order_id
     * @return true
     */
    public function delete(int $order_id) {
        $order = $this->model->where([ ['order_id', '=', $order_id ], ['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id ] ])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order['order_status'] != OrderDict::CLOSE ) throw new CommonException('ORDER_NOT_ALLOW_DELETE');
        $order->delete();
        return true;
    }

    /**
     * 订单状态
     * @return array|array[]|string
     */
    public function getStatus()
    {
        return array_map(function ($item) {
            return [ 'name' => $item['name'], 'status' => $item['status'] ];
        }, CardOrderDict::getStatus());
    }
}
