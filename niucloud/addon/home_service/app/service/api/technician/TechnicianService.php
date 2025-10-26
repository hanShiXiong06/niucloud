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

namespace addon\home_service\app\service\api\technician;

use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\technician\Technician;
use core\base\BaseApiService;
use core\exception\ApiException;
use core\exception\CommonException;

/**
 * 师傅服务层
 * Class TechnicianService
 * @package app\service\api\technician
 */
class TechnicianService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Technician();
    }


    /**
     * 验证是否是师傅
     * @return array
     */
    public function checkTechnician()
    {
        $field = 'id';
        $order = 'create_time desc';
        $info = $this->model->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])->field($field)->order($order)->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 获取师傅信息
     * @return array
     */
    public function getInfo()
    {
        $field = 'id,real_name,mobile,status,member_id,headimg,certificate,province_id,city_id,
        district_id,full_address,lng,lat,store_id,category_id,level_id,source,site_id,create_time,distribute_type,order_rate,commission,certificate,intro,order_num,positive_rate';
        $info = $this->model->where([['member_id', '=', $this->member_id], ['site_id', '=', $this->site_id]])
            ->with(
                [
                    'member' => function ($query) {
                        $query->field('member_id,mobile,nickname, headimg');
                    },
                    'store' => function ($query) {
                        $query->field('store_id,contact_name, mobile');
                    },
                    'level' => function ($query) {
                        $query->field('level_id,level_name, order_rate');
                    },
                ]
            )->field($field)->append(['distribute_name', 'headimg_mid', 'status_name', 'category_name'])
            ->findOrEmpty()->toArray();
        if (empty($info)) return $info;
        $order_stat = (new Order())->field([
            "SUM(CASE WHEN is_abnormal = 1 THEN 1 ELSE 0 END) AS abnormal_count",
            "SUM(CASE WHEN refund_status != '' THEN 1 ELSE 0 END) AS refund_count"
        ])->where([
            ['site_id', '=', $this->site_id],
            ['technician_id', '=', $info['id']],
        ])->find();

        $info['certificate'] = !empty($info['certificate']) ? explode(',', $info['certificate']) : [];
        $info['order_count'] = $info['order_num'] ?? 0;
        $info['abnormal_count'] = $order_stat['abnormal_count'] ?? 0;
        $info['refund_count'] = $order_stat['refund_count'] ?? 0;
        $info['positive_rate'] = isset($info['positive_rate']) ? round($info['positive_rate'], 1) . '%' : '0%';

        return $info;

    }


    /**
     * 修改
     * @param int $site_id
     * @param int $member_id
     * @param string $field
     * @param $data
     * @return Member
     */
    public function modify(string $field, $data)
    {
        $field_name = match ($field) {
            'headimg' => 'headimg',
            'status' => 'status',
        };
        $where = array(
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
        );
        return $this->model->where($where)->update([$field_name => $data]);
    }


    /**
     * 批量修改
     * @param array
     * @return bool
     */
    public function edit(array $data)
    {
        $technician = $this->findTechnicianInfo();
        if ($technician->isEmpty()) throw new ApiException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');
        $technician->allowField(['headimg', 'intro', 'mobile', 'real_name'])->save($data);
        return true;
    }


    /**
     * 获取师傅模型对象
     * @param array $data
     * @return Member|array|mixed|Model  !!! 仔细看,返回值是模型对象  如果想要判断是否为空  请用 $member->isEmpty()
     */
    public function findTechnicianInfo()
    {
        $where[] = ['site_id', '=', $this->site_id];
        $where[] = ['member_id', '=', $this->member_id];
        return $this->model->where($where)->findOrEmpty();
    }


    /**
     * 师傅切换门店
     * @param $data
     * @return bool
     */
    public function switchStore($data)
    {
        $technician_info = $this->checkTechnician();
        if (empty($technician_info)) throw new CommonException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');
        $this->model->where([['id', '=', $technician_info['id']]])->update(['store_id' => $data['store_id']]);
        return true;
    }

}
