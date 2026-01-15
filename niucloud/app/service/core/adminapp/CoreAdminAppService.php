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

namespace app\service\core\adminapp;

use app\model\adminapp\SysAdminapp;
use core\base\BaseCoreService;
use core\dict\DictLoader;

/**
 * 手机管理端应用
 */
class CoreAdminAppService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new SysAdminapp();
    }

    /**
     * 获取所有手机管理端菜单应用分组
     * @return void
     */
    public function getValue(int $uid, int $site_id, string $type){
        return $this->model->where([
            ['uid', '=', $uid],
            ['site_id', '=', $site_id],
            ['type', '=', $type],
        ])->findOrEmpty()['value'] ?? [];
    }

    /**
     *  获取手机管理端应用
     * @return true
     */
    public function setValue(int $uid, int $site_id, string $type,  array $value){
        $adminapp = $this->model->where([
            ['uid', '=', $uid],
            ['site_id', '=', $site_id],
            ['type', '=', $type],
        ])->findOrEmpty();
        if($adminapp->isEmpty()){
            $this->model->create([
                'uid' => $uid,
                'site_id' => $site_id,
                'type' => $type,
                'value' => $value,
            ]);
        }else{
            $this->model->save([
                'value' => $value,
            ]);
        }
        return true;
    }



}
