<?php

namespace addon\hsx_recycle\app\model\adminapp;

use core\base\BaseModel;

/**
 * 手机管理端偏好配置模型。
 */
class SysAdminapp extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'sys_adminapp';

    protected $json = ['value'];

    protected $jsonAssoc = true;
}
