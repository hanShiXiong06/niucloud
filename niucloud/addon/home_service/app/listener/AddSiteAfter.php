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

namespace addon\home_service\app\listener;

use addon\home_service\app\model\help_feedback\HelpCategory;
use addon\home_service\app\model\technician\TechnicianLevel;
use addon\home_service\app\model\goods\Guarantee;
use app\service\core\poster\CorePosterService;
use app\service\admin\diy\DiyService;

/**
 * 站点添加之后
 */
class AddSiteAfter
{
    public function handle($params = [])
    {
        if (in_array('home_service', $params['main_app'])) {
            $site_id = $params['site_id'];
            request()->siteId($site_id);

            // 创建默认秒杀海报
            $poster = new CorePosterService();
            $template = $poster->getTemplateList('home_service', 'home_service_goods')[0];
            $poster->add($site_id, 'home_service', [
                'name' => $template['name'],
                'type' => $template['type'],
                'value' => $template['data'],
                'status' => 1,
                'is_default' => 1
            ]);

            // 创建首页自定义页面
            $addon_flag = 'DIY_HOME_SERVICE_INDEX';
            $diy_service = new DiyService();
            $addon_index_template = $diy_service->getFirstPageData($addon_flag, 'home_service');
            $diy_service->add([
                'page_title' => $addon_index_template['title'],
                "title" => $addon_index_template['title'],
                "name" => $addon_flag,
                "type" => $addon_flag,
                "template" => $addon_index_template['template'],
                "mode" => $addon_index_template['mode'],
                "value" => json_encode($addon_index_template['data']),
                "is_default" => 1,
                "is_change" => 0
            ]);

            // 创建个人中心
            $addon_flag = 'DIY_HOME_SERVICE_MEMBER_INDEX';
            $diy_service = new DiyService();
            $addon_index_template = $diy_service->getFirstPageData($addon_flag, 'home_service');
            $diy_service->add([
                'page_title' => $addon_index_template['title'],
                "title" => $addon_index_template['title'],
                "name" => $addon_flag,
                "type" => $addon_flag,
                "template" => $addon_index_template['template'],
                "mode" => $addon_index_template['mode'],
                "value" => json_encode($addon_index_template['data']),
                "is_default" => 1,
                "is_change" => 0
            ]);

            //技师默认等级
            $technician_level = [
                'site_id' => $site_id,
                'level_num' => 0,
                'level_name' => '默认等级',
                'order_rate' => '1',
                'order_num' => 0,
                'is_default' => 1,
                'is_builtin_data' => 1,
                'create_time' => time()
            ];
            (new TechnicianLevel())->create($technician_level);

            //常见问题
            $help_category = [
                'site_id' => $site_id,
                'category_name' => '常见问题',
                'sort' => 0,
                'is_show' => 1,
                'help_count' => 0,
                'is_default' => 1,
                'is_builtin_data' => 1,
                'create_time' => time()
            ];
            (new HelpCategory())->create($help_category);
            //保障
            $guarantee_array = [
                [
                    'site_id' => $site_id,
                    'guarantee_title' => "品质保证",
                    'guarantee_image' => '/addon/home_service/addsite/guarantee1.png',
                    'guarantee_content' => "所有服务人员持健康证上岗，技能经严格考核，服务标准统一规范。",
                    'create_time' => time()
                ],
                [
                    'site_id' => $site_id,
                    'guarantee_title' => "优品推荐",
                    'guarantee_image' => '/addon/home_service/addsite/guarantee2.png',
                    'guarantee_content' => "根据用户评价精选优质服务人员，定期更新口碑服务清单。",
                    'create_time' => time()],
                [
                    'site_id' => $site_id,
                    'guarantee_title' => "全城最低",
                    'guarantee_image' => '/addon/home_service/addsite/guarantee3.png',
                    'guarantee_content' => "价格透明统一，同类服务全城比价，承诺差价双倍返还。",
                    'create_time' => time()
                ],
                [
                    'site_id' => $site_id,
                    'guarantee_title' => "服务无忧",
                    'guarantee_image' => '/addon/home_service/addsite/guarantee4.png',
                    'guarantee_content' => "客服全天在线，服务不满意免费返工，预约变更灵活便捷。",
                    'create_time' => time()
                ]
            ];
            (new Guarantee())->saveAll($guarantee_array);
            return true;





        }
    }
}
