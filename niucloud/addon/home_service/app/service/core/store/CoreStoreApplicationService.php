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


use addon\home_service\app\dict\store\StoreDict;
use addon\home_service\app\dict\technician\TechnicianDict;
use addon\home_service\app\model\store\StoreApplication;
use core\base\BaseCoreService;
use core\exception\CommonException;


/**
 * 门店入驻服务层
 */
class CoreStoreApplicationService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreApplication();
    }


    /**
     * 提交门店申请
     * @param array $data 申请数据
     * @return bool
     * @throws CommonException 当用户已是师傅时抛出异常
     */
    public function apply(array $data)
    {
      
        $data['create_time'] = time();
        // 无历史申请，创建新申请
        $this->model->create($data);
        return true;
    }


    /**
     * 更新申请信息
     * @param int $id 申请id
     * @param array $data 待更新数据
     * @return bool|int 受影响的行数
     */
    public function update($id, $data)
    {
        $data['audit_status'] = StoreDict::PENDING_EXAMINE;
        $data['audit_time'] = 0;
        return $this->model->where([
            ['site_id', '=', $data['site_id']],
            ['id', '=', $id],
        ])->update($data);
    }


}
