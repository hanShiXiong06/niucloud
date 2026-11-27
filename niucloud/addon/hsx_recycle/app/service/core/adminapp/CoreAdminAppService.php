<?php

namespace addon\hsx_recycle\app\service\core\adminapp;

use addon\hsx_recycle\app\model\adminapp\SysAdminapp;
use core\base\BaseCoreService;

/**
 * 手机管理端用户偏好配置。
 */
class CoreAdminAppService extends BaseCoreService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new SysAdminapp();
    }

    public function getValue(int $uid, int $site_id, string $type)
    {
        return $this->model->where([
            ['uid', '=', $uid],
            ['site_id', '=', $site_id],
            ['type', '=', $type],
        ])->findOrEmpty()['value'] ?? [];
    }

    public function setValue(int $uid, int $site_id, string $type, array $value)
    {
        $adminapp = $this->model->where([
            ['uid', '=', $uid],
            ['site_id', '=', $site_id],
            ['type', '=', $type],
        ])->findOrEmpty();

        if ($adminapp->isEmpty()) {
            $this->model->create([
                'uid' => $uid,
                'site_id' => $site_id,
                'type' => $type,
                'value' => $value,
            ]);
        } else {
            $adminapp->save([
                'value' => $value,
            ]);
        }

        return true;
    }
}
