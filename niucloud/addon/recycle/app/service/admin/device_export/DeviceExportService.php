<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\device_export;

use addon\recycle\app\model\RecycleDevice;
use core\base\BaseAdminService;
use app\job\sys\ExportJob;

/**
 * 设备导出服务
 */
class DeviceExportService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDevice();
    }

    /**
     * 获取已回收设备分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = []): array
    {
        
        $field = 'id, imei, imei2, sn, model, member_id, category_id, status, update_at, final_price, create_at, order_id';
        
        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['imei', 'model', 'status', 'update_at'], $where)
            ->with(['order' ])
            ->field($field)
            ->order('update_at desc')
            ->append(['status_name', 'category_name' , 'nickname']);

        // 筛选分类
        if (!empty($where['category_id'])) {
            $search_model->where('category_id', $where['category_id']);
        }

        return $this->pageQuery($search_model);
    }

    /**
     * 导出已回收设备
     * @param array $where
     * @return bool
     */
    public function export(array $where = []): bool
    {
        // 添加站点条件
        $where['site_id'] = $this->site_id;
        
        // 调用系统导出Job
        ExportJob::dispatch(['site_id' => $this->site_id, 'type' => 'recycle_device', 'where' => $where, 'page' => ['page' => 0, 'limit' => 0]]);
        
        return true;
    }
}
