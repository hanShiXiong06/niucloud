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

namespace addon\home_service\app\service\admin\help_feedback;

use addon\home_service\app\model\goods\GoodsCategory;
use addon\home_service\app\model\help_feedback\Feedback;
use addon\home_service\app\model\help_feedback\Help;
use addon\home_service\app\model\help_feedback\HelpCategory;
use core\base\BaseAdminService;
use think\facade\Db;


/**
 *  反馈服务层
 * Class FeedbackService
 * @package app\service\admin\feedback
 */
class FeedbackService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Feedback();
    }

    /**
     * 获取反馈列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'feedback_id,site_id,source,title,images,content,related_id';
        $order = 'create_time desc';
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch(["title",'source'], $where)->field($field)->order($order)->append(['source_name','related_name','images_thumb_mid']);
        $list = $this->pageQuery($search_model,function($item){
            $item['images'] = !empty($item['images']) ?  explode(',',$item['images']) : [];
        });
        return $list;
    }


}
