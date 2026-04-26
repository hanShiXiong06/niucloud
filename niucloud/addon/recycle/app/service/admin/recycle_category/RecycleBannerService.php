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

namespace addon\recycle\app\service\admin\recycle_category;

use addon\recycle\app\model\recycle_category\RecycleBanner;
use core\base\BaseAdminService;
use think\db\exception\DbException;

/**
 * Banner服务层
 * Class RecycleBannerService
 * @package addon\recycle\app\service\admin\recycle_category
 */
class RecycleBannerService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleBanner();
    }

    /**
     * 获取Banner列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,image,sort,create_at,update_at';
        $order = 'sort desc';
        $search_model = $this->model->where([['site_id','=',$this->site_id]])->withSearch(['site_id', 'image'], $where)
            ->field($field)
            ->order($order);
         
         // 需要单独处理图片 将字符串转换为数组
         $list = $search_model->select()->toArray();
         foreach ($list as $key => $value) {
            $list[$key]['image'] = explode(',', $value['image']);
         }
         return $list;
    }

    /**
     * 获取Banner信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $info = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id]
        ])->findOrEmpty()->toArray();
        
        if (empty($info)) {
            throw new \think\Exception('Banner不存在');
        }
        return $info;
    }

    /**
     * 添加Banner
     * @param array $data
     * @return array
     */
    public function add(array $data)
    {
        

        try {
            $data['site_id'] = $this->site_id;
            $data['create_at'] = time();
            $data['update_at'] = time();
           
            $res = $this->model->create($data);
            
            return ['id' => $res->id];
        } catch (DbException $e) {
            throw new \think\Exception('添加失败');
        }
    }

    /**
     * 编辑Banner
     * @param array $data
     * @return bool
     */
    public function edit(array $data)
    {
        try {
            $info = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $data['id']]
            ])->findOrEmpty()->toArray();
            
            if (empty($info)) {
                throw new \think\Exception('Banner不存在');
            }

            $data['update_at'] = time();
            
            $this->model->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $data['id']]
            ])->update($data);
            return true;
        } catch (DbException $e) {
            throw new \think\Exception('编辑失败');
        }
    }

    /**
     * 删除Banner
     * @param int $id
     * @return bool
     */
    public function delete(int $id)
    {
     
        try {
            $info = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $id]
            ])->findOrEmpty()->toArray();
            if (empty($info)) {
                throw new \think\Exception('Banner不存在');
            }
            // 执行软删除 给delete_time赋值
            $this->model->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $id]
            ])->update(['delete_time' => time()]);

            
            return true;
        } catch (DbException $e) {
            throw new \think\Exception('删除失败');
        }
    }

    // 更改排序
    public function changeSort(int $id, int $sort)
    {
        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id]
        ])->update(['sort' => $sort]);
    }
}
