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

namespace addon\home_service\app\api\controller\card;

use addon\home_service\app\dict\goods\CardDict;
use addon\home_service\app\service\api\card\CardService;
use core\base\BaseApiController;


/**
 * 次卡套餐控制器
 * Class Card
 * @package addon\home_service\app\api\controller\card
 */
class Card extends BaseApiController
{
    public function getValidType()
    {
        return success('SUCCESS',CardDict::getValidType());
    }


    /**
     * 获取次卡项目列表
     * @description 获取次卡项目列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["card_name", ""],
            ["city_id", ""],
            ["goods_id", ""],
            ["valid_type", ""],
        ]);
        return success((new CardService())->getPage($data));
    }

    /**
     * 获取个人中心最新次卡
     * @description 获取次卡项目列表
     * @return \think\Response
     */
    public function getFirstInfo()
    {
        return success((new CardService())->getFirstInfo());
    }

    /**
     * 次卡项目详情
     * @description 次卡项目详情
     * @param int $card_id
     * @return \think\Response
     */
    public function info(int $card_id)
    {
        $data = $this->request->params([
            ["city_id", ""],
        ]);
        return success((new CardService())->getInfo($card_id,$data));
    }

    /**
     * 获取项目列表供组件调用
     * @return \think\Response
     */
    public function components()
    {
        $data = $this->request->params([
            ['num', 0],
            ['card_ids', ''],
            ['order', ''], // 排序方式（综合：空，销量：sale_num，价格：price）
            ['city_id', ''],
        ]);
        return success((new CardService())->getCardComponents($data));
    }
}
