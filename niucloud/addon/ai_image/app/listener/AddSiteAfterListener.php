<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\ai_image\app\listener;
use addon\ai_image\app\dict\model\ModelDict;
use addon\ai_image\app\model\aiimagemodel\AiimageModel;
use app\service\core\poster\CorePosterService;

/**
 * 站点创建之后
 */
class AddSiteAfterListener
{
    public function handle($params = [])
    {
        if (in_array('ai_image', $params['main_app'])) {
            $this->site_id = $params['site_id'];
            $this->model = new AiimageModel();
            $base_list = ModelDict::getModelDict();
            foreach ($base_list as $v) {
                $info = $this->model->where([['key', '=', $v['key']], ['site_id', '=', $this->site_id]])->findOrEmpty();
                if ($info->isEmpty()) {
                    $v['site_id'] = $this->site_id;
                    $this->model->create($v);
                }
            }
            return true;
        }
    }
}
