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

use addon\recycle\app\model\recycle_category\RecycleCategoryConfig;
use core\base\BaseAdminService;
use think\db\exception\DbException;

/**
 * 回收分类配置服务层
 * Class RecycleCategoryConfigService
 * @package addon\recycle\app\service\admin\recycle_category
 */
class RecycleCategoryConfigService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleCategoryConfig();
    }

    /**
     * 获取配置信息
     * @return array
     */
    public function getConfig()
    {
        $info = $this->model->where([
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
        
        return $info;
    }

    /**
     * 更新配置
     * @return array
     */
    public function setConfig()
    {
        try {
            // 查询当前站点配置
            $info = $this->model->where([
                ['site_id', '=', $this->site_id]
            ])->findOrEmpty()->toArray();
            
            if (empty($info)) {
                // 不存在则添加
                $data = [
                    'site_id' => $this->site_id,
                    'is_enable' => 1,
                    'create_at' => time(),
                    'update_at' => time()
                ];
                $res = $this->model->create($data);
                return ['id' => $res->id];
            } else {
                // 存在则切换状态
                $data = [
                    'is_enable' => $info['is_enable'] ? 0 : 1,
                    'update_at' => time()
                ];
                $this->model->where([
                    ['site_id', '=', $this->site_id]
                ])->update($data);
                return ['id' => $info['id']];
            }
        } catch (DbException $e) {
            throw new \think\Exception('操作失败');
        }
    }
}
