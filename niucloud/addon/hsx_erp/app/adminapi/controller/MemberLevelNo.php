<?php
// +----------------------------------------------------------------------
// | 会员等级站内序号(level_no)只读接口:供会员价/同行价等"跨站统一口径"配置使用
// +----------------------------------------------------------------------

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\MemberLevelNoService;
use core\base\BaseAdminController;

class MemberLevelNo extends BaseAdminController
{
    /** 本站会员等级列表(含站内序号 level_no,按序号升序);顺带触发补号 */
    public function lists()
    {
        // 控制器无 $this->site_id 属性,站点 id 取自请求(与 BaseAdminService 同源)
        return success(MemberLevelNoService::levelsWithNo((int) $this->request->siteId()));
    }
}
