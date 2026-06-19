<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpRolePresetService;
use core\base\BaseAdminController;

/**
 * 推荐角色「一键生成」。不入侵框架，仅调用框架 RoleService 写 sys_role。
 */
class RolePreset extends BaseAdminController
{
    /** 预览推荐角色与权限点数量、是否已存在 */
    public function preview()
    {
        return success((new ErpRolePresetService())->preview());
    }

    /** 一键生成/更新推荐角色 */
    public function generate()
    {
        $overwrite = (int)$this->request->param('overwrite', 1) === 1;
        return success((new ErpRolePresetService())->generate($overwrite));
    }
}
