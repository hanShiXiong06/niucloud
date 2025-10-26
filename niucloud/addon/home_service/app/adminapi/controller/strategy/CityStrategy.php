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

namespace addon\home_service\app\adminapi\controller\strategy;


use addon\home_service\app\service\admin\strategy\CityStrategyService;
use core\base\BaseAdminController;


/**
 * 城市策略控制器
 * @description
 * Class Reserve
 * @package app\adminapi\controller\reserve
 */
class CityStrategy extends BaseAdminController
{

    /**
     * 获取策略列表
     * @description 获取策略列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            ["city_name", ""],
        ]);
        return success((new CityStrategyService())->getPage($data));
    }

    /**
     * 策略详情
     * @description 策略详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new CityStrategyService())->getInfo($id));
    }

    /**
     * 添加 策略
     * @description 添加 策略
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["province_id", ""],
            ["city_id", ""],
            ["way", 0],
            ['value', 0],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\Citystrategy.add');
        $id = (new CityStrategyService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 策略编辑
     * @description 策略编辑
     * @param $id 策略id
     * @return \think\Response
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ["way", 0],
            ['value', 0],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\Citystrategy.edit');
        (new CityStrategyService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 策略删除
     * @description 策略删除
     * @param $id  策略id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new CityStrategyService())->del($id);
        return success('DELETE_SUCCESS');
    }


}
