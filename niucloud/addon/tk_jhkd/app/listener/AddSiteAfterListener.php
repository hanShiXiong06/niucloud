<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\listener;
use app\service\core\poster\CorePosterService;
use think\facade\Log;

/**
 * 站点创建之后
 */
class AddSiteAfterListener
{
    public function handle($params = [])
    {
        if (in_array('tk_jhkd', $params[ 'main_app' ])) {

            $site_id = $params[ 'site_id' ];
            request()->siteId($site_id);
            $poster = new CorePosterService();
            // 创建默认海报
            $poster_template = $poster->getTemplateList('tk_jhkd', 'tk_jhkd_poster')[ 0 ];
            $poster->add($site_id, 'tk_jhkd', [
                'name' => $poster_template[ 'name' ],
                'type' => $poster_template[ 'type' ],
                'value' => $poster_template[ 'data' ],
                'status' => 1,
                'is_default' => 1
            ]);

            return true;
        }
    }
}
