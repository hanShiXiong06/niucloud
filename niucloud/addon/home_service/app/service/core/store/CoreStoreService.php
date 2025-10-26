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

namespace addon\home_service\app\service\core\store;


use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\service\admin\store\TechnicianService as StoreTechnicianService;
use core\base\BaseCoreService;
use addon\home_service\app\model\store\Store;
use core\exception\CommonException;
use think\facade\Db;


/**
 * 门店服务层
 */
class CoreStoreService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Store();
    }


    /**
     * 获取门店列表
     * @param array $where
     * @return array
     */
    public function getStoreList(array $where = [])
    {
        $field = 'is_default,member_id,store_id,store_name,mobile,headimg,contact_name,site_id,province_id,city_id,district_id,full_address,lng,lat,business_hours';
        $order = 'create_time desc';
        $store_list = $this->model
            ->withSearch(["store_name", "create_time", "site_id", "member_id"], $where)
            ->field($field)->storeDistance($where['lat'] ?? 0, $where['lng'] ?? 0)->order($order)->append([])->select()->toArray();

        $order_model = new Order();
        $today_start = strtotime(date('Y-m-d 00:00:00'));
        $month_start = strtotime(date('Y-m-01 00:00:00'));
        $month_end = strtotime(date('Y-m-01 00:00:00', strtotime('+1 month')));
        foreach ($store_list as &$value) {
            $order_stat = $order_model->field([
                "SUM(CASE WHEN is_abnormal = 1 AND refund_status = '' THEN 1 ELSE 0 END) AS abnormal_count",
                "SUM(CASE WHEN refund_status != '' THEN 1 ELSE 0 END) AS refund_count",
                "SUM(CASE WHEN order_status = '" . OrderDict::WAIT_DISPATCH . "' THEN 1 ELSE 0 END) AS wait_service_count",
                "SUM(CASE WHEN create_time >= {$today_start} THEN 1 ELSE 0 END) AS today_count",
                "SUM(CASE WHEN order_status = '" . OrderDict::FINISH . "' AND create_time >= {$month_start} AND create_time < {$month_end} THEN store_commission ELSE 0 END) AS month_income"
            ])->where([
                ['site_id', '=', $where['site_id']],
                ['store_id', '=', $value['store_id']],
            ])->find();

            $value['wait_service_count'] = $order_stat['wait_service_count'] ?? 0;
            $value['abnormal_count'] = $order_stat['abnormal_count'] ?? 0;
            $value['refund_count'] = $order_stat['refund_count'] ?? 0;
            $value['today_count'] = $order_stat['today_count'] ?? 0;
            $value['month_income'] = isset($order_stat['month_income']) ? number_format($order_stat['month_income'], 2, '.', '') : '0';

        }
        return $store_list;
    }

    /**
     * 编辑门店联系人信息
     * @param int $store_id
     * @param array $data
     * @return bool
     */
    public function editContact($store_id, $data)
    {
        $this->model->where([['store_id', '=', $store_id]])->update(['contact_name' => $data['contact_name'], 'mobile' => $data['mobile']]);
        return true;
    }

    /**
     * 获取门店信息
     * @param int $store_id
     * @return array
     */
    public function getInfo(int $store_id)
    {
        $field = 'evaluate_avg_scores,business_hours,commission,service_ratio,site_id,store_id,member_id,store_name,contact_name,mobile,order_num,achievement,service_time,create_time,headimg,id_card_front,id_card_back,id_number,license_img,province_id,city_id,district_id,full_address,lng,lat';
        $info = $this->model->where([['store_id', '=', $store_id]])
            ->with(
                [
                    'member' => function ($query) {
                        $query->field('member_id,mobile,nickname, headimg');
                    },
                ]
            )->field($field)->append(['headimg_mid'])
            ->findOrEmpty()->toArray();
        $info['technician_count'] = (new StoreTechnicianService)->getCount(['store_id' => $store_id]);
        return $info;
    }

    /**
     * 切换门店
     * @param int $current_store_id
     * @param int $store_id
     * @param int $site_id
     * @param int $member_id
     * @return bool
     */
    public function storeSwitch($current_store_id, $store_id, $site_id, $member_id)
    {
        if ($current_store_id != $store_id) {
            Db::startTrans();
            try {
                $this->model->where([['site_id', '=', $site_id], ['store_id', '=', $current_store_id]])->update(['is_default' => 0]);
                $this->model->where([['site_id', '=', $site_id], ['store_id', '=', $store_id], ['member_id', '=', $member_id]])->update(['is_default' => 1]);
                Db::commit();
                return true;
            } catch (\Exception $e) {
                Db::rollback();
                throw new CommonException($e->getMessage());
            }
        }
    }


}
