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

namespace addon\home_service\app\service\admin\store;

use addon\home_service\app\model\store\Store;
use addon\home_service\app\service\admin\store\TechnicianService as StoreTechnicianService;
use addon\home_service\app\service\core\statistics\CoreOrderService;
use core\base\BaseAdminService;
use addon\home_service\app\service\core\store\CoreStoreService;
use addon\home_service\app\service\admin\statistics\StoreAccountService;
use core\exception\AdminException;
use think\facade\Db;

/**
 * 门店服务层
 * Class TechnicianService
 * @package app\service\admin\technician
 */
class StoreService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Store();
    }


    /**
     * 门店列表
     * @param array $where
     * @return mixed
     */
    public function getList($where)
    {
        $where['site_id'] = $this->site_id;
        return (new CoreStoreService)->getStoreList($where);
    }


    /**
     * 门店分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage($where)
    {
        $field = 'commission,service_ratio,full_address,site_id,store_id,member_id,store_name,contact_name,mobile,order_num,achievement,service_time,create_time,headimg,id_card_front,id_card_back,id_number,license_img';
        $order = 'store.create_time desc';
        $with_where = [];
        if (!empty($where['nickname'])) $with_where = [['member.nickname|member.username', 'like', "%" . $where['nickname'] . "%"],];
        if (!empty($where['mobile'])) $with_where[] = ['store.mobile', 'like', "%" . $where['mobile'] . "%"];
        $search_model = $this->model
            // 条件用实际别名
            ->where([['store.site_id', '=', $this->site_id]])
            ->field($field)
            ->withSearch(["store_name", "contact_name", "create_time"], $where)
            ->withJoin([
                'member' => ['nickname', 'headimg', 'username'],
            ])
            ->where($with_where)
            ->order($order)->append(['headimg_mid']);
        $list = $this->pageQuery($search_model);
        $this->getStatistics($list);
        return $list;
    }


    /**
     *  获取统计数据
     * @param array $where
     * @return array
     */
    public function getStatistics(&$list)
    {
        $StoreIds = array_column($list['data'], 'store_id');
        $timeRange = [
            'start' => strtotime(date('Y-m-01 00:00:00')),
            'end' => strtotime(date('Y-m-t 23:59:59'))
        ];
        $orderTimeStatsMap = (new CoreOrderService)->batchGetStats($this->site_id, 'store_id', $StoreIds, [], $timeRange['start'], $timeRange['end'], true);
        $account_stats = (new StoreAccountService)->batchGetStats($this->site_id, $StoreIds);
        foreach ($list['data'] as &$datum) {
            $storeId = $datum['store_id'];
            $datum['technician_count'] = (new StoreTechnicianService)->getCount(['store_id' => $storeId]);
            $datum['time_service_process_total_count'] = $orderTimeStatsMap[$storeId]['service_process_total_count'] ?? 0;
            $datum['time_total_order_money'] = $orderTimeStatsMap[$storeId]['total_order_money'] ?? 0;
            $datum['total_pending_settlement_money'] = $account_stats[$storeId]['total_pending_settlement_money'] ?? 0;
        }
        return $list;
    }

    /**
     * 获取门店信息
     * @param int $store_id
     * @return array
     */
    public function getInfo(int $store_id)
    {


        $field = 'lng,lat,service_ratio,evaluate_avg_scores,site_id,store_id,member_id,store_name,contact_name,mobile,order_num,achievement,service_time,create_time,headimg,id_card_front,id_card_back,id_number,license_img,province_id,city_id,district_id,full_address';
        $info = $this->model->where([['store_id', '=', $store_id], ['site_id', '=', $this->site_id]])
            ->with(
                [
                    'member' => function ($query) {
                        $query->field('member_id,mobile,nickname, headimg');
                    },
                ]
            )->field($field)->append(['headimg_mid','id_card_back_thumb_mid','id_card_font_thumb_mid','headimg_thumb_mid'])
            ->findOrEmpty()->toArray();
        return $info;
    }


    // 常量定义 - 门店必填字段
    const REQUIRED_FIELDS = [
        'store_name', 'contact_name', 'mobile', 'id_card_front',
        'id_card_back', 'license_img', 'province_id', 'city_id',
        'district_id', 'full_address', 'lng', 'lat', 'service_ratio'
    ];


    /**
     * 添加门店
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        // 补充基础数据
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        // 验证门店数据
        $this->validateStoreData($data);
        // 判断是否为会员的第一个门店，设置默认值
        $existingStoreCount = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $data['member_id'] ?? 0]  // 按“会员+站点”维度判断门店数量
        ])->count('store_id');
        if ($existingStoreCount === 0) {
            $data['is_default'] = 1;
        }
        // 处理默认值
        $data['headimg'] = $data['headimg'] ?? '';
        Db::startTrans();
        try {
            $res = $this->model->create($data);
            event("AddHsStore", $data);
            Db::commit();
            return $res->store_id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new AdminException($e->getMessage());
        }
    }


    /**
     * 门店编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        Db::startTrans();
        try {
            // 验证门店数据，传入当前ID作为排除项
            $this->validateStoreData($data, $id);
            // 执行更新
            $this->model->where([
                ['store_id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])->update($data);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new AdminException($e->getMessage());
        }
    }


    /**
     * 验证门店数据格式
     * @param array $data
     * @param int|null $excludeId 排除的ID，用于编辑时排除自身
     * @throws AdminException
     */
    private function validateStoreData(array $data, ?int $excludeId = null)
    {
        // 验证必填字段
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field]) || (empty($data[$field]) && $data[$field] !== 0)) {
                throw new AdminException("STORE_{$field}_REQUIRED");
            }
        }
        // 验证服务费率必须大于0
        if ((float)$data['service_ratio'] <= 0) {
            throw new AdminException('STORE_SERVICE_RATIO_MUST_BE_GREATER_THAN_ZERO');
        }
        // 验证手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $data['mobile'])) {
            throw new AdminException('STORE_MOBILE_FORMAT_ERROR');
        }
        // 验证经纬度格式
        if (!is_numeric($data['lng']) || !is_numeric($data['lat'])) {
            throw new AdminException('STORE_COORDINATE_FORMAT_ERROR');
        }
        // 验证ID类字段为整数
        $integerFields = ['province_id', 'city_id', 'district_id'];
        foreach ($integerFields as $field) {
            if (!is_numeric($data[$field]) || (int)$data[$field] != $data[$field] || (int)$data[$field] <= 0) {
                throw new AdminException("STORE_{$field}_FORMAT_ERROR");
            }
        }
        // 验证图片字段不为空（实际项目中可能需要验证文件格式和大小）
        $imageFields = ['id_card_front', 'id_card_back', 'license_img'];
        foreach ($imageFields as $field) {
            if (empty($data[$field])) {
                throw new AdminException("STORE_{$field}_REQUIRED");
            }
        }
    }


}
