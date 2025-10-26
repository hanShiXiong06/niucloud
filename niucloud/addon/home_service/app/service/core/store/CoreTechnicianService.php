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

namespace addon\home_service\app\service\core\store;


use addon\home_service\app\dict\technician\TechnicianDict;
use addon\home_service\app\model\store\StoreTechnician;
use addon\home_service\app\model\technician\TechnicianLevel;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;


/**
 * 门店师傅服务层
 */
class CoreTechnicianService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreTechnician();
    }

    /**
     * 添加编辑门店师傅比例
     * @param array $data
     * @return bool
     */
    public function storeTechnicianRate($data)
    {
        if ($data['store_id'] == 0) return true;

        $condition = [
            ['site_id', '=', $data['site_id']],
            ['technician_id', '=', $data['technician_id']]
        ];

        Db::startTrans();
        try {
            $order_rate = $data['distribute_type'] == TechnicianDict::CUSTOMIZE
                ? $data['order_rate']
                : (new TechnicianLevel())->where([
                    ['site_id', '=', $data['site_id']],
                    ['level_id', '=', $data['level_id']]
                ])->value('order_rate');

            $this->model->where($condition)->update(['status' => 0]);

            $store_technician_info = $this->model->where(array_merge($condition, [
                ['store_id', '=', $data['store_id']]
            ]))->findOrEmpty();

            if ($store_technician_info->isEmpty()) {
                // 新增
                $this->model->create([
                    'site_id'       => $data['site_id'],
                    'technician_id' => $data['technician_id'],
                    'store_id'      => $data['store_id'],
                    'order_rate'    => $order_rate,
                    'status'        => 1,
                ]);
            } else {
                $save_data['status'] = 1;
                if (isset($data['edit_order_rate'])){
                    $save_data['order_rate'] = $data['order_rate'];
                }
                // 修改状态
                $store_technician_info->save($save_data);
            }
            Db::commit();
            return true;
          } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage() . $e->getfile() . $e->getline());
          }
    }


    /**
     * 添加统计数据
     * @param int $site_id
     * @param int $store_id
     * @param int $technician_id
     * @param $data
     * @param $incData
     * @return true
     */
    public static function addStat($site_id, $store_id, $technician_id, $data = [], $incData = [])
    {
        if(empty($store_id)) return true;

        $model = new StoreTechnician();
        $stat_data = [
            'site_id' => $site_id,
            'store_id' => $store_id,
            'technician_id' => $technician_id
        ];

        $stat = $model->where($stat_data)->findOrEmpty();

        if ($stat->isEmpty()) {
            // 插入时：累加的字段初始化
            foreach ($incData as $field => $value) {
                $data[$field] = $value;
            }
            $model->create(array_merge($stat_data, $data));
        } else {
            // 累加更新
            foreach ($incData as $field => $value) {
                $stat->$field = Db::raw("{$field} + {$value}");
            }
            // 覆盖更新
            if (!empty($data)) {
                $stat->save($data);
            } else {
                $stat->save();
            }
        }

        return true;
    }


}
