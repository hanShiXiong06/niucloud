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

namespace addon\home_service\app\service\admin\strategy;


use addon\home_service\app\model\CityStrategy;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\Model;


/**
 * 城市策略服务层
 * Class ConfigService
 * @package adaddon\home_service\app\service\admin
 */
class CityStrategyService extends BaseAdminService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new CityStrategy();
    }


    /**
     * 获取 策略列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'city_strategy.id,province_id,city_id,way,site_id,value,create_time';
        $order = 'create_time desc';
        $with_where = [];
        if (!empty($where['city_name'])) $with_where = [['city.name', 'like', "%" . $where['city_name'] . "%"],];
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])
            ->withJoin([
                'province' => ['id', 'name', 'level'],
                'city' => ['id', 'name', 'level'],
            ])
            ->where($with_where)
            ->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取 策略信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'city_strategy.id,province_id,city_id,way,site_id,value,create_time';
        $info = $this->model->field($field)->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
            ->withJoin([
                'province' => ['id', 'name', 'level'],
                'city' => ['id', 'name', 'level'],
            ])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 获取 策略信息
     * @param int $id
     * @return array
     */
    public function getWhereInfo(array $where = [])
    {
        $field = 'id,province_id,city_id,way,site_id,value,create_time';
        $info = $this->model->field($field)->where([['site_id', '=', $this->site_id]])->where($where)->findOrEmpty()->toArray();
        return $info;
    }


    /**
     * 添加 策略
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $strategy_info = $this->getWhereInfo([['city_id', '=', $data['city_id']]]);
        if (empty($strategy_info)) {
            $data['site_id'] = $this->site_id;
            $data['create_time'] = time();
            $res = $this->model->create($data);
            return $res->id;
        } else {
            throw new CommonException('HOME_SERVICE_CITY_STRATEGY_IS_EXIST');
        }
    }


    /**
     *  策略编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除 策略
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $res = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->delete();
        return $res;
    }


}
