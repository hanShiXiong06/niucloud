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
namespace addon\phone_shop\app\listener;

use addon\phone_shop\app\service\core\delivery\CoreCompanyService;
use addon\phone_shop\app\service\core\delivery\CoreElectronicSheetService;
use app\service\admin\diy\DiyService;
use app\service\core\poster\CorePosterService;

/**
 * 站点创建之后
 */
class AddSiteAfterListener
{
    public function handle($params = [])
    {
        if (in_array('phone_shop', $params[ 'main_app' ]) && count($params[ 'main_app' ]) == 1) {
            $site_id = $params[ 'site_id' ];
            request()->siteId($site_id);

            // 创建默认商品海报
            $poster = new CorePosterService();
            $template = $poster->getTemplateList('phone_shop', 'phone_shop_goods')[ 0 ];
            $poster->add($site_id, 'phone_shop', [
                'name' => $template[ 'name' ],
                'type' => $template[ 'type' ],
                'value' => $template[ 'data' ],
                'status' => 1,
                'is_default' => 1
            ]);

            // 创建默认积分商品海报
            $poster = new CorePosterService();
            $template = $poster->getTemplateList('phone_shop', 'phone_shop_point_goods')[ 0 ];
            $poster->add($site_id, 'phone_shop', [
                'name' => $template[ 'name' ],
                'type' => $template[ 'type' ],
                'value' => $template[ 'data' ],
                'status' => 1,
                'is_default' => 1
            ]);

            // 创建 积分商城 微页面
            $addon_flag = 'DIY_PHONE_SHOP_POINT_INDEX';

            $diy_service = new DiyService();
            $addon_index_template = $diy_service->getFirstPageData($addon_flag, 'phone_shop');

            $diy_service->add([
                'page_title' => $addon_index_template[ 'title' ],
                "title" => $addon_index_template[ 'title' ],
                "name" => $addon_flag,
                "type" => $addon_flag,
                "template" => $addon_index_template[ 'template' ],
                "mode" => $addon_index_template[ 'mode' ],
                "value" => json_encode($addon_index_template[ 'data' ]),
                "is_default" => 1,
                "is_change" => 0
            ]);

            // 创建物流公司
            $company_service = new CoreCompanyService();

            $company_list = [
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '邮政EMS',
                    'logo' => 'addon/phone_shop/express/ems.jpg',
                    'url' => 'https://www.ems.com.cn',
                    'express_no' => 'EMS',
                    'express_no_electronic_sheet' => 'EMS',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130 76*130',
                            'template_size' => '130',
                        ],
                        [
                            'template_name' => '一联130新 76*130',
                            'template_size' => '1301',
                        ],
                        [
                            'template_name' => '二联180 100*180',
                            'template_size' => '180',
                        ],
                        [
                            'template_name' => '二联180新 100*180',
                            'template_size' => '1801',
                        ],
                        [
                            'template_name' => '二联150 100*150',
                            'template_size' => '0',
                        ],
                        [
                            'template_name' => '二联150新 100*150',
                            'template_size' => '1501',
                        ]
                    ],
                    'exp_type' => [
                        [
                            'text' => '特快专递',
                            'value' => 1
                        ],
                        [
                            'text' => '代收到付',
                            'value' => 8
                        ],
                        [
                            'text' => '快递包裹',
                            'value' => 9
                        ],
                        [
                            'text' => '电商标快',
                            'value' => 10
                        ],
                        [
                            'text' => '国内标快',
                            'value' => 11
                        ]
                    ]
                ],
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '邮政快递包裹',
                    'logo' => 'addon/phone_shop/express/youzhengkd.png',
                    'url' => 'https://www.ems.com.cn',
                    'express_no' => 'YZPY',
                    'express_no_electronic_sheet' => 'YZPY',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130 76*130',
                            'template_size' => '130',
                        ],
                        [
                            'template_name' => '一联130新 76*130',
                            'template_size' => '1301',
                        ],
                        [
                            'template_name' => '二联1801 100*180',
                            'template_size' => '1801',
                        ],
                        [
                            'template_name' => '二联180 100*180',
                            'template_size' => '0',
                        ],
                        [
                            'template_name' => '二联180新 100*180',
                            'template_size' => '180',
                        ],
                        [
                            'template_name' => '二联150 100*150',
                            'template_size' => '150',
                        ],
                        [
                            'template_name' => '二联150新 100*150',
                            'template_size' => '1501',
                        ]
                    ],
                    'exp_type' => [
                        [
                            'text' => '标准快递',
                            'value' => 1
                        ]
                    ]
                ],
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '顺丰速运',
                    'logo' => 'addon/phone_shop/express/shunfeng.png',
                    'url' => 'https://www.sf-express.com/chn/sc',
                    'express_no' => 'SF',
                    'express_no_electronic_sheet' => 'SF',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130新 76*130',
                            'template_size' => '1301',
                        ],
                        [
                            'template_name' => '二联150新 100*150',
                            'template_size' => '0',
                        ],
                        [
                            'template_name' => '二联180新 100*180',
                            'template_size' => '180',
                        ],
                        [
                            'template_name' => '三联210新 100*210',
                            'template_size' => '210',
                        ]
                    ],
                    'exp_type' => [
                        [
                            'text' => '顺丰特快',
                            'value' => 1
                        ],
                        [
                            'text' => '顺丰标快',
                            'value' => 2
                        ],
                        [
                            'text' => '顺丰即日',
                            'value' => 6
                        ],
                        [
                            'text' => '冷运标快',
                            'value' => 201
                        ],
                        [
                            'text' => '顺丰微小件',
                            'value' => 202
                        ]
                    ]
                ],
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '中通快递',
                    'logo' => 'addon/phone_shop/express/zhongtong.png',
                    'url' => 'https://www.zto.com',
                    'express_no' => 'ZTO',
                    'express_no_electronic_sheet' => 'ZTO',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130 76*130',
                            'template_size' => '130',
                        ],
                        [
                            'template_name' => '二联180 100*180',
                            'template_size' => '0',
                        ],
                        [
                            'template_name' => '二联180新 100*180',
                            'template_size' => '180',
                        ]
                    ],
                    'exp_type' => []
                ],
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '圆通速递',
                    'logo' => 'addon/phone_shop/express/yuantong.png',
                    'url' => 'https://www.yto.net.cn',
                    'express_no' => 'YTO',
                    'express_no_electronic_sheet' => 'YTO',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130 76*130',
                            'template_size' => '130',
                        ],
                        [
                            'template_name' => '一联130新 76*130',
                            'template_size' => '1301',
                        ],
                        [
                            'template_name' => '二联180 100*180',
                            'template_size' => '0',
                        ],
                        [
                            'template_name' => '三联180 100*180',
                            'template_size' => '180',
                        ],
                        [
                            'template_name' => '二联180新 100*180',
                            'template_size' => '1801',
                        ]
                    ],
                    'exp_type' => [
                        [
                            'text' => '标准快递',
                            'value' => 1
                        ],
                        [
                            'text' => '圆准达',
                            'value' => 2
                        ]
                    ]
                ],
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '申通快递',
                    'logo' => 'addon/phone_shop/express/shentong.png',
                    'url' => 'https://www.sto.cn/pc',
                    'express_no' => 'STO',
                    'express_no_electronic_sheet' => 'STO',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130 76*130',
                            'template_size' => '130',
                        ],
                        [
                            'template_name' => '一联130新 76*130',
                            'template_size' => '1301',
                        ],
                        [
                            'template_name' => '二联180 100*180',
                            'template_size' => '0',
                        ],
                        [
                            'template_name' => '二联180新 100*180',
                            'template_size' => '180',
                        ],
                        [
                            'template_name' => '三联180新 100*180',
                            'template_size' => '1803',
                        ],
                        [
                            'template_name' => '二联150 100*150',
                            'template_size' => '150',
                        ]
                    ],
                    'exp_type' => [
                        [
                            'text' => '标准快递',
                            'value' => 1
                        ],
                    ]
                ],
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '韵达速递',
                    'logo' => 'addon/phone_shop/express/yunda.png',
                    'url' => 'http://www.yundaex.com/cn',
                    'express_no' => 'YD',
                    'express_no_electronic_sheet' => 'YD',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130 100*130',
                            'template_size' => '130',
                        ],
                        [
                            'template_name' => '二联180 100*180',
                            'template_size' => '180',
                        ],
                        [
                            'template_name' => '二联203 100*203',
                            'template_size' => '0',
                        ]
                    ],
                    'exp_type' => []
                ],
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '极兔速递',
                    'logo' => 'addon/phone_shop/express/jitu.png',
                    'url' => 'https://www.jtexpress.cn',
                    'express_no' => 'JTSD',
                    'express_no_electronic_sheet' => 'JTSD',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130 100*130',
                            'template_size' => '130',
                        ],
                        [
                            'template_name' => '二联180 100*180',
                            'template_size' => '0',
                        ]
                    ],
                    'exp_type' => [
                        [
                            'text' => '标准快递',
                            'value' => 1
                        ],
                        [
                            'text' => '兔优达',
                            'value' => 2
                        ]
                    ]
                ],
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '菜鸟速递',
                    'logo' => 'addon/phone_shop/express/cainiao.jpg',
                    'url' => 'https://express.cainiao.com/',
                    'express_no' => 'CNSD',
                    'express_no_electronic_sheet' => 'CNSD',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130 100*130',
                            'template_size' => '0',
                        ]
                    ],
                    'exp_type' => [
                        [
                            'text' => '当日达',
                            'value' => 1
                        ],
                        [
                            'text' => '次日达',
                            'value' => 2
                        ],
                        [
                            'text' => '预约配送',
                            'value' => 3
                        ],
                        [
                            'text' => '当日下午达',
                            'value' => 4
                        ],
                        [
                            'text' => '当日夜间达',
                            'value' => 5
                        ],
                        [
                            'text' => '次日上午达',
                            'value' => 6
                        ]
                    ]
                ],
                [
                    'site_id' => $site_id,
                    'create_time' => time(),
                    'company_name' => '京东快递',
                    'logo' => 'addon/phone_shop/express/jingdong.jpg',
                    'url' => 'https://www.jdl.com',
                    'express_no' => 'JD',
                    'express_no_electronic_sheet' => 'JD',
                    'electronic_sheet_switch' => 1,
                    'print_style' => [
                        [
                            'template_name' => '一联130新 76*130',
                            'template_size' => '130',
                        ],
                        [
                            'template_name' => '二联110 100*110',
                            'template_size' => '0',
                        ],
                        [
                            'template_name' => '二联110新 100*110',
                            'template_size' => '110',
                        ]
                    ],
                    'exp_type' => []
                ]
            ];
            $company_service->addAll($company_list);

            $company_list_for_print = $company_service->getList([
                [ 'site_id', '=', $site_id ],
                [ 'express_no_electronic_sheet', 'in', [ 'EMS', 'YZPY', 'SF' ] ],
                [ 'electronic_sheet_switch', '=', 1 ],
            ], 'company_id,company_name,express_no_electronic_sheet,print_style,exp_type');

            $electronic_sheet_list = [];
            foreach ($company_list_for_print as $k => $v) {
                $electronic_sheet_list[] = [
                    'site_id' => $site_id,
                    'template_name' => $v[ 'company_name' ] . ' ' . $v[ 'print_style' ][ 0 ][ 'template_name' ],
                    'express_company_id' => $v[ 'company_id' ],
                    'customer_name' => '电子面单账号',
                    'customer_pwd' => '电子面单密码',
                    'send_site' => '',
                    'send_staff' => '',
                    'month_code' => '',
                    'pay_type' => 1,
                    'is_notice' => 0,
                    'status' => 1,
                    'exp_type' => $v[ 'exp_type' ][ 0 ][ 'value' ],
                    'print_style' => $v[ 'print_style' ][ 0 ][ 'template_size' ],
                    'is_default' => $k == 0 ? 1 : 0,
                    'create_time' => time()
                ];
            }

            $electronic_sheet_service = new CoreElectronicSheetService();
            $electronic_sheet_service->addAll($electronic_sheet_list);

            return true;
        }
    }
}
