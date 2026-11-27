<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\device;

use core\base\BaseModel;

/**
 * 设备分类 Excel 导入任务
 */
class RecycleDeviceModelImportTask extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_device_model_import_task';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['result_json'];
    protected $jsonAssoc = true;

    public function searchSiteIdAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('site_id', '=', (int)$value);
        }
    }

    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', '=', (string)$value);
        }
    }
}
