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

use addon\home_service\app\model\store\StoreApplication;
use core\base\BaseAdminService;
use core\exception\CommonException;
use addon\home_service\app\dict\store\StoreDict;
use think\facade\Db;

/**
 * 门店入驻服务层
 * Class StoreService
 * @package app\service\admin\store
 */
class StoreApplicationService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreApplication();
    }

    /**
     * 获取门店入驻列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'store_name,headimg,audit_time,id,contact_name,id_card_front,id_card_back,id_number,mobile, license_img,apply_desc,full_address,
         audit_status,audit_user_id,audit_remark,create_time,member_id,site_id';
        $order = 'store_application.create_time desc';  // 排序也用实际别名
        $with_where = [];
        if (!empty($where['contact_name'])) $with_where = [['store_application.contact_name|store_application.mobile', 'like', "%" . $where['contact_name'] . "%"],];
        if (!empty($where['store_name'])) $with_where[] = ['store_application.store_name', 'like', "%" . $where['store_name'] . "%"];
        $search_model = $this->model
            // 条件用实际别名
            ->where([['store_application.site_id', '=', $this->site_id]])
            ->field($field)
            ->withSearch(["audit_status", "create_time"], $where)
            ->withJoin([
                'member' => ['nickname', 'headimg', 'username'],
            ])
            ->with([
                'sysUser' => function ($query) {
                    $query->field('username,uid');
                }
            ])
            ->where($with_where)
            ->order($order)
            ->append(['audit_status_name']);
        $list = $this->pageQuery($search_model);
        return $list;
    }


    /**
     * 获取门店入驻申请信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'audit_time,id,contact_name,store_name,id_card_front,id_card_back,id_number,mobile, license_img,apply_desc,full_address,
         audit_status,audit_user_id,audit_remark,create_time,member_id,site_id,province_id,city_id,district_id,full_address,lng,lat,headimg';
        $info = $this->model
            ->field($field)
            ->where([['id', '=', $id], ['store_application.site_id', '=', $this->site_id]])
            ->withJoin([
                'member' => ['nickname', 'headimg', 'username']
            ])
            ->with([
                'sysUser' => function ($query) {
                    $query->field('username,uid');
                }
            ])
            ->append(['audit_status_name'])->findOrEmpty()->toArray();
        return $info;
    }


    /**
     * 审核操作
     * @param int $id
     * @return array
     */
    public function examine($data, $id)
    {

        $existingApplicationInfo = $this->getInfo($id);
        if (empty($existingApplicationInfo)) {
            throw new CommonException('HOME_SERVICE_STORE_NO_NEED_APPLY');
        }
        if ($existingApplicationInfo['audit_status'] != StoreDict::PENDING_EXAMINE) {
            throw new CommonException('HOME_SERVICE_STORE_APPLY_IS_NOT_PENDING_EXAMINE');
        }
        Db::startTrans();
        try {
            $data['audit_user_id'] = $this->uid;
            $data['audit_time'] = time();
            // 保存service_ratio值并从$data中移除
            $serviceRatio = isset($data['service_ratio']) ? $data['service_ratio'] : null;
            unset($data['service_ratio']);
            // 执行更新操作（不含service_ratio）
            $this->update($data, $id);
            // 重新添加service_ratio到$data中
            if ($serviceRatio !== null) {
                $data['service_ratio'] = $serviceRatio;
            }
            if ($data['audit_status'] == StoreDict::PASS) {
                $data['source'] = 'application';
                (new  StoreService)->add($data);
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }

    }


    /**
     * 更新申请信息
     * @param array $data 待更新数据
     * @return bool|int 受影响的行数
     */
    public function update(array $data, $id = 0)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update($data);
    }


}
