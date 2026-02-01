<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\fast_pay\app\listener;
use app\service\admin\site\SiteService;
use app\service\core\poster\CorePosterService;
use addon\business_agent\app\model\projectuser\ProjectUser;
use think\facade\Log;

/**
 * 站点创建之后
 */
class AddSiteAfterListener
{
    public function handle($params = [])
    {
        if (in_array('fast_pay', $params[ 'site_addons' ])) {
            $site_id = $params[ 'site_id' ];
            request()->siteId($site_id);
            $poster = new CorePosterService();
            // 创建默收款码海报
            $bwc_template = $poster->getTemplateList('fast_pay', 'fast_pay_poster')[ 0 ];
            $poster->add($site_id, 'business_agent', [
                'name' => $bwc_template[ 'name' ],
                'type' => $bwc_template[ 'type' ],
                'value' => $bwc_template[ 'data' ],
                'status' => 1,
                'is_default' => 1
            ]);
            return true;
        }
       return true;
    }
}
