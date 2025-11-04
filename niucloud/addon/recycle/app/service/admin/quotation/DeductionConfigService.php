<?php

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleDeductionConfig;
use core\base\BaseAdminService;
use think\facade\Cache;

/**
 * 扣费配置服务层
 */
class DeductionConfigService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeductionConfig();
    }

    /**
     * 获取扣费配置分页列表
     */
    public function getPage(array $where = [])
    {
        $field = 'id, site_id, config_name,remark_text, sort, is_enable, create_at, update_at';
        $order = 'sort asc, id desc';

        $searchModel = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['config_name', 'is_enable'], $where)
            ->field($field)
            ->order($order);

        $list = $this->pageQuery($searchModel);
        return $list;
    }

    /**
     * 获取扣费配置列表（不分页）
     */
    public function getList(array $where = [])
    {
        $field = 'id, site_id, config_name,remark_text,  remark_text, sort, is_enable';
        $order = 'sort asc, id desc';

        $list = $this->model->where([['site_id', '=', $this->site_id], ['is_enable', '=', 1]])
            ->withSearch($where)
            ->field($field)
            ->order($order)
            ->select()
            ->toArray();

        return $list;
    }

    /**
     * 获取扣费配置详情
     */
    public function getInfo(int $id)
    {
        $field = 'id, site_id, config_name,remark_text,  remark_text, sort, is_enable';

        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->field($field)->findOrEmpty()->toArray();

        return $info;
    }

    /**
     * 添加扣费配置
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_at'] = time();
        $data['update_at'] = time();
        // remark_text
        $data['remark_text'] = $data['remark_text'];

        $res = $this->model->create($data);
        
        // 清除缓存
        $this->clearCache();
        
        return $res->id;
    }

    /**
     * 编辑扣费配置
     */
    public function edit(int $id, array $data)
    {
        $data['update_at'] = time();
        $data['remark_text'] = $data['remark_text'];

        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update($data);

        // 清除缓存
        $this->clearCache();

        return true;
    }

    /**
     * 删除扣费配置
     */
    public function del(int $id)
    {
        $res = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->delete();

        // 清除缓存
        $this->clearCache();

        return $res;
    }

    /**
     * 修改状态
     */
    public function modifyStatus(int $id, int $is_enable)
    {
        $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->update(['is_enable' => $is_enable, 'update_at' => time()]);

        // 清除缓存
        $this->clearCache();

        return true;
    }

   

  

    /**
     * 清除缓存
     */
    private function clearCache()
    {
        $cacheTag = 'deduction_config_' . $this->site_id;
        Cache::tag($cacheTag)->clear();
    }
}

