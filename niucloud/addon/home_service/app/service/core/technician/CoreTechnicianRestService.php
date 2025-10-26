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

namespace addon\home_service\app\service\core\technician;


use addon\home_service\app\model\technician\TechnicianRest;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 师傅休息服务层
 */
class CoreTechnicianRestService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new TechnicianRest();
    }


    /**
     * 获取师傅休息记录
     * @param array $where
     * @return array
     */
    public function getTechnicianRestList(array $where)
    {
        if (empty($where['technician_id'])) throw new CommonException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');
        $search_model = $this->model->field('id,date,technician_id,hour,notes')
            ->withSearch(["date"], $where)
            ->where([['technician_id', '=', $where['technician_id']], ['site_id', '=', $where['site_id']]])
            ->order('create_time desc');
        $list = $this->pageQuery($search_model, function ($item) {
            $item['time'] = explode(',', $item['hour']);
        });
        return $list;
    }

    /**
     * 设置师傅休息
     * @param array $data
     * @return bool
     */
    public function setTechnicianRest(array $data)
    {
        if (empty($data['technician_id'])) throw new CommonException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');
        if (empty($data['date'])) throw new CommonException('HOME_SERVICE_REST_DATE_NOT_EMPTY');
        $where = [
            ['technician_id', '=', $data['technician_id']],
            ['date', '=', $data['date']]
        ];
        Db::startTrans();
        try {
            $rest_info = $this->model->where($where)->findOrEmpty();
            if (!$rest_info->isEmpty()) {
                $rest_info->delete();
            }
            $this->model->create($data);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }

    }


    /**
     * 取消师傅休息
     * @param array $data
     * @return bool
     */
    public function cancelRest(array $data)
    {
        if (empty($data['technician_id'])) throw new CommonException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');
        if (empty($data['date'])) throw new CommonException('HOME_SERVICE_REST_DATE_NOT_EMPTY');
        $where = [
            ['technician_id', '=', $data['technician_id']],
            ['date', '=', $data['date']]
        ];
        Db::startTrans();
        try {
            $rest_info = $this->model->where($where)->findOrEmpty();
            if (!$rest_info->isEmpty()) {
                $rest_info->delete();
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 师傅休息 月数据 处理
     * @param array $data
     * @return bool
     */
    public function getRestMonthstats(array $where)
    {
        $list = $this->model->field('date,technician_id,hour,notes')
            ->withSearch(["date", "store_id"], $where)
            ->where([['technician_id', '=', $where['technician_id']], ['site_id', '=', $where['site_id']]])
            ->order('create_time desc')
            ->select()->toArray();
        foreach ($list as &$item) {
            $item['hour_array'] = explode(',', $item['hour']);
            $item['hour_text'] = $item['hour_array'][0] . "--" . end($item['hour_array']);
        }
        return $list;
    }


}
