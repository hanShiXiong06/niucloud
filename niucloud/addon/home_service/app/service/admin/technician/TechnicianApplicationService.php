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

namespace addon\home_service\app\service\admin\technician;

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\model\technician\TechnicianApplication;
use core\base\BaseAdminService;
use core\exception\CommonException;
use addon\home_service\app\dict\technician\TechnicianDict;
use think\facade\Db;

/**
 * 师傅入驻服务层
 * Class TechnicianService
 * @package app\service\admin\technician
 */
class TechnicianApplicationService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new TechnicianApplication();
    }

    /**
     * 获取师傅入驻列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,real_name,id_card_front,id_card_back,id_number,mobile, certificate,notes,full_address,store_id,category_id,
         audit_status,audit_user_id,audit_remark,create_time,member_id,site_id,headimg,province_id,city_id';
        $order = 'technician_application.create_time desc';  // 排序也用实际别名
        $with_where = [];
        if (!empty($where['nickname'])) $with_where = [['member.nickname|member.username', 'like', "%" . $where['nickname'] . "%"],];
        if (!empty($where['mobile'])) $with_where[] = ['technician_application.mobile', 'like', "%" . $where['mobile'] . "%"];
        $search_model = $this->model
            // 条件用实际别名
            ->where([['technician_application.site_id', '=', $this->site_id]])
            ->field($field)
            ->withSearch(["real_name", "audit_status", "create_time"], $where)
            ->withJoin([
                'member' => ['nickname', 'headimg', 'username'],
                'province' => ['id', 'name', 'level'],
                'city' => ['id', 'name', 'level'],
            ])
            ->with([
                'store' => function ($query) {
                    $query->field('store_id,store_name,contact_name, mobile');
                },
                'sysUser' => function ($query) {
                    $query->field('username,uid');
                }
            ])
            ->where($with_where)
            ->order($order)
            ->append(['audit_status_name', 'category_name']);
        $list = $this->pageQuery($search_model);


        return $list;
    }


    /**
     * 获取师傅入驻申请信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,real_name,id_card_front,id_card_back,id_number,mobile, certificate,notes,full_address,store_id,category_id,
         audit_status,audit_user_id,audit_remark,create_time,member_id,site_id,province_id,city_id,district_id,full_address,lng,lat,headimg';
        $info = $this->model
            ->field($field)
            ->where([['id', '=', $id], ['technician_application.site_id', '=', $this->site_id]])
            ->withJoin([
                'member' => ['nickname', 'headimg', 'username']
            ])
            ->with([
                'store' => function ($query) {
                    $query->field('store_id,store_name,contact_name, mobile');
                },
                'sysUser' => function ($query) {
                    $query->field('username,uid');
                }
            ])
            ->append(['audit_status_name', 'category_name'])->findOrEmpty()->toArray();
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
            throw new CommonException('HOME_SERVICE_TECHNICIAN_NO_NEED_APPLY');
        } elseif ($existingApplicationInfo['audit_status'] == TechnicianDict::PASS) {
            throw new CommonException('HOME_SERVICE_YOU_ARE_ALREADY_TECHNICIAN_NO_NEED');
        }
        Db::startTrans();
        try {
            $data['audit_user_id'] = $this->uid;
            $data['audit_time'] = time();
            $this->update($data, $id);
            if ($data['audit_status'] == TechnicianDict::PASS) {
                $data['source'] = 'application';
                $technician_id = (new  TechnicianService)->add($data);
                event('NotificationEvent', [
                    'identity' => [NoticeDict::TECHNICIAN],
                    'type' => NoticeDict::AUDIT_PASS,
                    'notice_source' => NoticeDict::SYSTEM,
                    'order_id' => 0,
                    'technician_id' => $technician_id,
                    'member_id' => 0,
                    'site_id' => $this->site_id,
                ]);
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
