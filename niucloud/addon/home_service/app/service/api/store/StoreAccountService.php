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

namespace addon\home_service\app\service\api\store;

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\model\account\StoreAccount;
use core\base\BaseApiService;


/**
 * 门店账单服务层
 */
class StoreAccountService extends BaseApiService
{
    use StoreTrait;

    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreAccount();
        $this->checkStore();
    }

    /**
     * 日账单列表
     */
    public function getDayBillPage()
    {
        $start_time = strtotime(date('Y-m-d 00:00:00'));
        $end_time = strtotime(date('Y-m-d 23:59:59'));

        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['store_id', '=', $this->store_id],
            ['create_time', 'between', [$start_time,$end_time]],
        ];

        $search_model = $this->model
            ->field('id,account_data,memo,status,create_time,related_id,from_type')
            ->where($baseWhere)
            ->append(['status_name'])
            ->order('create_time desc');
        return $this->pageQuery($search_model,function($item){
            $item['show_time'] = date('m-d H:i:s',strtotime($item['create_time']));
        });
    }

    /**
     * 账单明细列表
     */
    public function getStoreAccountList($where)
    {
        $date = !empty($where['date']) ? $where['date'] : date('Y-m');
        $start_time = strtotime($date.'-01 00:00:00');
        $end_time   = strtotime($date . '-' . date('t', strtotime($date . '-01')) . ' 23:59:59');
        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['store_id', '=', $this->store_id],
            ['create_time', 'between', [$start_time,$end_time]],
        ];

        $list = $this->model
            ->field('id,account_data,memo,status,create_time')
            ->withSearch(['from_type','status'],$where)
            ->where($baseWhere)
            ->append(['status_name'])
            ->order('create_time desc')
            ->select()->toArray();
        if (!empty($list)){
            foreach ($list as &$value){
                $value['show_time'] = date('m-d H:i:s',strtotime($value['create_time']));
            }
        }
        return $list;

    }

}
