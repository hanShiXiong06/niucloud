<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

/** ERP 商品目录 Excel 异步导入任务 */
class ErpCatalogImportTask extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_catalog_import_task';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['result_json'];
    protected $jsonAssoc = true;

    public function searchSiteIdAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) $query->where('site_id', '=', (int)$value);
    }

    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) $query->where('status', '=', (string)$value);
    }
}
